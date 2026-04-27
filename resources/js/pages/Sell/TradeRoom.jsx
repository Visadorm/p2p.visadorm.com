import { useEffect, useState } from "react"
import { Link, router } from "@inertiajs/react"
import { ethers } from "ethers"
import { toast } from "sonner"
import PublicHeader from "@/components/PublicHeader"
import PublicFooter from "@/components/PublicFooter"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Switch } from "@/components/ui/switch"
import { Textarea } from "@/components/ui/textarea"
import { Input } from "@/components/ui/input"
import ConnectWallet from "@/components/ConnectWallet"
import { useWallet } from "@/hooks/useWallet"
import { api } from "@/lib/api"
import { ESCROW_SELL_ABI, useBlockchainConfig } from "@/lib/contracts"

export default function SellTradeRoom({ tradeHash }) {
  const { merchant: caller, signer, phraseWallet, isAuthenticated } = useWallet()
  const { escrowAddress } = useBlockchainConfig()
  const [trade, setTrade] = useState(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [disputeReason, setDisputeReason] = useState("")
  const [proofFile, setProofFile] = useState(null)

  const refresh = async () => {
    try {
      const res = await api.getSellTrade(tradeHash)
      setTrade(res.data)
    } catch (e) {
      toast.error(e?.message ?? "Failed to load trade")
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { refresh() }, [tradeHash])

  if (loading) return <Shell><div className="p-8">Loading…</div></Shell>
  if (!trade) return <Shell><div className="p-8">Trade not found.</div></Shell>
  if (!isAuthenticated) return <Shell><div className="p-8"><ConnectWallet /></div></Shell>

  const isSeller = trade.is_seller
  const isMerchant = trade.is_merchant
  const signerForTx = phraseWallet ?? signer
  const escrow = signerForTx ? new ethers.Contract(escrowAddress, ESCROW_SELL_ABI, signerForTx) : null

  async function send(action) {
    if (!escrow) { toast.error("Wallet not ready"); return }
    setBusy(true)
    try {
      await action()
      await refresh()
    } catch (e) {
      toast.error(e?.message ?? "Action failed")
    } finally {
      setBusy(false)
    }
  }

  // ─── Seller actions ───
  const cancelPending = () => send(async () => {
    const tx = await escrow.cancelSellTradePending(trade.trade_hash)
    await tx.wait()
    await api.cancelSellTrade(trade.trade_hash, { cancel_tx_hash: tx.hash })
    toast.success("Trade cancelled. Funds returned.")
  })

  const verifyPayment = (val) => send(async () => {
    await api.setSellVerifyPayment(trade.trade_hash, val)
  })

  const releaseEscrow = () => send(async () => {
    const tx = await escrow.releaseSellEscrow(trade.trade_hash)
    await tx.wait()
    await api.confirmSellRelease(trade.trade_hash, { release_tx_hash: tx.hash })
    toast.success("USDC released to merchant")
  })

  // ─── Merchant actions ───
  const joinTrade = () => send(async () => {
    const tx = await escrow.joinSellTrade(trade.trade_hash)
    await tx.wait()
    await api.confirmSellJoin(trade.trade_hash, { join_tx_hash: tx.hash })
    toast.success("Joined the trade")
  })

  const markPaid = () => send(async () => {
    const tx = await escrow.markSellPaymentSent(trade.trade_hash)
    await tx.wait()
    await api.confirmSellMarkPaid(trade.trade_hash, { mark_paid_tx_hash: tx.hash })
    toast.success("Marked as paid")
  })

  const uploadProof = () => send(async () => {
    if (!proofFile) { toast.error("Pick a file"); return }
    await api.uploadSellCashProof(trade.trade_hash, proofFile)
    toast.success("Proof uploaded")
  })

  // ─── Both ───
  const openDispute = () => send(async () => {
    if (disputeReason.trim().length < 10) { toast.error("Reason ≥ 10 chars"); return }
    const tx = await escrow.openSellDispute(trade.trade_hash)
    await tx.wait()
    await api.openSellTradeDispute(trade.trade_hash, {
      dispute_tx_hash: tx.hash,
      reason: disputeReason,
    })
    toast.success("Dispute opened")
  })

  return (
    <Shell>
      <main className="mx-auto w-full max-w-2xl flex-1 px-4 py-8 space-y-4">
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center justify-between">
              <span>Sell trade — {trade.amount_usdc} USDC</span>
              <StatusBadge status={trade.status} />
            </CardTitle>
            <p className="text-sm text-muted-foreground">
              {trade.is_cash_trade ? "Cash meeting" : "Online payment"} •
              Counterparty: {trade.merchant?.username}
            </p>
          </CardHeader>
          <CardContent className="space-y-3">
            <Row label="Trade hash" value={trade.trade_hash} mono />
            <Row label="You" value={isSeller ? "Seller (USDC owner)" : isMerchant ? "Merchant (fiat sender)" : "Observer"} />
            <Row label="Amount" value={`${trade.amount_usdc} USDC`} />
            <Row label="Fiat" value={`${trade.amount_fiat} ${trade.currency_code}`} />
            <Row label="Stake" value={`${trade.stake_amount ?? 0} USDC (refundable)`} />
            {trade.fund_tx_hash && <TxRow label="Fund tx" hash={trade.fund_tx_hash} />}
            {trade.join_tx_hash && <TxRow label="Join tx" hash={trade.join_tx_hash} />}
            {trade.mark_paid_tx_hash && <TxRow label="Mark-paid tx" hash={trade.mark_paid_tx_hash} />}
            {trade.release_tx_hash && <TxRow label="Release tx" hash={trade.release_tx_hash} />}
            {trade.dispute_tx_hash && <TxRow label="Dispute tx" hash={trade.dispute_tx_hash} />}
            {trade.cancel_tx_hash && <TxRow label="Cancel tx" hash={trade.cancel_tx_hash} />}
          </CardContent>
        </Card>

        {/* Seller actions */}
        {isSeller && (
          <Card>
            <CardHeader><CardTitle>Your actions (Seller)</CardTitle></CardHeader>
            <CardContent className="space-y-3">
              {trade.status === "pending" && (
                <>
                  <p className="text-sm text-muted-foreground">Waiting for {trade.merchant?.username} to accept this trade.</p>
                  <Button variant="outline" disabled={busy} onClick={cancelPending}>Cancel trade (full refund)</Button>
                </>
              )}
              {trade.status === "escrow_locked" && (
                <p className="text-sm text-muted-foreground">Merchant joined. Waiting for them to send {trade.amount_fiat} {trade.currency_code} via {trade.payment_method}.</p>
              )}
              {trade.status === "payment_sent" && (
                <>
                  <div className="flex items-center justify-between rounded-md border p-3">
                    <div>
                      <p className="text-sm font-medium">Confirm fiat received</p>
                      <p className="text-xs text-muted-foreground">Verify the payment in your bank/wallet before releasing.</p>
                    </div>
                    <Switch checked={!!trade.seller_verified_payment} onCheckedChange={verifyPayment} disabled={busy} />
                  </div>
                  <Button
                    size="lg"
                    className="w-full"
                    disabled={busy || !trade.seller_verified_payment}
                    onClick={releaseEscrow}
                  >
                    Release USDC ({trade.amount_usdc})
                  </Button>
                  <p className="text-xs text-muted-foreground">
                    You sign + pay gas. Once released, the trade is final and irreversible.
                  </p>
                </>
              )}
              {(trade.status === "escrow_locked" || trade.status === "payment_sent") && (
                <DisputeBlock disputeReason={disputeReason} setDisputeReason={setDisputeReason} onOpen={openDispute} busy={busy} />
              )}
              {trade.status === "completed" && <p className="text-sm text-emerald-500">Trade complete. USDC sent to merchant.</p>}
              {(trade.status === "cancelled" || trade.status === "expired") && (
                <p className="text-sm text-muted-foreground">Trade closed. Funds returned to your wallet.</p>
              )}
            </CardContent>
          </Card>
        )}

        {/* Merchant actions */}
        {isMerchant && (
          <Card>
            <CardHeader><CardTitle>Your actions (Merchant)</CardTitle></CardHeader>
            <CardContent className="space-y-3">
              {trade.status === "pending" && (
                <Button size="lg" className="w-full" disabled={busy} onClick={joinTrade}>Accept this trade</Button>
              )}
              {trade.status === "escrow_locked" && (
                <>
                  <div className="rounded-md border p-3">
                    <p className="text-sm font-medium">Send {trade.amount_fiat} {trade.currency_code} to seller</p>
                    <p className="text-xs text-muted-foreground">Via your {trade.payment_method} account. Use trade hash as reference.</p>
                  </div>
                  {trade.is_cash_trade && (
                    <div className="space-y-2 rounded-md border p-3">
                      <p className="text-sm font-medium">Upload cash proof</p>
                      <Input type="file" accept="image/*,application/pdf" onChange={(e) => setProofFile(e.target.files?.[0])} />
                      <Button size="sm" variant="outline" disabled={busy || !proofFile} onClick={uploadProof}>Upload</Button>
                    </div>
                  )}
                  <Button size="lg" className="w-full" disabled={busy} onClick={markPaid}>I've sent the fiat</Button>
                </>
              )}
              {trade.status === "payment_sent" && (
                <p className="text-sm text-muted-foreground">Waiting for seller to verify and release. They get final say.</p>
              )}
              {(trade.status === "escrow_locked" || trade.status === "payment_sent") && (
                <DisputeBlock disputeReason={disputeReason} setDisputeReason={setDisputeReason} onOpen={openDispute} busy={busy} />
              )}
              {trade.status === "completed" && <p className="text-sm text-emerald-500">Trade complete. USDC received.</p>}
            </CardContent>
          </Card>
        )}
      </main>
    </Shell>
  )
}

function Shell({ children }) {
  return (
    <div className="flex min-h-screen flex-col bg-background">
      <PublicHeader />
      {children}
      <PublicFooter />
    </div>
  )
}

function Row({ label, value, mono = false }) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
      <span className="text-muted-foreground">{label}</span>
      <span className={mono ? "font-mono text-xs" : ""}>{value}</span>
    </div>
  )
}

