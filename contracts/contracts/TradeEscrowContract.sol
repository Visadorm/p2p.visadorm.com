// SPDX-License-Identifier: MIT
pragma solidity 0.8.27;

import "@openzeppelin/contracts/access/AccessControl.sol";
import "@openzeppelin/contracts/utils/Pausable.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC20/utils/SafeERC20.sol";
import "./SoulboundTradeNFT.sol";

/**
 * @title TradeEscrowContract
 * @dev P2P USDC trading escrow with anti-spam stakes, dispute resolution,
 *      cash meeting NFT support, and merchant-mirrored sell flow.
 *
 * Roles:
 *   ADMIN_ROLE    — Mediator Council Gnosis 2-of-3 multisig (dispute resolution, pause, ownership)
 *   OPERATOR_ROLE — Backend server / gas wallet (BUY FLOW ONLY — submits txs on behalf of users)
 *
 * Buy flow: merchant locks USDC, buyer takes via operator, merchant releases via operator.
 * Sell flow: seller (user) locks USDC, merchant joins via own wallet, seller releases via own wallet.
 *            Operator has ZERO authority over sell trades — every state transition requires party
 *            wallet signature (spec D7, D14, D16).
 *
 * Fee: 0.2% (20 basis points) per trade, sent to feeWallet.
 *      Always deducted on release AND on dispute resolution (both outcomes).
 * Stake: $5 USDC anti-spam (returned after completion or dispute resolve).
 */
