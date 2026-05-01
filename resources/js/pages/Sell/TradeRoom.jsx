import { useCallback, useEffect, useRef, useState } from "react"
import { useTradeChannel } from "@/hooks/use-trade-channel"
import { Link, router } from "@inertiajs/react"
import { ethers } from "ethers"
import { toast } from "sonner"
import PublicHeader from "@/components/PublicHeader"
import PublicFooter from "@/components/PublicFooter"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { LoadingButton } from "@/components/ui/loading-button"
import { Textarea } from "@/components/ui/textarea"
import { Input } from "@/components/ui/input"
import { Skeleton } from "@/components/ui/skeleton"
import { SpinnerIcon, CopyIcon, MapPinIcon, CertificateIcon, PaperclipIcon, PaperPlaneTiltIcon, FileTextIcon, ImageIcon, XIcon, SpeakerHighIcon, SpeakerSlashIcon, CheckIcon } from "@phosphor-icons/react"
import NFTQRCode from "@/components/NFTQRCode"
import ConnectWallet from "@/components/ConnectWallet"
import { useWallet } from "@/hooks/useWallet"
import { api } from "@/lib/api"
import { ESCROW_SELL_ABI, useBlockchainConfig } from "@/lib/contracts"
import { humanizeWalletError } from "@/lib/wallet-errors"
import { Fancybox } from "@fancyapps/ui"
import "@fancyapps/ui/dist/fancybox/fancybox.css"
import { playChatChime, flashTabTitle, isChatMuted, setChatMuted } from "@/lib/chat-notify"

