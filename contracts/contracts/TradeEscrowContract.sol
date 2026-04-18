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
 *      and cash meeting NFT support. All critical logic on-chain.
 *
 * Roles:
 *   ADMIN_ROLE    — Gnosis 2-of-3 multisig (dispute resolution, pause, ownership)
 *   OPERATOR_ROLE — Backend server / gas wallet (submits txs on behalf of users)
 *
 * Fee: 0.2% (20 basis points) per trade, sent to feeWallet.
 * Stake: $5 USDC on public trades (returned after completion).
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
        Resolved
    }

    // ─── Structs ───
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
            expiresAt: expiresAt
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

    // ─── Emergency ───

    function pause() external onlyRole(ADMIN_ROLE) {
        _pause();
    }

    function unpause() external onlyRole(ADMIN_ROLE) {
        _unpause();
    }
}
