import { useEffect, useState, useRef } from "react"
import { Link, router } from "@inertiajs/react"
import { toast } from "sonner"
import { ethers } from "ethers"
import { Star, FileImage, CheckCircle, UploadSimple, DownloadSimple } from "@phosphor-icons/react"
import PublicHeader from "@/components/PublicHeader"
import PublicFooter from "@/components/PublicFooter"
import { Button } from "@/components/ui/button"
import { useWallet } from "@/hooks/useWallet"
import { api } from "@/lib/api"
import { ESCROW_SELL_ABI, useBlockchainConfig } from "@/lib/contracts"

const humanizeStatus = (s) => (s || "").replace(/_/g, " ").replace(/\b\w/g, c => c.toUpperCase())

export default function SellTradeRoom({ tradeHash }) {
  const { merchant, signer, phraseWallet } = useWallet()
  const { escrowAddress, rpcUrl } = useBlockchainConfig()
  const [trade, setTrade] = useState(null)
  const [loading, setLoading] = useState(true)
  const [acting, setActing] = useState(false)

  const refresh = async () => {
    try {
      const res = await api.getTradeStatus?.(tradeHash) || await fetch(`/api/trade/${tradeHash}/status`, {
        headers: { Accept: "application/json", Authorization: `Bearer ${localStorage.getItem("auth_token")}` }
      }).then(r => r.json())
      setTrade(res.data || res)
    } catch {
      setTrade(null)
    }
  }

  useEffect(() => {
    refresh().finally(() => setLoading(false))
    const t = setInterval(refresh, 10_000)
    return () => clearInterval(t)
  }, [tradeHash])

  const isBuyer = trade && merchant && trade.buyer_wallet?.toLowerCase() === merchant.wallet_address?.toLowerCase()
  const isSeller = trade && merchant && trade.seller_wallet?.toLowerCase() === merchant.wallet_address?.toLowerCase()

  const markPaid = async () => {
    if (!escrowAddress || (!signer && !phraseWallet)) { toast.error("Wallet not ready"); return }
    setActing(true)
    try {
      const signerForTx = phraseWallet
        ? phraseWallet.connect(new ethers.providers.JsonRpcProvider(rpcUrl || "https://sepolia.base.org"))
        : signer
      const escrow = new ethers.Contract(escrowAddress, ESCROW_SELL_ABI, signerForTx)
      toast.info("Marking payment sent on-chain…")
      const tx = await escrow.markSellPaymentSent(trade.trade_hash)
      const receipt = await tx.wait()
      await api.markSellPaymentSent(tradeHash, { paid_tx_hash: receipt.transactionHash })
      toast.success("Payment marked as sent")
      refresh()
    } catch (e) {
      const msg = e?.reason || e?.data?.message || e?.message || "Failed"
      toast.error(msg)
    } finally { setActing(false) }
  }

  const dispute = async () => {
    if (!confirm("Open a dispute? Funds will be locked until multisig resolves.")) return
    setActing(true)
    try { await api.openSellDispute(tradeHash); toast.success("Dispute opened"); refresh() }
    catch (e) { toast.error(e?.message || "Failed") }
    finally { setActing(false) }
  }

  if (loading) return <Shell><div className="p-8 text-center text-[#8b96b0]">Loading…</div></Shell>
  if (!trade) return <Shell><div className="p-8 text-center text-[#8b96b0]">Trade not found.</div></Shell>

  return (
    <Shell>
      <h1 className="text-2xl font-semibold">Sell Trade</h1>
      <p className="mt-1 font-mono text-xs text-[#8b96b0]">{trade.trade_hash}</p>

      {!isBuyer && !isSeller && (
        <div className="mt-3 rounded-lg border border-[#fbbf24]/30 bg-[#fbbf24]/10 px-3 py-2 text-xs text-[#fbbf24]">
          You're connected as <span className="font-mono">{merchant?.wallet_address?.slice(0, 10)}...</span> — neither the buyer nor the seller of this trade.
        </div>
      )}

      <div className="mt-5 grid grid-cols-2 gap-3">
        <Stat label="Amount" value={`${Number(trade.amount_usdc).toLocaleString()} USDC`} />
        <Stat label={isBuyer ? "You pay" : "You receive"} value={`${Number(trade.amount_fiat).toLocaleString()} ${trade.currency_code}`} />
        <Stat label="Status" value={trade.status_label || humanizeStatus(trade.status)} />
        <Stat label="Payment method" value={trade.payment_method_label || trade.payment_method || "—"} />
      </div>

      <FeeBreakdown trade={trade} isBuyer={isBuyer} />

      <PaymentInstructions trade={trade} isBuyer={isBuyer} isSeller={isSeller} />

      <BankProofBlock trade={trade} isBuyer={isBuyer} isSeller={isSeller} onUploaded={refresh} />

      {isSeller && trade.status === "payment_sent" && (
        <div className="mt-6 rounded-xl border border-[#1e2a42] bg-[#161c2d] p-5">
          <p className="text-sm text-[#e8edf7]">Buyer claims payment is sent. Verify off-chain, then sign the release.</p>
          <Button asChild className="mt-3"><Link href={`/sell/trade/${tradeHash}/release`}>Verify & Release</Link></Button>
        </div>
      )}

      {isBuyer && trade.status === "escrow_locked" && (
        <div className="mt-6 rounded-xl border border-[#1e2a42] bg-[#161c2d] p-5">
          <p className="text-sm text-[#8b96b0]">Send fiat off-chain to the seller using the agreed method, then mark payment as sent.</p>
          <Button className="mt-3 w-full" onClick={markPaid} disabled={acting}>
            {acting ? "Submitting…" : "I sent the payment"}
          </Button>
        </div>
      )}

      {(isBuyer || isSeller) && ["escrow_locked", "payment_sent"].includes(trade.status) && (
        <div className="mt-3">
          <Button variant="outline" onClick={dispute} disabled={acting}>Open dispute</Button>
        </div>
      )}

      {trade.status === "completed" && (
        <>
          <div className="mt-6 rounded-xl border border-[#22c98a]/30 bg-[#22c98a]/10 p-5 text-sm text-[#22c98a]">
            Trade completed. USDC released on-chain.
          </div>
          <ReviewBlock trade={trade} isBuyer={isBuyer} isSeller={isSeller} onSubmitted={refresh} />
        </>
      )}
      {trade.status === "disputed" && (
        <div className="mt-6 rounded-xl border border-[#fbbf24]/30 bg-[#fbbf24]/10 p-5 text-sm text-[#fbbf24]">
          Dispute under multisig review.
        </div>
      )}
    </Shell>
  )
}