export default function SellTradeRoom({ tradeHash }) {
  const { merchant: caller, signer, phraseWallet, isAuthenticated } = useWallet()
  const { escrowAddress, nftAddress } = useBlockchainConfig()
  const [trade, setTrade] = useState(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [disputeReason, setDisputeReason] = useState("")
  const [proofFile, setProofFile] = useState(null)

  const refresh = useCallback(async () => {
    try {
      const res = await api.getSellTrade(tradeHash)
      setTrade(res.data)
    } catch (e) {
      toast.error(e?.message ?? "Failed to load trade")
    } finally {
      setLoading(false)
    }
  }, [tradeHash])

  useEffect(() => { refresh() }, [refresh])

  useTradeChannel(tradeHash, refresh, { enabled: !loading })

  if (loading) {
    return (
      <Shell>
        <main className="mx-auto w-full max-w-2xl flex-1 px-4 py-8 space-y-4">
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <Skeleton className="h-6 w-64" />
                <Skeleton className="h-5 w-20 rounded-full" />
              </div>
              <Skeleton className="mt-2 h-3 w-48" />
            </CardHeader>
            <CardContent>
              <div className="flex flex-col items-center justify-center gap-3 py-12 text-muted-foreground">
                <SpinnerIcon size={32} className="animate-spin" />
                <p className="text-sm">Loading trade…</p>
              </div>
            </CardContent>
          </Card>
        </main>
      </Shell>
    )
  }

  if (!trade) {
    return (
      <Shell>
        <main className="mx-auto w-full max-w-2xl flex-1 px-4 py-8">
          <Card>
            <CardContent className="py-16 text-center">
              <p className="text-lg font-semibold">Trade not found</p>
              <p className="mt-2 text-sm text-muted-foreground">This sell trade doesn't exist or has been removed.</p>
            </CardContent>
          </Card>
        </main>
      </Shell>
    )
  }

  if (!isAuthenticated) {
    return (
      <Shell>
        <main className="mx-auto w-full max-w-2xl flex-1 px-4 py-8">
          <Card>
            <CardHeader>
              <CardTitle>Connect your wallet</CardTitle>
            </CardHeader>
            <CardContent>
              <ConnectWallet />
            </CardContent>
          </Card>
        </main>
      </Shell>
    )
  }

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
      toast.error(humanizeWalletError(e))
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

  // A2: combined verify + release. Confirmation is the wallet signature itself.
  const releaseEscrow = () => send(async () => {
    if (!trade.seller_verified_payment) {
      await api.setSellVerifyPayment(trade.trade_hash, true)
    }
    const tx = await escrow.releaseSellEscrow(trade.trade_hash)
    await tx.wait()
    await api.confirmSellRelease(trade.trade_hash, { release_tx_hash: tx.hash })
    toast.success("USDC released to buyer")
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

  // A4: buyer uploads fiat payment proof.
  const uploadPaymentProof = () => send(async () => {
    if (!proofFile) { toast.error("Pick a file"); return }
    await api.uploadSellPaymentProof(trade.trade_hash, proofFile)
    setProofFile(null)
    toast.success("Payment proof uploaded. Seller has been notified.")
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

  const buyerCancelPrePayment = () => send(async () => {
    const ok = window.confirm(
      "Cancel this trade? Seller will be refunded in full. " +
      "Use only if you cannot complete the fiat payment."
    )
    if (!ok) return
    const tx = await escrow.cancelSellTradeByBuyer(trade.trade_hash)
    await tx.wait()
    await api.cancelSellTrade(trade.trade_hash, { cancel_tx_hash: tx.hash })
    toast.success("Trade cancelled. Seller refunded.")
  })

  return (
    <Shell>
      <main className="mx-auto w-full max-w-2xl flex-1 px-4 py-8 space-y-4">
        {/* A9: countdown — only ticks while timer-active (Pending or EscrowLocked) */}
        {(trade.status === "pending" || trade.status === "escrow_locked") && trade.expires_at && (
          <ExpiryCountdown trade={trade} />
        )}

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
            <CopyableRow label="Trade hash" value={trade.trade_hash} />
            <Row label="You" value={isSeller ? "You sell USDC" : isMerchant ? "You buy USDC (send fiat)" : "Observer"} />
            <Row label="Amount" value={`${trade.amount_usdc} USDC`} />
            <Row label="Fiat" value={`${trade.amount_fiat} ${trade.currency_code}`} />
            <Row label="Payment method" value={trade.payment_method_label || trade.payment_method} />
            <Row label="Stake" value={`${trade.stake_amount ?? 0} USDC (refundable)`} />
            {trade.is_cash_trade && trade.meeting_location && (
              <div className="flex items-start gap-2 rounded-md border border-amber-500/20 bg-amber-500/5 p-3">
                <MapPinIcon weight="fill" size={18} className="mt-0.5 shrink-0 text-amber-500" />
                <div className="text-sm">
                  <p className="font-medium">Meeting location</p>
                  <p className="text-muted-foreground">{trade.meeting_location}</p>
                </div>
              </div>
            )}
            {trade.is_cash_trade && trade.nft_token_id && (
              <div className="flex items-start gap-2 rounded-md border border-primary/20 bg-primary/5 p-3">
                <CertificateIcon weight="fill" size={18} className="mt-0.5 shrink-0 text-primary" />
                <div className="text-sm">
                  <p className="font-medium">Soulbound Trade NFT minted</p>
                  <a
                    href={`https://sepolia.basescan.org/token/${nftAddress}?a=${trade.nft_token_id}`}
                    target="_blank"
                    rel="noreferrer"
                    className="font-mono text-xs text-primary hover:underline"
                  >
                    {trade.trade_hash.slice(0, 10)}…{trade.trade_hash.slice(-8)} ↗
                  </a>
                </div>
              </div>
            )}
            {trade.fund_tx_hash && <TxRow label="Fund tx" hash={trade.fund_tx_hash} />}
            {trade.join_tx_hash && <TxRow label="Join tx" hash={trade.join_tx_hash} />}
            {trade.mark_paid_tx_hash && <TxRow label="Mark-paid tx" hash={trade.mark_paid_tx_hash} />}
            {trade.release_tx_hash && <TxRow label="Release tx" hash={trade.release_tx_hash} />}
            {trade.dispute_tx_hash && <TxRow label="Dispute tx" hash={trade.dispute_tx_hash} />}
            {trade.cancel_tx_hash && <TxRow label="Cancel tx" hash={trade.cancel_tx_hash} />}
          </CardContent>
        </Card>

        {/* Cash trade QR — visible to both parties for in-person scan.
            Encodes URL back to this same sell trade room (works for either party). */}
        {trade.is_cash_trade && (trade.status === "pending" || trade.status === "escrow_locked" || trade.status === "payment_sent") && (
          <NFTQRCode
            tradeHash={trade.trade_hash}
            tokenId={trade.nft_token_id}
            amountUsdc={trade.amount_usdc}
            verifyUrl={`${typeof window !== "undefined" ? window.location.origin : ""}/sell/trade/${trade.trade_hash}`}
          />
        )}

        {/* Seller actions */}
        {isSeller && (
          <Card>
            <CardHeader><CardTitle>Your actions (Seller)</CardTitle></CardHeader>
            <CardContent className="space-y-3">
              {trade.status === "pending" && (
                <>
                  <p className="text-sm text-muted-foreground">
                    Waiting for buyer to join. Buyer has been notified.
                  </p>
                  <LoadingButton variant="outline" loading={busy} loadingText="Cancelling…" onClick={cancelPending}>
                    Cancel trade (full refund)
                  </LoadingButton>
                </>
              )}
              {trade.status === "escrow_locked" && (
                <>
                  <p className="text-sm text-muted-foreground">
                    Waiting for buyer payment of {trade.amount_fiat} {trade.currency_code} via {trade.payment_method_label || trade.payment_method}.
                  </p>
                  {trade.has_payment_proof && (
                    <PaymentProofViewer trade={trade} />
                  )}
                </>
              )}
              {trade.status === "payment_sent" && (
                <>
                  <div className="rounded-md border border-amber-500/30 bg-amber-500/5 p-3">
                    <p className="text-sm font-medium text-amber-400">
                      {trade.is_cash_trade ? "Buyer confirmed cash payment" : "Buyer marked as paid"}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                      {trade.is_cash_trade
                        ? "Confirm you received the cash from the buyer in person, then release USDC. The wallet signature is your final confirmation."
                        : "Verify the fiat landed in your account before releasing USDC. The wallet signature is your final confirmation."}
                    </p>
                  </div>
                  {trade.is_cash_trade && trade.has_cash_proof && (
                    <CashProofViewer trade={trade} />
                  )}
                  {!trade.is_cash_trade && trade.has_payment_proof && (
                    <PaymentProofViewer trade={trade} />
                  )}
                  <LoadingButton
                    size="lg"
                    className="w-full"
                    loading={busy}
                    loadingText="Releasing…"
                    onClick={releaseEscrow}
                  >
                    Confirm & Release USDC ({trade.amount_usdc})
                  </LoadingButton>
                  <p className="text-xs text-muted-foreground">
                    You sign + pay gas. Once released, the trade is final and irreversible.
                  </p>
                </>
              )}
              {(trade.status === "escrow_locked" || trade.status === "payment_sent") && (
                <DisputeBlock disputeReason={disputeReason} setDisputeReason={setDisputeReason} onOpen={openDispute} busy={busy} />
              )}
              {trade.status === "completed" && <p className="text-sm text-emerald-500">Trade complete. USDC sent to buyer.</p>}
              {(trade.status === "cancelled" || trade.status === "expired") && (
                <p className="text-sm text-muted-foreground">Trade closed. Funds returned to your wallet.</p>
              )}
              {trade.status === "disputed" && (
                <div className="rounded-md border border-rose-500/20 bg-rose-500/5 p-3 text-sm">
                  <p className="font-medium text-rose-400">Dispute under review</p>
                  <p className="mt-1 text-muted-foreground">
                    Mediator Council is reviewing this dispute. Funds remain locked in escrow.
                    You will be notified when the multisig resolves it.
                  </p>
                </div>
              )}
              {trade.status === "resolved" && (
                <p className="text-sm text-muted-foreground">
                  Dispute resolved by Mediator Council. Funds distributed on-chain. View Resolve tx for details.
                </p>
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
                <LoadingButton size="lg" className="w-full" loading={busy} loadingText="Joining…" onClick={joinTrade}>Join Trade</LoadingButton>
              )}
              {trade.status === "escrow_locked" && (
                <>
                  <div className="rounded-md border p-3">
                    <p className="text-sm font-medium">Send {trade.amount_fiat} {trade.currency_code} to seller</p>
                    <p className="text-xs text-muted-foreground">
                      Via {trade.payment_method_label || trade.payment_method}. Use trade hash as reference.
                    </p>
                  </div>
                  <PaymentInstructions trade={trade} />
                  {trade.is_cash_trade && (
                    <div className="space-y-2 rounded-md border p-3">
                      <p className="text-sm font-medium">Upload cash proof (optional)</p>
                      <p className="text-xs text-muted-foreground">Photo of cash exchange, signed receipt, or QR scan record.</p>
                      <Input type="file" accept="image/*,application/pdf" onChange={(e) => setProofFile(e.target.files?.[0])} />
                      <LoadingButton size="sm" variant="outline" loading={busy} loadingText="Uploading…" disabled={!proofFile} onClick={uploadProof}>Upload</LoadingButton>
                    </div>
                  )}
                  {!trade.is_cash_trade && (
                    <PaymentProofUploader
                      trade={trade}
                      proofFile={proofFile}
                      setProofFile={setProofFile}
                      busy={busy}
                      onUpload={uploadPaymentProof}
                    />
                  )}
                  <LoadingButton size="lg" className="w-full" loading={busy} loadingText={trade.is_cash_trade ? "Confirming…" : "Marking paid…"} onClick={markPaid}>
                    {trade.is_cash_trade ? "I paid seller in cash" : "I Paid"}
                  </LoadingButton>
                  <LoadingButton
                    variant="outline"
                    className="w-full"
                    loading={busy}
                    loadingText="Cancelling…"
                    onClick={buyerCancelPrePayment}
                  >
                    Cancel trade (request mediator review)
                  </LoadingButton>
                </>
              )}
              {trade.status === "payment_sent" && (
                <>
                  <p className="text-sm text-muted-foreground">Waiting for seller to verify and release. They have final say.</p>
                  {!trade.is_cash_trade && (
                    <PaymentProofUploader
                      trade={trade}
                      proofFile={proofFile}
                      setProofFile={setProofFile}
                      busy={busy}
                      onUpload={uploadPaymentProof}
                    />
                  )}
                </>
              )}
              {(trade.status === "escrow_locked" || trade.status === "payment_sent") && (
                <DisputeBlock disputeReason={disputeReason} setDisputeReason={setDisputeReason} onOpen={openDispute} busy={busy} />
              )}
              {trade.status === "completed" && <p className="text-sm text-emerald-500">Trade complete. USDC received.</p>}
              {(trade.status === "cancelled" || trade.status === "expired") && (
                <p className="text-sm text-muted-foreground">
                  Trade closed before completion. No fiat was due — escrow returned to seller.
                </p>
              )}
              {trade.status === "disputed" && (
                <div className="rounded-md border border-rose-500/20 bg-rose-500/5 p-3 text-sm">
                  <p className="font-medium text-rose-400">Dispute under review</p>
                  <p className="mt-1 text-muted-foreground">
                    Mediator Council is reviewing this dispute. Funds remain locked in escrow.
                    You will be notified when the multisig resolves it.
                  </p>
                </div>
              )}
              {trade.status === "resolved" && (
                <p className="text-sm text-muted-foreground">
                  Dispute resolved by Mediator Council. View Resolve tx on BaseScan for outcome.
                </p>
              )}
            </CardContent>
          </Card>
        )}

        {(isSeller || isMerchant) && (
          <TradeChat trade={trade} myRole={isSeller ? "seller" : "buyer"} />
        )}
      </main>
    </Shell>
  )
}

// A6: render full seller payment instructions to the buyer (bank/online/cash).
function PaymentInstructions({ trade }) {
  const details = trade.payment_method_details
  const type = trade.payment_method_type
  const provider = trade.payment_method_provider
  const safetyNote = trade.payment_method_safety_note
  const location = trade.payment_method_location

  const hasContent = details || provider || safetyNote || location || type === "cash_meeting"
  if (!hasContent) return null

  const copy = (val, label) => {
    if (!val) return
    navigator.clipboard?.writeText(String(val)).then(
      () => toast.success(`${label} copied`),
      () => toast.error("Copy failed")
    )
  }

  // Pretty key labels: account_number → "Account number"
  const labelize = (key) => key.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase())

  return (
    <div className="space-y-2 rounded-md border border-blue-500/30 bg-blue-500/5 p-3 text-sm">
      <p className="font-medium text-blue-400">Seller payment instructions</p>

      {provider && (
        <PaymentDetailRow label="Provider" value={provider} onCopy={() => copy(provider, "Provider")} mono />
      )}

      {type === "cash_meeting" && location && (
        <PaymentDetailRow label="Meeting location" value={location} onCopy={() => copy(location, "Meeting location")} />
      )}

      {details && typeof details === "object" && Object.entries(details).map(([key, value]) => (
        value !== null && value !== "" && (
          <PaymentDetailRow
            key={key}
            label={labelize(key)}
            value={String(value)}
            onCopy={() => copy(value, labelize(key))}
            mono
          />
        )
      ))}

      {safetyNote && (
        <div className="mt-2 rounded border border-amber-500/30 bg-amber-500/5 p-2 text-xs text-amber-400">
          {safetyNote}
        </div>
      )}

      <p className="text-xs text-muted-foreground">
        Always include trade hash as transfer reference.
      </p>
    </div>
  )
}

function PaymentDetailRow({ label, value, onCopy, mono = false }) {
  const [copied, setCopied] = useState(false)
  const handle = () => {
    onCopy()
    setCopied(true)
    setTimeout(() => setCopied(false), 1500)
  }
  return (
    <div className="flex items-center justify-between gap-3">
      <span className="text-muted-foreground">{label}</span>
      <div className="flex min-w-0 items-center gap-1.5">
        <span className={`truncate ${mono ? "font-mono text-xs" : ""}`}>{value}</span>
        <button
          type="button"
          onClick={handle}
          className="flex size-6 shrink-0 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-primary"
          aria-label={`Copy ${label}`}
          title={copied ? "Copied!" : "Copy"}
        >
          {copied ? (
            <CheckIcon weight="bold" size={12} className="text-emerald-500" />
          ) : (
            <CopyIcon weight="duotone" size={14} />
          )}
        </button>
      </div>
    </div>
  )
}

function TradeChat({ trade, myRole }) {
  const [messages, setMessages] = useState([])
  const [body, setBody] = useState("")
  const [attachment, setAttachment] = useState(null)
  const [loading, setLoading] = useState(true)
  const [sending, setSending] = useState(false)
  const [locked, setLocked] = useState(false)
  const [scrollEl, setScrollEl] = useState(null)
  const [fileEl, setFileEl] = useState(null)
  const [muted, setMuted] = useState(() => isChatMuted())
  const lastSeenIdRef = useRef(0)
  const titleRestoreRef = useRef(null)
  const initialLoadRef = useRef(true)

  const refresh = async () => {
    try {
      const res = await api.listSellTradeMessages(trade.trade_hash)
      const next = res.data.messages ?? []
      setMessages(next)
      setLocked(!!res.data.locked)

      const lastId = lastSeenIdRef.current
      const newIncoming = next.filter((m) => m.id > lastId && m.sender_role !== myRole)

      if (initialLoadRef.current) {
        if (next.length) lastSeenIdRef.current = next[next.length - 1].id
        initialLoadRef.current = false
      } else if (newIncoming.length) {
        lastSeenIdRef.current = next[next.length - 1].id

        if (!isChatMuted()) playChatChime()

        if (document.visibilityState !== "visible") {
          if (titleRestoreRef.current) titleRestoreRef.current()
          titleRestoreRef.current = flashTabTitle(
            `(${newIncoming.length}) New trade message`,
            document.title.replace(/^\(\d+\)\s*[^—]+—\s*/, "")
          )
        }
      }
    } catch {
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    refresh()
    const id = setInterval(refresh, 5000)
    return () => {
      clearInterval(id)
      if (titleRestoreRef.current) titleRestoreRef.current()
    }
  }, [trade.trade_hash])

  const toggleMute = () => {
    const next = !muted
    setMuted(next)
    setChatMuted(next)
  }

  useEffect(() => {
    if (scrollEl) scrollEl.scrollTop = scrollEl.scrollHeight
  }, [messages.length, scrollEl])

  const send = async () => {
    if (locked) return
    if (!body.trim() && !attachment) {
      toast.error("Type a message or attach a file")
      return
    }
    setSending(true)
    try {
      await api.sendSellTradeMessage(trade.trade_hash, { body: body.trim() || undefined, attachment })
      setBody("")
      setAttachment(null)
      if (fileEl) fileEl.value = ""
      await refresh()
    } catch (e) {
      toast.error(e?.message ?? "Send failed")
    } finally {
      setSending(false)
    }
  }

  const fetchAttachmentUrl = async (msgId) => {
    const res = await api.downloadSellTradeMessageAttachment(trade.trade_hash, msgId)
    if (!res.ok) throw new Error("Download failed")
    const blob = await res.blob()
    return URL.createObjectURL(blob)
  }

  const openInLightbox = async (msgId) => {
    try {
      const url = await fetchAttachmentUrl(msgId)
      Fancybox.show([{ src: url, type: "image" }], {
        Toolbar: { display: { right: ["close"] } },
      })
    } catch {
      toast.error("Failed to load image")
    }
  }

  const openInNewTab = async (msgId) => {
    try {
      const url = await fetchAttachmentUrl(msgId)
      window.open(url, "_blank")
    } catch {
      toast.error("Failed to open file")
    }
  }

  const fmtTime = (iso) => {
    if (!iso) return ""
    const d = new Date(iso)
    return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
  }

  const onKeyDown = (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault()
      if (!sending && (body.trim() || attachment)) send()
    }
  }

  return (
    <Card className="overflow-hidden">
      <CardHeader className="border-b bg-card/50 py-3">
        <div className="flex items-center justify-between gap-2">
          <div>
            <CardTitle className="text-base">Trade chat</CardTitle>
            <p className="mt-0.5 text-xs text-muted-foreground">
              Private — visible only to you and the counterparty.
            </p>
          </div>
          <button
            type="button"
            onClick={toggleMute}
            className="flex size-8 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
            title={muted ? "Sound muted — click to enable" : "Sound on — click to mute"}
            aria-label={muted ? "Unmute chat sounds" : "Mute chat sounds"}
          >
            {muted ? <SpeakerSlashIcon weight="duotone" size={16} /> : <SpeakerHighIcon weight="duotone" size={16} />}
          </button>
        </div>
      </CardHeader>

      <div
        ref={setScrollEl}
        className="flex h-96 flex-col gap-3 overflow-y-auto bg-background/40 px-4 py-4"
      >
        {loading && (
          <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
            Loading…
          </div>
        )}
        {!loading && messages.length === 0 && (
          <div className="flex h-full flex-col items-center justify-center gap-1 text-center text-sm text-muted-foreground">
            <p>No messages yet</p>
            <p className="text-xs">Coordinate the trade here.</p>
          </div>
        )}
        {messages.map((m) => {
          const mine = m.sender_role === myRole
          return (
            <div key={m.id} className={`flex w-full ${mine ? "justify-end" : "justify-start"}`}>
              <div className={`max-w-[78%] ${mine ? "items-end" : "items-start"} flex flex-col gap-1`}>
                <div
                  className={`inline-block rounded-2xl px-3 py-2 text-sm leading-snug ${
                    mine
                      ? "bg-primary text-primary-foreground rounded-br-sm"
                      : "bg-muted text-foreground rounded-bl-sm"
                  }`}
                >
                  {m.body && (
                    <p className="whitespace-pre-wrap break-words">{m.body}</p>
                  )}
                  {m.has_attachment && (
                    <ChatAttachmentPreview
                      message={m}
                      mine={mine}
                      tradeHash={trade.trade_hash}
                      onImageClick={() => openInLightbox(m.id)}
                      onPdfClick={() => openInNewTab(m.id)}
                      onFileClick={() => openInNewTab(m.id)}
                    />
                  )}
                </div>
                <span className={`px-1 text-[10px] text-muted-foreground ${mine ? "text-right" : "text-left"}`}>
                  {fmtTime(m.created_at)}
                </span>
              </div>
            </div>
          )
        })}
      </div>

      {locked ? (
        <div className="border-t px-4 py-3 text-center text-xs text-muted-foreground">
          Chat is closed. Trade has been completed, cancelled, or resolved.
        </div>
      ) : (
        <div className="border-t bg-card/50 px-3 py-2">
          {attachment && (
            <div className="mb-2 flex items-center justify-between rounded-md bg-muted/40 px-2.5 py-1.5 text-xs">
              <div className="flex items-center gap-2 truncate">
                {attachment.type?.startsWith("image/") ? (
                  <ImageIcon weight="duotone" size={16} className="text-primary" />
                ) : (
                  <FileTextIcon weight="duotone" size={16} className="text-primary" />
                )}
                <span className="truncate">{attachment.name}</span>
                <span className="text-muted-foreground">
                  · {(attachment.size / 1024).toFixed(0)} KB
                </span>
              </div>
              <button
                type="button"
                onClick={() => { setAttachment(null); if (fileEl) fileEl.value = "" }}
                className="ml-2 rounded-full p-1 hover:bg-muted"
              >
                <XIcon weight="bold" size={12} />
              </button>
            </div>
          )}
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => fileEl?.click()}
              disabled={sending}
              className="flex size-9 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-50"
              aria-label="Attach file"
            >
              <PaperclipIcon weight="bold" size={18} />
            </button>
            <input
              ref={setFileEl}
              type="file"
              accept="image/*,application/pdf"
              onChange={(e) => setAttachment(e.target.files?.[0] || null)}
              disabled={sending}
              className="hidden"
            />
            <input
              type="text"
              value={body}
              onChange={(e) => setBody(e.target.value)}
              onKeyDown={onKeyDown}
              placeholder="Write a message…"
              disabled={sending}
              className="flex-1 rounded-full border bg-background px-4 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
            />
            <button
              type="button"
              onClick={send}
              disabled={sending || (!body.trim() && !attachment)}
              className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground transition-opacity hover:opacity-90 disabled:opacity-40"
              aria-label="Send"
            >
              <PaperPlaneTiltIcon weight="fill" size={16} />
            </button>
          </div>
        </div>
      )}

    </Card>
  )
}

function ChatAttachmentPreview({ message, mine, tradeHash, onImageClick, onPdfClick, onFileClick }) {
  const att = message.attachment
  const kind = att?.kind || "file"
  const sizeLabel = att?.size_bytes ? `${(att.size_bytes / 1024).toFixed(0)} KB` : ""
  const filename = att?.filename || `attachment-${message.id}`

  if (kind === "image") {
    return (
      <ChatImageBubble
        tradeHash={tradeHash}
        messageId={message.id}
        onClick={onImageClick}
      />
    )
  }

  const cardClass = mine
    ? "bg-primary-foreground/15 hover:bg-primary-foreground/25"
    : "bg-background/60 hover:bg-background"

  if (kind === "pdf") {
    return (
      <button
        type="button"
        onClick={onPdfClick}
        className={`mt-1 flex w-full max-w-[260px] items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-xs transition-colors ${cardClass}`}
      >
        <span className={`flex size-9 shrink-0 items-center justify-center rounded-md ${mine ? "bg-primary-foreground/20" : "bg-rose-500/15"}`}>
          <FileTextIcon weight="duotone" size={20} className={mine ? "" : "text-rose-400"} />
        </span>
        <span className="flex-1 truncate">
          <span className="block font-medium">{filename}</span>
          <span className={`text-[10px] ${mine ? "opacity-80" : "text-muted-foreground"}`}>
            PDF{sizeLabel && ` · ${sizeLabel}`}
          </span>
        </span>
      </button>
    )
  }

  return (
    <button
      type="button"
      onClick={onFileClick}
      className={`mt-1 flex w-full max-w-[260px] items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-xs transition-colors ${cardClass}`}
    >
      <span className={`flex size-9 shrink-0 items-center justify-center rounded-md ${mine ? "bg-primary-foreground/20" : "bg-muted-foreground/15"}`}>
        <FileTextIcon weight="duotone" size={20} />
      </span>
      <span className="flex-1 truncate">
        <span className="block font-medium">{filename}</span>
        <span className={`text-[10px] ${mine ? "opacity-80" : "text-muted-foreground"}`}>
          {(att?.extension || "FILE").toUpperCase()}{sizeLabel && ` · ${sizeLabel}`}
        </span>
      </span>
    </button>
  )
}

function ChatImageBubble({ tradeHash, messageId, onClick }) {
  const [thumbUrl, setThumbUrl] = useState(null)
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    let cancelled = false
    let createdUrl = null
    api.downloadSellTradeMessageAttachment(tradeHash, messageId)
      .then((res) => {
        if (!res.ok) throw new Error("fail")
        return res.blob()
      })
      .then((blob) => {
        if (cancelled) return
        createdUrl = URL.createObjectURL(blob)
        setThumbUrl(createdUrl)
      })
      .catch(() => { if (!cancelled) setFailed(true) })
    return () => {
      cancelled = true
      if (createdUrl) URL.revokeObjectURL(createdUrl)
    }
  }, [tradeHash, messageId])

  if (failed) {
    return (
      <button
        type="button"
        onClick={onClick}
        className="mt-1 flex items-center gap-2 rounded-md bg-background/60 px-2 py-1.5 text-xs text-muted-foreground hover:bg-background"
      >
        <ImageIcon weight="duotone" size={16} />
        <span>Image (click to view)</span>
      </button>
    )
  }

  return (
    <button
      type="button"
      onClick={onClick}
      className="mt-1 block overflow-hidden rounded-lg border border-white/10 transition-opacity hover:opacity-90"
      aria-label="Open image"
    >
      {thumbUrl ? (
        <img
          src={thumbUrl}
          alt="attachment"
          className="block max-h-64 max-w-[260px] object-cover"
        />
      ) : (
        <div className="flex h-32 w-48 items-center justify-center bg-background/40 text-xs text-muted-foreground">
          Loading…
        </div>
      )}
    </button>
  )
}

