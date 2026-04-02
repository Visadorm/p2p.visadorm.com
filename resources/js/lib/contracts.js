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