function Shell({ children }) {
  return (
    <div className="flex min-h-screen flex-col bg-[#0a0d14] text-[#e8edf7]">
      <PublicHeader />
      <main className="mx-auto w-full max-w-2xl flex-1 px-5 py-10">{children}</main>
      <PublicFooter />
    </div>
  )
}
function Stat({ label, value }) {
  return (
    <div className="rounded-xl border border-[#1e2a42] bg-[#161c2d] p-4">
      <div className="text-xs text-[#8b96b0]">{label}</div>
      <div className="mt-1 font-mono text-sm font-semibold text-[#e8edf7]">{value}</div>
    </div>
  )
}

function BankProofBlock({ trade, isBuyer, isSeller, onUploaded }) {
  const [uploading, setUploading] = useState(false)
  const inputRef = useRef(null)
  const isOpen = ["escrow_locked", "payment_sent"].includes(trade.status)
  const hasProof = !!trade.bank_proof_path

  if (!isBuyer && !isSeller) return null
  if (!isOpen && !hasProof) return null

  const upload = async (file) => {
    if (!file) return
    setUploading(true)
    try {
      await api.uploadBankProof(trade.trade_hash, file)
      toast.success("Bank proof uploaded")
      onUploaded?.()
    } catch (e) { toast.error(e?.message || "Upload failed") }
    finally {
      setUploading(false)
      if (inputRef.current) inputRef.current.value = ""
    }
  }

  const download = async () => {
    try {
      const res = await api.downloadBankProof(trade.trade_hash)
      if (!res.ok) throw new Error(`Download failed (${res.status})`)
      const blob = await res.blob()
      const url = URL.createObjectURL(blob)
      window.open(url, "_blank")
      setTimeout(() => URL.revokeObjectURL(url), 10_000)
    } catch (e) { toast.error(e?.message || "Failed to fetch proof") }
  }

  return (
    <div className="mt-6 rounded-xl border border-[#1e2a42] bg-[#161c2d] p-5">
      <div className="flex items-center gap-2 text-sm font-semibold text-[#e8edf7]">
        <FileImage weight="duotone" className="size-5 text-[#4f6ef7]" />
        Bank proof
      </div>

      {hasProof ? (
        <div className="mt-3 flex items-center gap-3 rounded-lg border border-[#22c98a]/20 bg-[#22c98a]/5 p-3">
          <CheckCircle weight="fill" className="size-6 text-[#22c98a]" />
          <div className="flex-1 text-sm">
            <div className="font-semibold text-[#22c98a]">Proof uploaded</div>
            <div className="text-xs text-[#8b96b0]">{isSeller ? "Verify the deposit landed in your account, then sign release." : "Seller will verify and release once they confirm the deposit."}</div>
          </div>
          {isSeller && (
            <Button variant="outline" size="sm" onClick={download}>
              <DownloadSimple weight="bold" className="size-4" /> View
            </Button>
          )}
          {isBuyer && isOpen && (
            <Button variant="outline" size="sm" onClick={() => inputRef.current?.click()} disabled={uploading}>
              {uploading ? "Uploading…" : "Replace"}
            </Button>
          )}
        </div>
      ) : isBuyer ? (
        <div className="mt-3">
          <p className="text-xs text-[#8b96b0]">
            Upload a screenshot or PDF of your fiat transfer. Helps the seller verify faster + protects you in disputes.
          </p>
          <Button className="mt-3 w-full" onClick={() => inputRef.current?.click()} disabled={uploading}>
            <UploadSimple weight="bold" className="size-4" />
            {uploading ? "Uploading…" : "Upload bank proof (image / PDF)"}
          </Button>
        </div>
      ) : (
        <p className="mt-3 text-xs text-[#fbbf24]">Buyer hasn't uploaded proof yet.</p>
      )}

      <input
        ref={inputRef}
        type="file"
        accept="image/*,.pdf"
        className="hidden"
        onChange={(e) => upload(e.target.files?.[0])}
      />
    </div>
  )
}