function TxRow({ label, hash }) {
  const url = `https://sepolia.basescan.org/tx/${hash}`
  return (
    <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
      <span className="text-muted-foreground">{label}</span>
      <a className="font-mono text-xs text-primary hover:underline" href={url} target="_blank" rel="noreferrer">{hash.slice(0, 10)}…{hash.slice(-8)}</a>
    </div>
  )
}

function StatusBadge({ status }) {
  const map = {
    pending: { label: "Funded", color: "bg-blue-500/15 text-blue-400" },
    escrow_locked: { label: "In progress", color: "bg-amber-500/15 text-amber-400" },
    payment_sent: { label: "Awaiting release", color: "bg-purple-500/15 text-purple-400" },
    completed: { label: "Completed", color: "bg-emerald-500/15 text-emerald-400" },
    disputed: { label: "Disputed", color: "bg-rose-500/15 text-rose-400" },
    cancelled: { label: "Cancelled", color: "bg-zinc-500/15 text-zinc-400" },
    expired: { label: "Expired", color: "bg-zinc-500/15 text-zinc-400" },
  }
  const m = map[status] ?? { label: status, color: "bg-zinc-500/15 text-zinc-400" }
  return <span className={`rounded-full px-2 py-0.5 text-xs ${m.color}`}>{m.label}</span>
}

function DisputeBlock({ disputeReason, setDisputeReason, onOpen, busy }) {
  return (
    <details className="rounded-md border p-3">
      <summary className="cursor-pointer text-sm font-medium">Open a dispute</summary>
      <div className="mt-3 space-y-2">
        <Textarea
          placeholder="Describe what went wrong (≥ 10 chars)…"
          value={disputeReason}
          onChange={(e) => setDisputeReason(e.target.value)}
          rows={3}
        />
        <Button variant="destructive" size="sm" disabled={busy || disputeReason.trim().length < 10} onClick={onOpen}>
          Open dispute (Mediator Council reviews)
        </Button>
      </div>
    </details>
  )
}
