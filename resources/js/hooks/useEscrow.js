import { useState, useEffect, useCallback } from "react"
import { ethers } from "ethers"
import { useWallet } from "@/hooks/useWallet"
import { api } from "@/lib/api"
import { ERC20_ABI, useBlockchainConfig, toRawUsdc } from "@/lib/contracts"
import { toast } from "sonner"

export function useEscrow() {
  const { isAuthenticated, signer: walletSigner, phraseWallet, isCorrectChain } = useWallet()
  const { usdcAddress, escrowAddress, rpcUrl } = useBlockchainConfig()
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState(false)
  const [dashboardData, setDashboardData] = useState(null)
  const [trades, setTrades] = useState([])
  const [depositState, setDepositState] = useState("idle") // idle | approving | depositing | confirming | done
  const [withdrawState, setWithdrawState] = useState("idle") // idle | submitting | confirming | done

  const fetchData = useCallback(async () => {
    if (!isAuthenticated) return
    try {
      const [dashRes, tradesRes] = await Promise.all([
        api.getDashboard(),
        api.getMerchantTrades("status=escrow_locked&per_page=20"),
      ])
      setDashboardData(dashRes.data)
      setTrades(tradesRes.data?.data || tradesRes.data || [])
    } catch {
      setError(true)
      toast.error("Failed to load liquidity data")
    } finally {
      setIsLoading(false)
    }
  }, [isAuthenticated])

  useEffect(() => {
    fetchData()
  }, [fetchData])

  const escrowBalance = Number(dashboardData?.escrow_balance || 0)
  const lockedBalance = Number(dashboardData?.locked_balance || 0)
  const availableBalance = Math.max(escrowBalance - lockedBalance, 0)

  const retry = useCallback(() => {
    setError(false)
    setIsLoading(true)
    fetchData()
  }, [fetchData])

  const deposit = useCallback(async (amount) => {
    if (!amount || parseFloat(amount) <= 0) {
      toast.error("Enter a valid amount")
      return
    }

    if (!usdcAddress || !escrowAddress) {
      toast.error("Blockchain not configured")
      return
    }

    // Use the signer from the wallet context (supports both MetaMask and phrase wallets)
    const signer = phraseWallet || walletSigner
    if (!signer) {
      toast.error("Wallet not connected")
      return
    }

    if (!isCorrectChain && !phraseWallet) {
      toast.error("Switch to Base Sepolia in your wallet")
      return
    }

    const rawAmount = toRawUsdc(amount)

    // For phrase wallets, connect to the RPC provider directly
    const signerForTx = phraseWallet
      ? phraseWallet.connect(new ethers.providers.JsonRpcProvider(rpcUrl || "https://sepolia.base.org"))
      : signer

    const usdc = new ethers.Contract(usdcAddress, ERC20_ABI, signerForTx)

    try {
      // Check and set allowance
      const signerAddress = await signerForTx.getAddress()
      const allowance = await usdc.allowance(signerAddress, escrowAddress)

      if (allowance.lt(rawAmount.toString())) {
        setDepositState("approving")
        const approveTx = await usdc.approve(escrowAddress, rawAmount.toString())
        await approveTx.wait()
        // Small pause to let the allowance propagate
        await new Promise((r) => setTimeout(r, 1500))
      }

      // Submit deposit to backend
      setDepositState("depositing")
      const res = await api.depositEscrow(amount)
      const txHash = res.data?.tx_hash

      // Poll for confirmation
      setDepositState("confirming")
      let confirmed = false
      let failCount = 0
      for (let i = 0; i < 30; i++) {
        await new Promise((r) => setTimeout(r, 3000))
        try {
          const statusRes = await api.getEscrowTxStatus(txHash)
          failCount = 0
          if (statusRes.data?.status === "confirmed") { confirmed = true; break }
          if (statusRes.data?.status === "failed") throw new Error("Transaction failed on-chain")
        } catch (pollErr) {
          failCount++
          if (failCount >= 5) break
        }
      }

      setDepositState("done")
      toast.success(confirmed ? `Deposited ${amount} USDC` : "Transaction submitted — confirming in background")
      fetchData()
    } catch (err) {
      setDepositState("idle")
      toast.error(err?.message ?? "Deposit failed")
    }
  }, [usdcAddress, escrowAddress, walletSigner, phraseWallet, isCorrectChain, fetchData])

  const withdraw = useCallback(async (amount) => {
    if (!amount || parseFloat(amount) <= 0) {
      toast.error("Enter a valid amount")
      return
    }
    if (parseFloat(amount) > availableBalance) {
      toast.error("Insufficient available balance")
      return
    }

    try {
      setWithdrawState("submitting")
      const res = await api.withdrawEscrow(amount)
      const txHash = res.data?.tx_hash

      setWithdrawState("confirming")
      let wConfirmed = false
      let wFailCount = 0
      for (let i = 0; i < 30; i++) {
        await new Promise((r) => setTimeout(r, 3000))
        try {
          const statusRes = await api.getEscrowTxStatus(txHash)
          wFailCount = 0
          if (statusRes.data?.status === "confirmed") { wConfirmed = true; break }
          if (statusRes.data?.status === "failed") throw new Error("Transaction failed on-chain")
        } catch (pollErr) {
          wFailCount++
          if (wFailCount >= 5) break
        }
      }

      setWithdrawState("done")
      toast.success(wConfirmed ? `Withdrew ${amount} USDC` : "Transaction submitted — confirming in background")
      fetchData()
    } catch (err) {
      setWithdrawState("idle")
      toast.error(err?.message ?? "Withdrawal failed")
    }
  }, [availableBalance, fetchData])

  return {
    isLoading,
    error,
    retry,
    dashboardData,
    trades,
    escrowBalance,
    lockedBalance,
    availableBalance,
    depositState,
    withdrawState,
    deposit,
    withdraw,
    refresh: fetchData,
  }
}