function FeeBreakdown({ trade, isBuyer }) {
  const gross = Number(trade.amount_usdc)
  const fee = Number(trade.fee_amount) || +(gross * 0.002).toFixed(6)
  const net = +(gross - fee).toFixed(6)
  const feePct = +(fee / gross * 100).toFixed(2)
  return (
    <div className="mt-3 rounded-lg border border-[#1e2a42] bg-[#161c2d]/50 p-3 text-xs">
      <div className="mb-1 font-semibold text-[#8b96b0]">USDC settlement breakdown</div>
      <div className="flex justify-between text-[#8b96b0]"><span>Locked in escrow</span><span className="font-mono">{gross.toLocaleString()} USDC</span></div>
      <div className="flex justify-between text-[#8b96b0]"><span>Network fee ({feePct}%)</span><span className="font-mono">−{fee.toLocaleString()} USDC</span></div>
      <div className="mt-1 flex justify-between border-t border-[#1e2a42] pt-1 font-semibold text-[#e8edf7]">
        <span>{isBuyer ? "You receive on release" : "Buyer receives on release"}</span>
        <span className="font-mono text-[#22c98a]">{net.toLocaleString()} USDC</span>
      </div>
    </div>
  )
}

function ReviewCard({ heading, review }) {
  if (!review) return null
  return (
    <div className="mt-3 rounded-xl border border-[#1e2a42] bg-[#161c2d] p-4">
      <div className="flex items-center justify-between gap-3">
        <div className="text-xs font-semibold uppercase tracking-wide text-[#8b96b0]">{heading}</div>
        <div className="flex items-center gap-0.5">
          {[1, 2, 3, 4, 5].map(s => (
            <Star key={s} weight={s <= review.rating ? "fill" : "regular"}
              className={`size-4 ${s <= review.rating ? "text-[#fbbf24]" : "text-[#4a5568]"}`} />
          ))}
          <span className="ml-1 text-xs text-[#8b96b0]">{review.rating}/5</span>
        </div>
      </div>
      {review.comment && (
        <p className="mt-2 whitespace-pre-line text-sm text-[#e8edf7]">{review.comment}</p>
      )}
      <div className="mt-2 text-xs text-[#4a5568]">
        Submitted {review.created_at ? new Date(review.created_at).toLocaleString() : ""}
      </div>
    </div>
  )
}

