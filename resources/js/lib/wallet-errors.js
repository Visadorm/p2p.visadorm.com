export function humanizeWalletError(err) {
  if (!err) return "Action failed"

  const code = err.code
  const msg = String(err.message || err.reason || err.shortMessage || "").toLowerCase()
  const data = String(err.data?.message || "").toLowerCase()

  if (
    code === 4001 ||
    code === "ACTION_REJECTED" ||
    msg.includes("user rejected") ||
    msg.includes("user denied") ||
    msg.includes("rejected by user") ||
    msg.includes("user cancelled") ||
    msg.includes("user canceled")
  ) {
    return "Transaction cancelled"
  }

  if (msg.includes("insufficient funds") || msg.includes("insufficient balance")) {
    return "Insufficient funds for gas or USDC"
  }

  if (msg.includes("nonce") && msg.includes("low")) {
    return "Wallet nonce out of sync — refresh and retry"
  }

  if (code === "UNPREDICTABLE_GAS_LIMIT" || msg.includes("execution reverted")) {
    const reason = err.error?.reason || err.reason
    if (reason) return `Transaction would fail: ${reason}`
    return "Transaction would fail. Refresh the page and try again."
  }

  if (msg.includes("network") || msg.includes("timeout")) {
    return "Network error. Try again."
  }

  if (msg.includes("wrong network") || msg.includes("chain")) {
    return "Wrong network. Switch to Base Sepolia."
  }

  return "Could not complete transaction. Try again."
}