contract TradeEscrowContract is AccessControl, Pausable, ReentrancyGuard {
    using SafeERC20 for IERC20;

    // ─── Roles ───
    bytes32 public constant ADMIN_ROLE = keccak256("ADMIN_ROLE");
    bytes32 public constant OPERATOR_ROLE = keccak256("OPERATOR_ROLE");

    // ─── Constants ───
    uint256 public constant FEE_BPS = 20; // 0.2%
    uint256 public constant BPS_DENOMINATOR = 10_000;
    uint256 public constant STAKE_AMOUNT = 5e6; // $5 USDC (6 decimals)
    uint256 public constant MIN_TRADE_AMOUNT = 10e6; // $10 minimum (6 decimals)

    // ─── Immutables ───
    IERC20 public immutable usdcToken;
    address public immutable feeWallet;
    SoulboundTradeNFT public immutable tradeNFT;

    // ─── Enums ───
    // Buy flow uses: EscrowLocked → PaymentSent → Completed (or Disputed → Resolved, Cancelled)
    // Sell flow uses: Pending (FUNDED) → EscrowLocked (joined) → PaymentSent (fiat sent) → Completed
    //                 (or Disputed → Resolved, Cancelled, Expired)
    // New cases (Pending, Expired) appended at end to preserve existing buy-flow enum positions.
    enum TradeStatus {
        None,
        EscrowLocked,
        PaymentSent,
        Completed,
        Disputed,
        Cancelled,
        Resolved,
        Pending,
        Expired
    }

    // Trade kind discriminator (added for sell flow). None preserves buy-flow zero-init.
    enum TradeKind {
        None,
        Buy,
        Sell
    }

    // ─── Structs ───
    // New fields (kind, seller, isCashTrade) appended at end so existing buy-flow
    // tests using named field access continue to work.
    struct Trade {
        address merchant;
        address buyer;
        uint256 amount;
        uint256 stakeAmount;
        address stakePaidBy;
        TradeStatus status;
        bool isPrivate;
        uint256 createdAt;
        uint256 expiresAt;
        // ─── Sell-flow additions ───
        TradeKind kind;
        address seller;
        bool isCashTrade;
    }

    // ─── State ───
    mapping(bytes32 => Trade) public trades;
    mapping(address => uint256) public merchantEscrowBalance;
    mapping(address => uint256) public merchantLockedInTrades;

    // ─── Events ───
    event EscrowDeposited(address indexed merchant, uint256 amount);
    event EscrowWithdrawn(address indexed merchant, uint256 amount);
    event TradeCreated(
        bytes32 indexed tradeId,
        address indexed merchant,
        address indexed buyer,
        uint256 amount,
        bool isPrivate
    );
    event PaymentMarkedSent(bytes32 indexed tradeId);
    event TradeCompleted(bytes32 indexed tradeId, uint256 fee);
    event TradeCancelled(bytes32 indexed tradeId);
    event DisputeOpened(bytes32 indexed tradeId, address indexed openedBy);
    event DisputeResolved(bytes32 indexed tradeId, address indexed winner, uint256 amount);

    // Sell-flow events
    event SellTradeOpened(
        bytes32 indexed tradeId,
        address indexed seller,
        address indexed merchant,
        uint256 amount
    );
    event SellTradeJoined(bytes32 indexed tradeId, address indexed merchant);
    event SellPaymentMarked(bytes32 indexed tradeId);
    event SellEscrowReleased(bytes32 indexed tradeId, uint256 fee);
    event ExpiredTradeCancelled(bytes32 indexed tradeId);

    // ─── Constructor ───
    constructor(
        address _usdcToken,
        address _feeWallet,
        address _tradeNFT,
        address _admin,
        address _operator
    ) {
        require(_usdcToken != address(0), "Invalid USDC address");
        require(_feeWallet != address(0), "Invalid fee wallet");
        require(_tradeNFT != address(0), "Invalid NFT address");
        require(_admin != address(0), "Invalid admin");
        require(_operator != address(0), "Invalid operator");

        usdcToken = IERC20(_usdcToken);
        feeWallet = _feeWallet;
        tradeNFT = SoulboundTradeNFT(_tradeNFT);

        _grantRole(DEFAULT_ADMIN_ROLE, _admin);
        _grantRole(ADMIN_ROLE, _admin);
        _grantRole(OPERATOR_ROLE, _operator);
    }

    // ─── Merchant Escrow (Buy Flow) ───

    function depositEscrow(
        address merchant,
        uint256 amount
    ) external nonReentrant whenNotPaused onlyRole(OPERATOR_ROLE) {
        require(amount > 0, "Amount must be > 0");

        usdcToken.safeTransferFrom(merchant, address(this), amount);
        merchantEscrowBalance[merchant] += amount;

        emit EscrowDeposited(merchant, amount);
    }

    function withdrawEscrow(
        address merchant,
        uint256 amount
    ) external nonReentrant whenNotPaused onlyRole(OPERATOR_ROLE) {
        require(amount > 0, "Amount must be > 0");
        uint256 available = merchantEscrowBalance[merchant] - merchantLockedInTrades[merchant];
        require(amount <= available, "Insufficient available balance");

        merchantEscrowBalance[merchant] -= amount;
        usdcToken.safeTransfer(merchant, amount);

        emit EscrowWithdrawn(merchant, amount);
    }

    function getAvailableBalance(address merchant) external view returns (uint256) {
        return merchantEscrowBalance[merchant] - merchantLockedInTrades[merchant];
    }

    // ─── Trade Lifecycle (Buy Flow) ───

    /**
     * @dev Initiate a buy trade. Locks merchant USDC (amount + fee). Buyer stakes $5 on public trades.
     */
    function initiateTrade(
        bytes32 tradeId,
        address merchant,
        address buyer,
        uint256 amount,
        bool isPrivate,
        uint256 expiresAt
    ) external nonReentrant whenNotPaused onlyRole(OPERATOR_ROLE) {
        require(trades[tradeId].status == TradeStatus.None, "Trade already exists");
        require(amount >= MIN_TRADE_AMOUNT, "Below minimum trade amount");
        require(merchant != buyer, "Merchant cannot trade with self");

        uint256 fee = (amount * FEE_BPS) / BPS_DENOMINATOR;
        uint256 total = amount + fee;

        uint256 available = merchantEscrowBalance[merchant] - merchantLockedInTrades[merchant];
        require(total <= available, "Insufficient merchant escrow");

        uint256 stakeAmount = 0;
        address stakePaidBy = address(0);

        if (!isPrivate) {
            stakeAmount = STAKE_AMOUNT;
            stakePaidBy = buyer;
            usdcToken.safeTransferFrom(buyer, address(this), STAKE_AMOUNT);
        }

        merchantLockedInTrades[merchant] += total;

        trades[tradeId] = Trade({
            merchant: merchant,
            buyer: buyer,
            amount: amount,
            stakeAmount: stakeAmount,
            stakePaidBy: stakePaidBy,
            status: TradeStatus.EscrowLocked,
            isPrivate: isPrivate,
            createdAt: block.timestamp,
            expiresAt: expiresAt,
            kind: TradeKind.Buy,
            seller: address(0),
            isCashTrade: false
        });

        emit TradeCreated(tradeId, merchant, buyer, amount, isPrivate);
    }

    function markPaymentSent(
        bytes32 tradeId
    ) external nonReentrant whenNotPaused onlyRole(OPERATOR_ROLE) {
        Trade storage trade = trades[tradeId];
        require(trade.status == TradeStatus.EscrowLocked, "Invalid trade status");
        require(trade.kind == TradeKind.Buy, "Operator cannot touch sell");

        trade.status = TradeStatus.PaymentSent;

        emit PaymentMarkedSent(tradeId);
    }

    function confirmPayment(
        bytes32 tradeId
    ) external nonReentrant whenNotPaused onlyRole(OPERATOR_ROLE) {
        Trade storage trade = trades[tradeId];
        require(
            trade.status == TradeStatus.PaymentSent || trade.status == TradeStatus.EscrowLocked,
            "Invalid trade status"
        );
        require(trade.kind == TradeKind.Buy, "Operator cannot release sell");

        uint256 fee = (trade.amount * FEE_BPS) / BPS_DENOMINATOR;
        uint256 total = trade.amount + fee;

        trade.status = TradeStatus.Completed;
        merchantLockedInTrades[trade.merchant] -= total;
        merchantEscrowBalance[trade.merchant] -= total;

        usdcToken.safeTransfer(trade.buyer, trade.amount);

        if (fee > 0) {
            usdcToken.safeTransfer(feeWallet, fee);
        }

        if (trade.stakeAmount > 0) {
            usdcToken.safeTransfer(trade.stakePaidBy, trade.stakeAmount);
        }

        emit TradeCompleted(tradeId, fee);
    }

    function cancelTrade(
        bytes32 tradeId
    ) external nonReentrant whenNotPaused onlyRole(OPERATOR_ROLE) {
        Trade storage trade = trades[tradeId];
        require(
            trade.status == TradeStatus.EscrowLocked,
            "Can only cancel before payment is sent"
        );
        require(trade.kind == TradeKind.Buy, "Operator cannot touch sell");

        uint256 fee = (trade.amount * FEE_BPS) / BPS_DENOMINATOR;
        uint256 total = trade.amount + fee;

        trade.status = TradeStatus.Cancelled;
        merchantLockedInTrades[trade.merchant] -= total;

        if (trade.stakeAmount > 0) {
            usdcToken.safeTransfer(feeWallet, trade.stakeAmount);
        }

        emit TradeCancelled(tradeId);
    }

    // ─── Disputes (Buy Flow) ───

    function openDispute(
        bytes32 tradeId,
        address openedBy
    ) external nonReentrant whenNotPaused onlyRole(OPERATOR_ROLE) {
        Trade storage trade = trades[tradeId];
        require(
            trade.status == TradeStatus.EscrowLocked || trade.status == TradeStatus.PaymentSent,
            "Invalid trade status"
        );
        require(trade.kind == TradeKind.Buy, "Operator cannot touch sell");
        require(
            openedBy == trade.merchant || openedBy == trade.buyer,
            "Not a party to this trade"
        );

        trade.status = TradeStatus.Disputed;

        emit DisputeOpened(tradeId, openedBy);
    }

    function resolveDispute(
        bytes32 tradeId,
        address winner
    ) external nonReentrant onlyRole(ADMIN_ROLE) {
        Trade storage trade = trades[tradeId];
        require(trade.status == TradeStatus.Disputed, "Trade not in dispute");
        require(trade.kind == TradeKind.Buy, "Use resolveSellDispute for sell trades");
        require(
            winner == trade.merchant || winner == trade.buyer,
            "Winner must be merchant or buyer"
        );

        uint256 fee = (trade.amount * FEE_BPS) / BPS_DENOMINATOR;
        uint256 total = trade.amount + fee;

        trade.status = TradeStatus.Resolved;
        merchantLockedInTrades[trade.merchant] -= total;

        if (winner == trade.merchant) {
            // Merchant wins: unlock escrow fully, no fee charged (preserves existing buy behavior)
        } else {
            merchantEscrowBalance[trade.merchant] -= total;
            usdcToken.safeTransfer(winner, trade.amount);

            if (fee > 0) {
                usdcToken.safeTransfer(feeWallet, fee);
            }
        }

        if (trade.stakeAmount > 0) {
            usdcToken.safeTransfer(trade.stakePaidBy, trade.stakeAmount);
        }

        emit DisputeResolved(tradeId, winner, trade.amount);
    }

    // ─── Cash Meeting NFT (Buy Flow — operator-driven) ───

    function mintTradeNFT(
        bytes32 tradeId,
        string calldata meetingLocation
    ) external whenNotPaused onlyRole(OPERATOR_ROLE) {
        Trade storage trade = trades[tradeId];
        require(trade.status == TradeStatus.EscrowLocked, "Invalid trade status");
        require(trade.kind == TradeKind.Buy, "Operator cannot touch sell");

        tradeNFT.mint(trade.buyer, tradeId, trade.merchant, trade.amount, meetingLocation);
    }

    function burnTradeNFT(bytes32 tradeId) external onlyRole(OPERATOR_ROLE) {
        uint256 tokenId = tradeNFT.tradeIdToTokenId(tradeId);
        require(tokenId != 0, "No NFT for trade");
        require(trades[tradeId].kind == TradeKind.Buy, "Operator cannot touch sell");

        tradeNFT.burn(tokenId);
    }

    // ─── SELL FLOW ───
    // Spec: seller is sole authority. Backend has no release power. Buyer (merchant) signals
    // only via own wallet. Disputes resolved by multisig council. Once released, trade is final.
    // No OPERATOR_ROLE on any sell function — every state transition requires party wallet sig.

    /**
     * @dev Seller opens a sell trade — locks `amount + fee + stake` USDC from msg.sender.
     *      Stake gating: caller passes requireStake based on entry path
     *      (public merchant page, private link, or cash trade) per backend settings.
     *      meetingLocation used only when isCashTrade=true; pass empty string otherwise.
     */
    function openSellTrade(
        bytes32 tradeId,
        address merchant,
        uint256 amount,
        uint256 expiresAt,
        bool requireStake,
        bool isCashTrade,
        string calldata meetingLocation
    ) external nonReentrant whenNotPaused {
        require(trades[tradeId].status == TradeStatus.None, "Trade already exists");
        require(amount >= MIN_TRADE_AMOUNT, "Below minimum trade amount");
        require(expiresAt > block.timestamp, "Expiry must be in future");
        require(merchant != msg.sender, "Cannot trade with self");
        require(merchant != address(0), "Invalid merchant");

        uint256 fee = (amount * FEE_BPS) / BPS_DENOMINATOR;
        uint256 stake = requireStake ? STAKE_AMOUNT : 0;
        uint256 total = amount + fee + stake;

        usdcToken.safeTransferFrom(msg.sender, address(this), total);

        trades[tradeId] = Trade({
            merchant: merchant,
            buyer: merchant, // mirror: merchant receives USDC at release
            amount: amount,
            stakeAmount: stake,
            stakePaidBy: msg.sender,
            status: TradeStatus.Pending, // FUNDED
            isPrivate: false,
            createdAt: block.timestamp,
            expiresAt: expiresAt,
            kind: TradeKind.Sell,
            seller: msg.sender,
            isCashTrade: isCashTrade
        });

        emit SellTradeOpened(tradeId, msg.sender, merchant, amount);

        if (isCashTrade) {
            tradeNFT.mint(msg.sender, tradeId, merchant, amount, meetingLocation);
        }
    }

    /**
     * @dev Merchant (buyer of USDC) joins seller's open trade. Direct wallet call only.
     */
    function joinSellTrade(bytes32 tradeId) external nonReentrant whenNotPaused {
        Trade storage trade = trades[tradeId];
        require(trade.kind == TradeKind.Sell, "Not sell");
        require(trade.status == TradeStatus.Pending, "Not joinable");
        require(block.timestamp <= trade.expiresAt, "Expired");
        require(msg.sender == trade.merchant, "Only target merchant");

        trade.status = TradeStatus.EscrowLocked;
        emit SellTradeJoined(tradeId, msg.sender);
    }

    /**
     * @dev Merchant signals fiat sent. Direct wallet call only.
     */
    function markSellPaymentSent(bytes32 tradeId) external whenNotPaused {
        Trade storage trade = trades[tradeId];
        require(trade.kind == TradeKind.Sell, "Not sell");
        require(trade.status == TradeStatus.EscrowLocked, "Bad status");
        require(msg.sender == trade.merchant, "Only merchant (buyer of USDC)");

        trade.status = TradeStatus.PaymentSent;
        emit SellPaymentMarked(tradeId);
    }

    /**
     * @dev SELLER ONLY — releases escrow to merchant. Direct wallet call.
     */
    function releaseSellEscrow(bytes32 tradeId) external nonReentrant whenNotPaused {
        Trade storage trade = trades[tradeId];
        require(trade.kind == TradeKind.Sell, "Not sell");
        require(msg.sender == trade.seller, "Only seller");
        require(
            trade.status == TradeStatus.PaymentSent || trade.status == TradeStatus.EscrowLocked,
            "Bad status"
        );

        uint256 fee = (trade.amount * FEE_BPS) / BPS_DENOMINATOR;
        uint256 merchantAmount = trade.amount - fee;

        trade.status = TradeStatus.Completed;

        usdcToken.safeTransfer(trade.merchant, merchantAmount);
        if (fee > 0) usdcToken.safeTransfer(feeWallet, fee);
        if (trade.stakeAmount > 0) {
            usdcToken.safeTransfer(trade.stakePaidBy, trade.stakeAmount);
        }

        if (trade.isCashTrade) {
            _burnSellTradeNFT(tradeId);
        }

        emit SellEscrowReleased(tradeId, fee);
        emit TradeCompleted(tradeId, fee);
    }

    /**
     * @dev Open dispute. Direct wallet call by seller or merchant.
     *      Pending status NOT disputable — no counterparty to dispute against.
     *      Seller in Pending exits via cancelSellTradePending() (full refund).
     */
    function openSellDispute(bytes32 tradeId) external nonReentrant whenNotPaused {
        Trade storage trade = trades[tradeId];
        require(trade.kind == TradeKind.Sell, "Not sell");
        require(
            trade.status == TradeStatus.EscrowLocked || trade.status == TradeStatus.PaymentSent,
            "Dispute requires merchant joined"
        );
        require(msg.sender == trade.seller || msg.sender == trade.merchant, "Not party");

        trade.status = TradeStatus.Disputed;
        emit DisputeOpened(tradeId, msg.sender);
    }

    /**
     * @dev Mediator Council resolves sell dispute. ADMIN_ROLE = multisig only.
     *      Spec S2.5: fee deducted in BOTH outcomes (convergence after decision).
     *      Winner receives `amount - fee`; fee always routes to feeWallet.
     */
    function resolveSellDispute(
        bytes32 tradeId,
        address winner
    ) external nonReentrant onlyRole(ADMIN_ROLE) {
        Trade storage trade = trades[tradeId];
        require(trade.kind == TradeKind.Sell, "Not sell");
        require(trade.status == TradeStatus.Disputed, "Trade not in dispute");
        require(
            winner == trade.seller || winner == trade.merchant,
            "Winner must be seller or merchant"
        );

        uint256 fee = (trade.amount * FEE_BPS) / BPS_DENOMINATOR;
        uint256 winnerAmount = trade.amount - fee;

        trade.status = TradeStatus.Resolved;

        if (winner == trade.merchant) {
            usdcToken.safeTransfer(trade.merchant, winnerAmount);
        } else {
            usdcToken.safeTransfer(trade.seller, winnerAmount);
        }

        if (fee > 0) usdcToken.safeTransfer(feeWallet, fee);
        if (trade.stakeAmount > 0) {
            usdcToken.safeTransfer(trade.stakePaidBy, trade.stakeAmount);
        }
        if (trade.isCashTrade) _burnSellTradeNFT(tradeId);

        emit DisputeResolved(tradeId, winner, winnerAmount);
    }

    /**
     * @dev Seller cancels before merchant joins. Refunds amount + fee + stake.
     */
    function cancelSellTradePending(bytes32 tradeId) external nonReentrant whenNotPaused {
        Trade storage trade = trades[tradeId];
        require(trade.kind == TradeKind.Sell, "Not sell");
        require(trade.status == TradeStatus.Pending, "Already joined");
        require(msg.sender == trade.seller, "Only seller");

        uint256 fee = (trade.amount * FEE_BPS) / BPS_DENOMINATOR;
        trade.status = TradeStatus.Cancelled;

        usdcToken.safeTransfer(trade.seller, trade.amount + fee);
        if (trade.stakeAmount > 0) {
            usdcToken.safeTransfer(trade.stakePaidBy, trade.stakeAmount);
        }
        if (trade.isCashTrade) _burnSellTradeNFT(tradeId);

        emit TradeCancelled(tradeId);
    }

    /**
     * @dev Permissionless expiry cleanup. Refunds seller fully.
     */
    function cancelExpiredSellTrade(bytes32 tradeId) external nonReentrant whenNotPaused {
        Trade storage trade = trades[tradeId];
        require(trade.kind == TradeKind.Sell, "Not sell");
        require(
            trade.status == TradeStatus.Pending || trade.status == TradeStatus.EscrowLocked,
            "Bad status"
        );
        require(block.timestamp > trade.expiresAt, "Not expired");

        uint256 fee = (trade.amount * FEE_BPS) / BPS_DENOMINATOR;
        trade.status = TradeStatus.Expired;

        usdcToken.safeTransfer(trade.seller, trade.amount + fee);
        if (trade.stakeAmount > 0) {
            usdcToken.safeTransfer(trade.stakePaidBy, trade.stakeAmount);
        }
        if (trade.isCashTrade) _burnSellTradeNFT(tradeId);

        emit ExpiredTradeCancelled(tradeId);
    }

    /**
     * @dev Internal NFT burn helper for sell trades.
     */
    function _burnSellTradeNFT(bytes32 tradeId) internal {
        uint256 tokenId = tradeNFT.tradeIdToTokenId(tradeId);
        if (tokenId != 0) {
            tradeNFT.burn(tokenId);
        }
    }

    // ─── Emergency ───

    function pause() external onlyRole(ADMIN_ROLE) {
        _pause();
    }

    function unpause() external onlyRole(ADMIN_ROLE) {
        _unpause();
    }
}