// A4: buyer uploads fiat payment proof image/PDF.
function PaymentProofUploader({ trade, proofFile, setProofFile, busy, onUpload }) {
  return (
    <div className="space-y-2 rounded-md border p-3">
      <p className="text-sm font-medium">
        {trade.has_payment_proof ? "Re-upload payment proof" : "Upload payment proof"}
      </p>
      <p className="text-xs text-muted-foreground">
        Screenshot of bank transfer, receipt, or confirmation. Image or PDF, max 5MB. Helps the seller verify your payment.
      </p>
      {trade.has_payment_proof && (
        <p className="text-xs text-emerald-500">
          Already uploaded {trade.payment_proof_uploaded_at ? new Date(trade.payment_proof_uploaded_at).toLocaleString() : ""}
        </p>
      )}
      <Input
        type="file"
        accept="image/*,application/pdf"
        onChange={(e) => setProofFile(e.target.files?.[0])}
      />
      <LoadingButton size="sm" variant="outline" loading={busy} loadingText="Uploading…" disabled={!proofFile} onClick={onUpload}>
        Upload proof
      </LoadingButton>
    </div>
  )
}

// A9: live countdown to trade expiry. Refreshes every 1s.
// Visible to both parties when status is Pending or EscrowLocked.
function ExpiryCountdown({ trade }) {
  const [remaining, setRemaining] = useState(() => msUntil(trade.expires_at))

  useEffect(() => {
    const id = setInterval(() => setRemaining(msUntil(trade.expires_at)), 1000)
    return () => clearInterval(id)
  }, [trade.expires_at])

  const isExpired = remaining <= 0
  const minutes = Math.floor(Math.max(0, remaining) / 60000)
  const seconds = Math.floor((Math.max(0, remaining) % 60000) / 1000)
  const display = `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`

  // Color escalation: <2 min = red, <10 min = amber, else blue.
  const tone = isExpired || minutes < 2
    ? "border-rose-500/40 bg-rose-500/10 text-rose-400"
    : minutes < 10
    ? "border-amber-500/40 bg-amber-500/10 text-amber-400"
    : "border-blue-500/40 bg-blue-500/10 text-blue-400"

  return (
    <div className={`rounded-md border p-3 text-sm ${tone}`}>
      <p className="font-medium">
        {isExpired
          ? "This trade has expired."
          : `Time left to complete payment: ${display}`}
      </p>
    </div>
  )
}

