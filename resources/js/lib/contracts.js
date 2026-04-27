// resources/js/lib/contracts.js
import { usePage } from '@inertiajs/react'

export const STAKE_AMOUNT = "5000000" // $5 USDC with 6 decimals

export const ERC20_ABI = [
  "function approve(address spender, uint256 amount) returns (bool)",
  "function allowance(address owner, address spender) view returns (uint256)",
  "function balanceOf(address account) view returns (uint256)",
]

/**
 * Get blockchain config from Inertia shared props.
 * Returns { usdc_address, trade_escrow_address, chain_id } or empty strings.
 */
export function useBlockchainConfig() {
  const { blockchain } = usePage().props
  return {
    usdcAddress:    blockchain?.usdc_address          ?? "",
    escrowAddress:  blockchain?.trade_escrow_address  ?? "",
    chainId:        blockchain?.chain_id               ?? 0,
    rpcUrl:         blockchain?.rpc_url                ?? "",
    network:        blockchain?.network                ?? "",
  }
}

/**
 * Convert a human-readable USDC amount (e.g. "100.5") to raw units (6 decimals).
 * Returns a BigInt.
 */
export function toRawUsdc(humanAmount) {
  const parts = String(humanAmount).split(".")
  const whole = BigInt(parts[0] || "0")
  const decimals = (parts[1] || "").padEnd(6, "0").slice(0, 6)
  return whole * 1_000_000n + BigInt(decimals)
}

/**
 * Sell flow ABI — every function callable directly from a user wallet.
 * No operator role on any sell function. Backend never broadcasts these.
 */
export const ESCROW_SELL_ABI = [
  "function openSellTrade(bytes32 tradeId, address merchant, uint256 amount, uint256 expiresAt, bool requireStake, bool isCashTrade, string meetingLocation)",
  "function joinSellTrade(bytes32 tradeId)",
  "function markSellPaymentSent(bytes32 tradeId)",
  "function releaseSellEscrow(bytes32 tradeId)",
  "function openSellDispute(bytes32 tradeId)",
  "function cancelSellTradePending(bytes32 tradeId)",
  "function cancelExpiredSellTrade(bytes32 tradeId)",
  "event SellTradeOpened(bytes32 indexed tradeId, address indexed seller, address indexed merchant, uint256 amount)",
  "event SellTradeJoined(bytes32 indexed tradeId, address indexed merchant)",
  "event SellPaymentMarked(bytes32 indexed tradeId)",
  "event SellEscrowReleased(bytes32 indexed tradeId, uint256 fee)",
  "event DisputeOpened(bytes32 indexed tradeId, address indexed openedBy)",
  "event TradeCancelled(bytes32 indexed tradeId)",
]

/**
 * Compute total USDC the seller must approve before openSellTrade:
 * amount + fee + (optional) stake.
 */
export function computeSellApproveAmount(amountUsdc, { feeBps = 20, stakeUsdc = 5, requireStake = true } = {}) {
  const amt = toRawUsdc(amountUsdc)
  const fee = (amt * BigInt(feeBps)) / 10000n
  const stake = requireStake ? toRawUsdc(stakeUsdc) : 0n
  return (amt + fee + stake).toString()
}
