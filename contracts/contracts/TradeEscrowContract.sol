// SPDX-License-Identifier: MIT
pragma solidity 0.8.27;

import "@openzeppelin/contracts/access/AccessControl.sol";
import "@openzeppelin/contracts/utils/Pausable.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC20/utils/SafeERC20.sol";
import "@openzeppelin/contracts/utils/cryptography/EIP712.sol";
import "@openzeppelin/contracts/utils/cryptography/ECDSA.sol";
import "./SoulboundTradeNFT.sol";

/**
 * @title TradeEscrowContract
 * @dev P2P USDC trading escrow with anti-spam stakes, dispute resolution,
 *      and cash meeting NFT support. All critical logic on-chain.
 *
 * Roles:
 *   ADMIN_ROLE    — Gnosis 2-of-3 multisig (dispute resolution, pause, ownership)
 *   OPERATOR_ROLE — Backend server / gas wallet (submits txs on behalf of users)
 *
 * Fee: 0.2% (20 basis points) per trade, sent to feeWallet.
 * Stake: $5 USDC on public trades (returned after completion).
 */
contract TradeEscrowContract is AccessControl, Pausable, ReentrancyGuard, EIP712 {
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
    // Terminal states (no further transitions allowed): Completed, Cancelled, Resolved.
    // Disputes are only permitted while funds are held in escrow (EscrowLocked or PaymentSent).
    // Once a trade reaches Completed, the contract permanently rejects any further action.
    enum TradeStatus {
        None,
        EscrowLocked,
        PaymentSent,
        Completed,
        Disputed,
        Cancelled,
        Resolved,
        SellFunded
    }

    enum TradeKind {
        Buy,
        Sell
    }

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
        address seller;
        TradeKind kind;
    }

    // ─── State ───
    mapping(bytes32 => Trade) public trades;
    mapping(address => uint256) public merchantEscrowBalance;
    mapping(address => uint256) public merchantLockedInTrades;
    mapping(address => uint256) public sellerNonce;

    bytes32 private constant SELL_RELEASE_TYPEHASH = keccak256(
        "ReleaseSellEscrow(bytes32 tradeId,uint256 nonce,uint256 deadline)"
    );

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

    event SellTradeFunded(
        bytes32 indexed tradeId,
        address indexed seller,
        uint256 amount,
        uint256 expiresAt,
        bool isPrivate
    );
    event SellTradeTaken(bytes32 indexed tradeId, address indexed buyer);
    event SellEscrowReleased(bytes32 indexed tradeId, uint256 fee, bool viaMetaTx);
    event SellOfferCancelled(bytes32 indexed tradeId, address indexed seller);
    event ExpiredTradeCancelled(bytes32 indexed tradeId);

    // ─── Constructor ───
    constructor(
        address _usdcToken,
        address _feeWallet,
        address _tradeNFT,
        address _admin,
        address _operator
    ) EIP712("VisadormP2P", "1") {
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

    // ─── Merchant Escrow ───

    /**
     * @dev Merchant deposits USDC into their escrow pool.
     */
    function depositEscrow(
        address merchant,
        uint256 amount
    ) external nonReentrant whenNotPaused onlyRole(OPERATOR_ROLE) {
        require(amount > 0, "Amount must be > 0");

        usdcToken.safeTransferFrom(merchant, address(this), amount);
        merchantEscrowBalance[merchant] += amount;

        emit EscrowDeposited(merchant, amount);
    }

    /**
     * @dev Merchant withdraws unlocked USDC from escrow.
     */
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

    /**
     * @dev Get merchant's available (unlocked) escrow balance.
     */
    function getAvailableBalance(address merchant) external view returns (uint256) {
        return merchantEscrowBalance[merchant] - merchantLockedInTrades[merchant];
    }

    // ─── Trade Lifecycle ───

    /**
     * @dev Initiate a trade. Locks merchant USDC (amount + fee). Buyer stakes $5 on public trades.
     *      The 0.2% fee is charged to the merchant, not the buyer.
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
            seller: merchant,
            kind: TradeKind.Buy
        });

        emit TradeCreated(tradeId, merchant, buyer, amount, isPrivate);
    }

    /**
     * @dev Buyer marks fiat payment as sent.
     */
    function markPaymentSent(
        bytes32 tradeId
    ) external nonReentrant whenNotPaused onlyRole(OPERATOR_ROLE) {
        Trade storage trade = trades[tradeId];
        require(trade.status == TradeStatus.EscrowLocked, "Invalid trade status");

        trade.status = TradeStatus.PaymentSent;

        emit PaymentMarkedSent(tradeId);
    }

    /**
     * @dev Merchant confirms fiat received. Releases full USDC amount to buyer.
     *      The 0.2% fee is deducted from merchant escrow (already locked at trade initiation).
     */
    function confirmPayment(
        bytes32 tradeId
    ) external nonReentrant whenNotPaused onlyRole(OPERATOR_ROLE) {
        Trade storage trade = trades[tradeId];
        require(
            trade.status == TradeStatus.PaymentSent || trade.status == TradeStatus.EscrowLocked,
            "Invalid trade status"
        );

        uint256 fee = (trade.amount * FEE_BPS) / BPS_DENOMINATOR;
        uint256 total = trade.amount + fee;

        trade.status = TradeStatus.Completed;
        merchantLockedInTrades[trade.merchant] -= total;
        merchantEscrowBalance[trade.merchant] -= total;

        // Transfer full USDC amount to buyer (merchant absorbs the fee)
        usdcToken.safeTransfer(trade.buyer, trade.amount);

        // Transfer fee to platform
        if (fee > 0) {
            usdcToken.safeTransfer(feeWallet, fee);
        }

        // Return stake
        if (trade.stakeAmount > 0) {
            usdcToken.safeTransfer(trade.stakePaidBy, trade.stakeAmount);
        }

        emit TradeCompleted(tradeId, fee);
    }

    /**
     * @dev Cancel a trade before payment is confirmed. Returns stake, unlocks amount + fee.
     */
    function cancelTrade(
        bytes32 tradeId
    ) external nonReentrant whenNotPaused onlyRole(OPERATOR_ROLE) {
        Trade storage trade = trades[tradeId];
        require(
            trade.status == TradeStatus.EscrowLocked,
            "Can only cancel before payment is sent"
        );

        uint256 fee = (trade.amount * FEE_BPS) / BPS_DENOMINATOR;
        uint256 total = trade.amount + fee;

        trade.status = TradeStatus.Cancelled;
        merchantLockedInTrades[trade.merchant] -= total;

        // Forfeit stake to fee wallet (anti-spam penalty)
        if (trade.stakeAmount > 0) {
            usdcToken.safeTransfer(feeWallet, trade.stakeAmount);
        }

        emit TradeCancelled(tradeId);
    }

    // ─── Disputes ───

    /**
     * @dev Open a dispute on a trade. State-based enforcement:
     *      Disputes are only permitted while USDC is still held in escrow
     *      (EscrowLocked or PaymentSent). Once a trade is Completed, Cancelled,
     *      or Resolved, it is terminal and this function reverts. There is no
     *      post-completion dispute window — finality is on-chain and immediate.
     */
    function openDispute(
        bytes32 tradeId,
        address openedBy
    ) external nonReentrant whenNotPaused onlyRole(OPERATOR_ROLE) {
        Trade storage trade = trades[tradeId];
        require(
            trade.status == TradeStatus.EscrowLocked || trade.status == TradeStatus.PaymentSent,
            "Invalid trade status"
        );
        require(
            openedBy == trade.merchant || openedBy == trade.buyer,
            "Not a party to this trade"
        );

        trade.status = TradeStatus.Disputed;

        emit DisputeOpened(tradeId, openedBy);
    }

    /**
     * @dev Resolve a dispute. ADMIN_ROLE only (multisig).
     *      Winner receives the full trade amount; fee comes from merchant escrow.
     */
    function resolveDispute(
        bytes32 tradeId,
        address winner
    ) external nonReentrant onlyRole(ADMIN_ROLE) {
        Trade storage trade = trades[tradeId];
        require(trade.status == TradeStatus.Disputed, "Trade not in dispute");
        require(
            winner == trade.merchant || winner == trade.buyer,
            "Winner must be merchant or buyer"
        );

        uint256 fee = (trade.amount * FEE_BPS) / BPS_DENOMINATOR;
        uint256 total = trade.amount + fee;

        trade.status = TradeStatus.Resolved;
        merchantLockedInTrades[trade.merchant] -= total;

        if (winner == trade.merchant) {
            // Merchant wins: unlock escrow fully, no fee charged
            // (amount + fee stays in merchantEscrowBalance, just unlocked)
        } else {
            // Buyer wins: deduct from merchant escrow, send to buyer + fee to platform
            merchantEscrowBalance[trade.merchant] -= total;
            usdcToken.safeTransfer(winner, trade.amount);

            if (fee > 0) {
                usdcToken.safeTransfer(feeWallet, fee);
            }
        }

        // Return stake
        if (trade.stakeAmount > 0) {
            usdcToken.safeTransfer(trade.stakePaidBy, trade.stakeAmount);
        }

        emit DisputeResolved(tradeId, winner, trade.amount);
    }

    // ─── Cash Meeting NFT ───

    /**
     * @dev Mint a soulbound NFT for a cash meeting trade.
     */
    function mintTradeNFT(
        bytes32 tradeId,
        string calldata meetingLocation
    ) external whenNotPaused onlyRole(OPERATOR_ROLE) {
        Trade storage trade = trades[tradeId];
        require(trade.status == TradeStatus.EscrowLocked, "Invalid trade status");

        tradeNFT.mint(trade.buyer, tradeId, trade.merchant, trade.amount, meetingLocation);
    }

    /**
     * @dev Burn NFT after trade completion or cancellation.
     */
    function burnTradeNFT(bytes32 tradeId) external onlyRole(OPERATOR_ROLE) {
        uint256 tokenId = tradeNFT.tradeIdToTokenId(tradeId);
        require(tokenId != 0, "No NFT for trade");

        tradeNFT.burn(tokenId);
    }

    // ─── Sell Flow (seller-direct authority) ───

    function fundSellTrade(
        bytes32 tradeId,
        uint256 amount,
        bool isPrivate,
        uint256 expiresAt
    ) external nonReentrant whenNotPaused {
        require(trades[tradeId].status == TradeStatus.None, "Trade already exists");
        require(amount >= MIN_TRADE_AMOUNT, "Below minimum trade amount");
        require(expiresAt > block.timestamp, "Expiry must be in future");

        usdcToken.safeTransferFrom(msg.sender, address(this), amount);

        trades[tradeId] = Trade({
            merchant: address(0),
            buyer: address(0),
            amount: amount,
            stakeAmount: 0,
            stakePaidBy: address(0),
            status: TradeStatus.SellFunded,
            isPrivate: isPrivate,
            createdAt: block.timestamp,
            expiresAt: expiresAt,
            seller: msg.sender,
            kind: TradeKind.Sell
        });

        emit SellTradeFunded(tradeId, msg.sender, amount, expiresAt, isPrivate);
    }

    function takeSellTrade(bytes32 tradeId) external nonReentrant whenNotPaused {
        Trade storage trade = trades[tradeId];
        require(trade.kind == TradeKind.Sell, "Not a sell trade");
        require(trade.status == TradeStatus.SellFunded, "Offer not available");
        require(block.timestamp <= trade.expiresAt, "Offer expired");
        require(msg.sender != trade.seller, "Seller cannot take own offer");

        if (!trade.isPrivate) {
            usdcToken.safeTransferFrom(msg.sender, address(this), STAKE_AMOUNT);
            trade.stakeAmount = STAKE_AMOUNT;
            trade.stakePaidBy = msg.sender;
        }

        trade.buyer = msg.sender;
        trade.status = TradeStatus.EscrowLocked;

        emit SellTradeTaken(tradeId, msg.sender);
    }

    function markSellPaymentSent(bytes32 tradeId) external whenNotPaused {
        Trade storage trade = trades[tradeId];
        require(trade.kind == TradeKind.Sell, "Not a sell trade");
        require(trade.status == TradeStatus.EscrowLocked, "Invalid trade status");
        require(msg.sender == trade.buyer, "Only buyer can mark payment sent");

        trade.status = TradeStatus.PaymentSent;

        emit PaymentMarkedSent(tradeId);
    }

    function releaseSellEscrow(bytes32 tradeId) external nonReentrant whenNotPaused {
        Trade storage trade = trades[tradeId];
        require(trade.kind == TradeKind.Sell, "Not a sell trade");
        require(msg.sender == trade.seller, "Only seller can release");
        _settleSellRelease(trade, tradeId, false);
    }

    function executeMetaSellRelease(
        bytes32 tradeId,
        uint256 nonce,
        uint256 deadline,
        bytes calldata sellerSignature
    ) external nonReentrant whenNotPaused onlyRole(OPERATOR_ROLE) {
        require(block.timestamp <= deadline, "Signature expired");

        Trade storage trade = trades[tradeId];
        require(trade.kind == TradeKind.Sell, "Not a sell trade");
        require(nonce == sellerNonce[trade.seller], "Invalid nonce");

        bytes32 structHash = keccak256(abi.encode(SELL_RELEASE_TYPEHASH, tradeId, nonce, deadline));
        bytes32 digest = _hashTypedDataV4(structHash);
        address recovered = ECDSA.recover(digest, sellerSignature);
        require(recovered == trade.seller, "Bad seller signature");

        sellerNonce[trade.seller] = nonce + 1;

        _settleSellRelease(trade, tradeId, true);
    }

    function openSellDispute(bytes32 tradeId) external nonReentrant whenNotPaused {
        Trade storage trade = trades[tradeId];
        require(trade.kind == TradeKind.Sell, "Not a sell trade");
        require(
            trade.status == TradeStatus.EscrowLocked || trade.status == TradeStatus.PaymentSent,
            "Invalid trade status"
        );
        require(
            msg.sender == trade.seller || msg.sender == trade.buyer,
            "Not a party to this trade"
        );

        trade.status = TradeStatus.Disputed;
        emit DisputeOpened(tradeId, msg.sender);
    }

    function cancelSellOffer(bytes32 tradeId) external nonReentrant whenNotPaused {
        Trade storage trade = trades[tradeId];
        require(trade.kind == TradeKind.Sell, "Not a sell trade");
        require(trade.status == TradeStatus.SellFunded, "Offer no longer cancellable by seller");
        require(msg.sender == trade.seller, "Only seller can cancel own offer");

        trade.status = TradeStatus.Cancelled;
        usdcToken.safeTransfer(trade.seller, trade.amount);

        emit SellOfferCancelled(tradeId, trade.seller);
    }

    function cancelExpiredSellTrade(bytes32 tradeId) external nonReentrant whenNotPaused {
        Trade storage trade = trades[tradeId];
        require(trade.kind == TradeKind.Sell, "Not a sell trade");
        require(
            trade.status == TradeStatus.SellFunded || trade.status == TradeStatus.EscrowLocked,
            "Trade not eligible for expiry cancel"
        );
        require(block.timestamp > trade.expiresAt, "Trade not expired");

        trade.status = TradeStatus.Cancelled;
        usdcToken.safeTransfer(trade.seller, trade.amount);

        if (trade.stakeAmount > 0) {
            usdcToken.safeTransfer(trade.stakePaidBy, trade.stakeAmount);
        }

        emit ExpiredTradeCancelled(tradeId);
    }

    function resolveSellDispute(
        bytes32 tradeId,
        address winner
    ) external nonReentrant onlyRole(ADMIN_ROLE) {
        Trade storage trade = trades[tradeId];
        require(trade.kind == TradeKind.Sell, "Not a sell trade");
        require(trade.status == TradeStatus.Disputed, "Trade not in dispute");
        require(winner == trade.seller || winner == trade.buyer, "Winner must be seller or buyer");

        uint256 fee = (trade.amount * FEE_BPS) / BPS_DENOMINATOR;
        uint256 winnerAmount = trade.amount - fee;

        trade.status = TradeStatus.Resolved;

        usdcToken.safeTransfer(winner, winnerAmount);

        if (fee > 0) {
            usdcToken.safeTransfer(feeWallet, fee);
        }

        if (trade.stakeAmount > 0) {
            usdcToken.safeTransfer(trade.stakePaidBy, trade.stakeAmount);
        }

        emit DisputeResolved(tradeId, winner, winnerAmount);
    }

    function _settleSellRelease(Trade storage trade, bytes32 tradeId, bool viaMetaTx) private {
        require(
            trade.status == TradeStatus.EscrowLocked || trade.status == TradeStatus.PaymentSent,
            "Invalid trade status"
        );

        uint256 fee = (trade.amount * FEE_BPS) / BPS_DENOMINATOR;
        uint256 buyerAmount = trade.amount - fee;

        trade.status = TradeStatus.Completed;

        usdcToken.safeTransfer(trade.buyer, buyerAmount);

        if (fee > 0) {
            usdcToken.safeTransfer(feeWallet, fee);
        }

        if (trade.stakeAmount > 0) {
            usdcToken.safeTransfer(trade.stakePaidBy, trade.stakeAmount);
        }

        emit SellEscrowReleased(tradeId, fee, viaMetaTx);
        emit TradeCompleted(tradeId, fee);
    }

    // ─── Emergency ───

    function pause() external onlyRole(ADMIN_ROLE) {
        _pause();
    }

    function unpause() external onlyRole(ADMIN_ROLE) {
        _unpause();
    }
}