function msUntil(isoString) {
  if (!isoString) return 0
  return new Date(isoString).getTime() - Date.now()
}

// A7: seller views buyer-uploaded cash proof (in-person/NFT trades).
function CashProofViewer({ trade }) {
  const [busy, setBusy] = useState(false)
  const open = async () => {
    setBusy(true)
    try {
      const res = await api.downloadSellCashProof(trade.trade_hash)
      if (!res.ok) throw new Error("Download failed")
      const blob = await res.blob()
      const url = URL.createObjectURL(blob)
      window.open(url, "_blank")
    } catch {
      toast.error("Failed to load cash proof")
    } finally {
      setBusy(false)
    }
  }
  return (
    <div className="rounded-md border border-emerald-500/30 bg-emerald-500/5 p-3 text-sm">
      <p className="font-medium text-emerald-400">Buyer uploaded cash proof</p>
      <LoadingButton size="sm" variant="outline" className="mt-2" loading={busy} loadingText="Opening…" onClick={open}>
        Open / download proof
      </LoadingButton>
    </div>
  )
}

// A4: seller views buyer-uploaded proof. Sanctum endpoint, must use fetch+blob.
function PaymentProofViewer({ trade }) {
  const [busy, setBusy] = useState(false)
  const open = async () => {
    setBusy(true)
    try {
      const res = await api.downloadSellPaymentProof(trade.trade_hash)
      if (!res.ok) throw new Error("Download failed")
      const blob = await res.blob()
      const url = URL.createObjectURL(blob)
      window.open(url, "_blank")
    } catch {
      toast.error("Failed to load payment proof")
    } finally {
      setBusy(false)
    }
  }
  return (
    <div className="rounded-md border border-emerald-500/30 bg-emerald-500/5 p-3 text-sm">
      <p className="font-medium text-emerald-400">Buyer uploaded payment proof</p>
      {trade.payment_proof_uploaded_at && (
        <p className="text-xs text-muted-foreground">
          {new Date(trade.payment_proof_uploaded_at).toLocaleString()}
        </p>
      )}
      <LoadingButton size="sm" variant="outline" className="mt-2" loading={busy} loadingText="Opening…" onClick={open}>
        Open / download proof
      </LoadingButton>
    </div>
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

function CopyableRow({ label, value }) {
  const onCopy = () => {
    navigator.clipboard?.writeText(value).then(
      () => toast.success("Copied"),
      () => toast.error("Copy failed")
    )
  }
  const truncated = value && value.length > 22 ? `${value.slice(0, 12)}…${value.slice(-8)}` : value
  return (
    <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
      <span className="text-muted-foreground">{label}</span>
      <button
        type="button"
        onClick={onCopy}
        className="inline-flex items-center gap-1.5 rounded border border-transparent px-1.5 py-0.5 font-mono text-xs hover:bg-muted hover:border-border"
        title="Click to copy"
      >
        <span>{truncated}</span>
        <CopyIcon size={12} className="opacity-60" />
      </button>
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
  // A2: labels reflect actual sell-flow state (no "accept" terminology).
  const map = {
    pending: { label: "Waiting for Buyer", color: "bg-blue-500/15 text-blue-400" },
    escrow_locked: { label: "Buyer Paying", color: "bg-amber-500/15 text-amber-400" },
    payment_sent: { label: "Verify Payment", color: "bg-purple-500/15 text-purple-400" },
    completed: { label: "Completed", color: "bg-emerald-500/15 text-emerald-400" },
    disputed: { label: "Disputed", color: "bg-rose-500/15 text-rose-400" },
    cancelled: { label: "Cancelled", color: "bg-zinc-500/15 text-zinc-400" },
    expired: { label: "Expired", color: "bg-zinc-500/15 text-zinc-400" },
    resolved: { label: "Resolved", color: "bg-emerald-500/15 text-emerald-400" },
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
        <LoadingButton variant="destructive" size="sm" loading={busy} loadingText="Opening dispute…" disabled={disputeReason.trim().length < 10} onClick={onOpen}>
          Open dispute (Mediator Council reviews)
        </LoadingButton>
      </div>
    </details>
  )
}