function ReviewBlock({ trade, isBuyer, isSeller, onSubmitted }) {
  const [rating, setRating] = useState(0)
  const [comment, setComment] = useState("")
  const [submitting, setSubmitting] = useState(false)

  const buyerReview = trade.review
  const sellerReview = trade.merchant_review
  const userReview = isBuyer ? buyerReview : isSeller ? sellerReview : null
  const userCanSubmit = (isBuyer || isSeller) && !userReview

  return (
    <>
      <ReviewCard heading="Buyer's review of seller" review={buyerReview} />
      <ReviewCard heading="Seller's review of buyer" review={sellerReview} />

      {!isBuyer && !isSeller && !buyerReview && !sellerReview && (
        <div className="mt-3 rounded-xl border border-[#fbbf24]/30 bg-[#fbbf24]/10 p-4 text-sm text-[#fbbf24]">
          You're not connected as the buyer or seller of this trade. Reconnect with the correct wallet to leave a review.
        </div>
      )}

      {userCanSubmit && (
        <div className="mt-3 rounded-xl border border-[#22c98a]/20 bg-[#22c98a]/5 p-5">
          <div className="flex items-center gap-2 text-sm font-semibold text-[#e8edf7]">
            <Star weight="fill" className="size-5 text-[#fbbf24]" />
            Rate this trade ({isBuyer ? "as buyer" : "as seller"})
          </div>
          <div className="mt-3 flex items-center gap-1">
            {[1, 2, 3, 4, 5].map(s => (
              <button key={s} type="button" onClick={() => setRating(s)} className="transition-transform hover:scale-110">
                <Star weight={s <= rating ? "fill" : "regular"} className={`size-7 ${s <= rating ? "text-[#fbbf24]" : "text-[#4a5568]"}`} />
              </button>
            ))}
            {rating > 0 && <span className="ml-2 text-sm text-[#8b96b0]">{rating}/5</span>}
          </div>
          <textarea
            value={comment}
            onChange={e => setComment(e.target.value)}
            placeholder="Leave a comment (optional)"
            rows={3}
            maxLength={1000}
            className="mt-3 w-full rounded-lg border border-[#1e2a42] bg-[#0a0d14] px-3 py-2 text-sm text-[#e8edf7] placeholder:text-[#4a5568] focus:border-[#4f6ef7] focus:outline-none"
          />
          <Button className="mt-3 w-full" disabled={submitting || rating < 1} onClick={async () => {
            if (rating < 1) { toast.error("Pick a rating"); return }
            setSubmitting(true)
            try {
              await api.createReview(trade.trade_hash, { rating, comment: comment || undefined })
              toast.success("Review submitted")
              setRating(0); setComment("")
              onSubmitted?.()
            } catch (e) { toast.error(e?.message || "Failed to submit review") }
            finally { setSubmitting(false) }
          }}>
            {submitting ? "Submitting…" : "Submit Review"}
          </Button>
        </div>
      )}
    </>
  )
}

function PaymentInstructions({ trade, isBuyer, isSeller }) {
  const details = trade.payment_method_details
  const label = trade.payment_method_label || trade.payment_method
  const safety = trade.safety_note
  const sellerShort = trade.seller_wallet ? `${trade.seller_wallet.slice(0, 6)}...${trade.seller_wallet.slice(-4)}` : "seller"

  if (!details && !label) return null

  const heading = isBuyer
    ? `Send ${Number(trade.amount_fiat).toLocaleString()} ${trade.currency_code} to seller via ${label || "payment method"}`
    : isSeller
      ? `Buyer will send ${Number(trade.amount_fiat).toLocaleString()} ${trade.currency_code} to your "${label}" account`
      : `Seller's payment details (${label})`

  const subtext = isBuyer
    ? `Use the account details below. Reference: trade ${trade.trade_hash.slice(0, 10)}…`
    : isSeller
      ? `Verify the deposit lands in your account, then sign the release.`
      : null

  return (
    <div className="mt-6 rounded-xl border border-[#4f6ef7]/30 bg-[#4f6ef7]/5 p-5">
      <div className="text-sm font-semibold text-[#e8edf7]">{heading}</div>
      {subtext && <p className="mt-1 text-xs text-[#8b96b0]">{subtext}</p>}

      {details && Object.keys(details).length > 0 ? (
        <dl className="mt-4 space-y-2">
          {Object.entries(details).map(([k, v]) => (
            <div key={k} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-[#1e2a42] bg-[#0a0d14] px-3 py-2">
              <dt className="text-xs uppercase tracking-wide text-[#8b96b0]">{k.replace(/_/g, " ")}</dt>
              <dd className="font-mono text-sm text-[#e8edf7] break-all">{String(v)}</dd>
            </div>
          ))}
        </dl>
      ) : (
        <p className="mt-3 text-xs text-[#fbbf24]">
          Seller has not provided account details yet. Contact {sellerShort} or open a dispute.
        </p>
      )}

      {safety && (
        <p className="mt-3 rounded-lg border border-[#fbbf24]/30 bg-[#fbbf24]/10 p-2.5 text-xs text-[#fbbf24]">
          ⚠ {safety}
        </p>
      )}
    </div>
  )
}
