import { useState, useEffect, useCallback, useRef } from "react"
import { toast } from "sonner"
import { Link, router } from "@inertiajs/react"
import {
  CheckCircle,
  ShieldCheck,
  MapPin,
  Handshake,
  ArrowLeft,
  Info,
  Warning,
  Star,
  UploadSimple,
} from "@phosphor-icons/react"
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Separator } from "@/components/ui/separator"
import { Skeleton } from "@/components/ui/skeleton"
import ConnectWallet from "@/components/ConnectWallet"
import SiteLogo from "@/components/SiteLogo"
import TradeStatusTimeline from "@/components/TradeStatusTimeline"
import TradeCountdown from "@/components/TradeCountdown"
import NFTQRCode from "@/components/NFTQRCode"
import { useWallet } from "@/hooks/useWallet"
import { api } from "@/lib/api"
import { useTradeChannel } from "@/hooks/use-trade-channel"

function getStepsFromStatus(status) {
  const statusOrder = ["pending", "escrow_locked", "payment_sent", "completed"]
  const currentIndex = statusOrder.indexOf(status)

  return [
    { label: "Started", completed: currentIndex >= 0 },
    { label: "Meeting", completed: currentIndex >= 2, current: currentIndex === 1 || currentIndex === 2 },
    { label: "Confirmed", completed: currentIndex >= 3, current: currentIndex === 3 },
    { label: "Completed", completed: currentIndex >= 3, current: false },
  ]
}


export default function TradeMeeting({ tradeHash }) {
  const { isAuthenticated } = useWallet()
  const [trade, setTrade] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [timeLeft, setTimeLeft] = useState(0)
  const [cancelling, setCancelling] = useState(false)
  const [markingPaid, setMarkingPaid] = useState(false)
  const [showDisputeForm, setShowDisputeForm] = useState(false)
  const [disputeReason, setDisputeReason] = useState("")
  const [disputing, setDisputing] = useState(false)

  // Evidence state
  const [uploadingEvidence, setUploadingEvidence] = useState(false)
  const [evidenceNote, setEvidenceNote] = useState("")
  const evidenceInputRef = useRef(null)

  // Review state
  const [reviewRating, setReviewRating] = useState(0)
  const [reviewComment, setReviewComment] = useState("")
  const [submittingReview, setSubmittingReview] = useState(false)
  const [reviewSubmitted, setReviewSubmitted] = useState(false)

  const fetchTrade = useCallback(async () => {
    try {
      const res = await api.getTradeStatus(tradeHash)
      setTrade(res.data)
      // Calculate time left from expires_at
      if (res.data?.expires_at) {
        const expiresAt = new Date(res.data.expires_at).getTime()
        const now = Date.now()
        const diff = Math.max(0, Math.floor((expiresAt - now) / 1000))
        setTimeLeft(diff)
      }
      return res.data
    } catch (err) {
      throw err
    }
  }, [tradeHash])

  // Initial fetch
  useEffect(() => {
    setLoading(true)
    setError(null)
    fetchTrade()
      .catch((err) => {
        setError(err.message || "Failed to load trade details")
        toast.error(err.message || "Failed to load trade details")
      })
      .finally(() => setLoading(false))
  }, [fetchTrade])

  // Real-time updates via Echo, falls back to polling
  useTradeChannel(tradeHash, fetchTrade, { enabled: !loading && !error })

  // Countdown timer
  useEffect(() => {
    if (timeLeft <= 0) return
    const timer = setInterval(() => {
      setTimeLeft((prev) => (prev > 0 ? prev - 1 : 0))
    }, 1000)
    return () => clearInterval(timer)
  }, [timeLeft])

  const handleCancel = async () => {
    setCancelling(true)
    try {
      const res = await api.cancelTrade(tradeHash)
      toast.success(res.message || "Trade cancelled")
      router.visit("/trades")
    } catch (err) {
      toast.error(err.message || "Failed to cancel trade")
    } finally {
      setCancelling(false)
    }
  }

  const handleMarkPaid = async () => {
    setMarkingPaid(true)
    try {
      const res = await api.markPaid(tradeHash)
      setTrade(res.data)
      toast.success(res.message || "Payment marked as sent")
    } catch (err) {
      toast.error(err.message || "Failed to mark payment")
    } finally {
      setMarkingPaid(false)
    }
  }

  const handleDispute = async () => {
    if (!disputeReason.trim()) {
      toast.error("Please provide a reason for the dispute")
      return
    }
    setDisputing(true)
    try {
      await api.openDispute(tradeHash, disputeReason)
      toast.success("Dispute opened")
      setShowDisputeForm(false)
      setDisputeReason("")
      await fetchTrade()
    } catch (err) {
      toast.error(err.message || "Failed to open dispute")
    } finally {
      setDisputing(false)
    }
  }

  const handleUploadEvidence = async (file) => {
    if (!file || !trade?.dispute?.id) return
    setUploadingEvidence(true)
    try {
      await api.uploadDisputeEvidence(trade.dispute.id, file, evidenceNote.trim() || undefined)
      toast.success("Evidence uploaded")
      setEvidenceNote("")
      await fetchTrade()
    } catch (err) {
      toast.error(err.message || "Failed to upload evidence")
    } finally {
      setUploadingEvidence(false)
      if (evidenceInputRef.current) evidenceInputRef.current.value = ""
    }
  }

  const handleSubmitReview = async () => {
    if (reviewRating < 1) {
      toast.error("Select a rating")
      return
    }
    setSubmittingReview(true)
    try {
      await api.createReview(tradeHash, {
        rating: reviewRating,
        comment: reviewComment || undefined,
      })
      toast.success("Review submitted")
      setReviewSubmitted(true)
    } catch (err) {
      toast.error(err.message || "Failed to submit review")
    } finally {
      setSubmittingReview(false)
    }
  }

  // Loading skeleton
  if (loading) {
    return (
      <div className="min-h-screen overflow-x-hidden bg-background">
        <header className="sticky top-0 z-50 border-b border-border/50 bg-background/80 backdrop-blur-xl">
          <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 lg:px-6">
            <Link href="/"><SiteLogo /></Link>
            <ConnectWallet />
          </div>
        </header>
        <div className="mx-auto max-w-2xl px-4 py-8 lg:px-6 space-y-6">
          <Skeleton className="h-6 w-40" />
          <Skeleton className="h-20 w-full rounded-xl" />
          <Skeleton className="h-48 w-full rounded-xl" />
          <Skeleton className="h-32 w-full rounded-xl" />
          <Skeleton className="h-64 w-full rounded-xl" />
        </div>
      </div>
    )
  }

  // Error state
  if (error) {
    return (
      <div className="min-h-screen overflow-x-hidden bg-background">
        <header className="sticky top-0 z-50 border-b border-border/50 bg-background/80 backdrop-blur-xl">
          <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 lg:px-6">
            <Link href="/"><SiteLogo /></Link>
            <ConnectWallet />
          </div>
        </header>
        <div className="mx-auto max-w-2xl px-4 py-8 lg:px-6">
          <Card className="border-red-500/20 bg-red-500/5">
            <CardContent className="flex flex-col items-center gap-4 py-12">
              <Warning weight="fill" size={48} className="text-red-400" />
              <p className="text-lg font-semibold text-red-400">{error}</p>
              <Link href="/trades" className="text-sm text-muted-foreground hover:text-foreground">
                Back to trades
              </Link>
            </CardContent>
          </Card>
        </div>
      </div>
    )
  }

  const tradeStatus = trade?.status?.value || trade?.status || "pending"
  const steps = getStepsFromStatus(tradeStatus)
  const buyerWallet = trade?.buyer_wallet || ""
  const truncatedWallet = buyerWallet ? `${buyerWallet.slice(0, 6)}...${buyerWallet.slice(-4)}` : "---"
  const amountUsdc = Number(trade?.amount_usdc) || 0
  const amountFiat = Number(trade?.amount_fiat) || 0
  const currencyCode = trade?.currency_code || ""
  const merchantName = trade?.merchant?.username || "Merchant"
  const paymentMethodName = trade?.payment_method || ""
  const meetingLocation = trade?.meeting_location || "Location not set"
  const tokenId = trade?.nft_token_id || "N/A"
  const escrowAmount = amountUsdc

  const canCancel = tradeStatus === "pending" || tradeStatus === "escrow_locked" || tradeStatus === "payment_sent"
  const isTerminal = ["completed", "cancelled", "expired", "disputed"].includes(tradeStatus)

  return (
    <div className="min-h-screen overflow-x-hidden bg-background">
      {/* Top Navigation Bar */}
      <header className="sticky top-0 z-50 border-b border-border/50 bg-background/80 backdrop-blur-xl">
        <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 lg:px-6">
          <Link href="/"><SiteLogo /></Link>
          <ConnectWallet />
        </div>
      </header>

      <div className="mx-auto max-w-2xl px-4 py-8 lg:px-6">
        {/* Back Link */}
        <Link
          href="/trades"
          className="mb-6 inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
        >
          <ArrowLeft weight="bold" size={16} />
          Back to Trades
        </Link>

        <div className="space-y-6">
          {/* Status Timeline */}
          <Card className="border-border/50">
            <CardContent className="py-6">
              <TradeStatusTimeline steps={steps} />
            </CardContent>
          </Card>

          {/* Pending — Escrow locking in progress */}
          {tradeStatus === "pending" && (
            <Card className="border-amber-500/20 bg-amber-500/5">
              <CardContent className="flex items-center gap-3 py-4">
                <span className="relative flex size-3">
                  <span className="absolute inline-flex size-full animate-ping rounded-full bg-amber-400 opacity-75" />
                  <span className="relative inline-flex size-3 rounded-full bg-amber-500" />
                </span>
                <span className="text-sm font-semibold text-amber-400">Locking escrow on-chain... This usually takes 10-30 seconds.</span>
              </CardContent>
            </Card>
          )}

          {/* Terminal Status Banner */}
          {isTerminal && (
            <Card className={`border ${
              tradeStatus === "completed" ? "border-emerald-500/20 bg-emerald-500/5" :
              tradeStatus === "disputed" ? "border-red-500/20 bg-red-500/5" :
              "border-muted-foreground/20 bg-muted/5"
            }`}>
              <CardContent className="flex items-center justify-center gap-3 py-6">
                <span className={`text-lg font-bold capitalize ${
                  tradeStatus === "completed" ? "text-emerald-400" :
                  tradeStatus === "disputed" ? "text-red-400" :
                  "text-muted-foreground"
                }`}>
                  Trade {tradeStatus.replace("_", " ")}
                </span>
              </CardContent>
            </Card>
          )}

          {/* Trade Details */}
          <Card className="border-border/50">
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <Handshake weight="duotone" size={20} className="text-emerald-400" />
                Trade Details
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-muted-foreground">Trade ID</span>
                  <span className="font-mono text-sm font-semibold">{trade?.trade_hash ? `#${trade.trade_hash.slice(0, 8)}` : "---"}</span>
                </div>
                <Separator />
                <div className="flex items-center justify-between">
                  <span className="text-sm text-muted-foreground">Buyer Wallet</span>
                  <span className="font-mono text-sm">{truncatedWallet}</span>
                </div>
                <Separator />
                <div className="flex items-center justify-between">
                  <span className="text-sm text-muted-foreground">Amount</span>
                  <div className="text-right">
                    <span className="font-mono text-sm font-semibold">{amountUsdc} USDC</span>
                    {amountFiat > 0 && (
                      <span className="text-sm text-muted-foreground">
                        {" "}= {amountFiat.toLocaleString()} {currencyCode}
                      </span>
                    )}
                  </div>
                </div>
                <Separator />
                <div className="flex items-center justify-between">
                  <span className="text-sm text-muted-foreground">Payment Method</span>
                  <span className="text-sm font-medium">{paymentMethodName}</span>
                </div>
                <Separator />
                <div className="flex items-center justify-between">
                  <span className="text-sm text-muted-foreground">Merchant</span>
                  <span className="text-sm font-semibold">{merchantName}</span>
                </div>
                {trade?.merchant_verified_name && (
                  <>
                    <Separator />
                    <div className="flex items-center justify-between">
                      <span className="text-sm text-muted-foreground">Verified Name</span>
                      <span className="text-sm font-semibold">{trade.merchant_verified_name}</span>
                    </div>
                  </>
                )}
                {trade?.merchant_business_name && (
                  <>
                    <Separator />
                    <div className="flex items-center justify-between">
                      <span className="text-sm text-muted-foreground">Business</span>
                      <span className="text-sm font-semibold">{trade.merchant_business_name}</span>
                    </div>
                  </>
                )}
                <Separator />
                <div className="flex items-center justify-between">
                  <span className="text-sm text-muted-foreground">Meeting Location</span>
                  <div className="flex items-center gap-1.5">
                    <MapPin weight="fill" size={14} className="text-primary" />
                    <span className="text-sm font-medium">{meetingLocation}</span>
                  </div>
                </div>
              </div>
              {trade?.safety_note && (
                <div className="mt-3 flex items-start gap-2 rounded-lg bg-amber-500/10 border border-amber-500/20 px-3 py-2.5">
                  <Info weight="fill" size={14} className="mt-0.5 shrink-0 text-amber-400" />
                  <p className="text-sm text-amber-400">{trade.safety_note}</p>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Escrow Status Banner */}
          {!isTerminal && (
            <div className="flex flex-wrap items-center gap-3 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-5 py-4">
              <ShieldCheck weight="fill" size={28} className="text-emerald-500 shrink-0" />
              <div className="flex-1 min-w-0">
                <p className="text-sm font-semibold text-emerald-400">USDC is locked in escrow</p>
                <p className="text-sm text-muted-foreground">
                  Funds are safely held until both parties confirm
                </p>
              </div>
              <span className="font-mono text-xl font-bold text-emerald-400 shrink-0">
                {escrowAmount} USDC
              </span>
            </div>
          )}

          {/* Timer Card */}
          {!isTerminal && timeLeft > 0 && (
            <TradeCountdown
              timeLeft={timeLeft}
              label="Meeting Timer"
              description="Complete the meeting before the timer expires"
            />
          )}

          {/* QR Code Card */}
          <NFTQRCode tradeHash={tradeHash} tokenId={tokenId} amountUsdc={trade?.amount_usdc} />

          {/* Stake Info */}
          {!isTerminal && Number(trade?.stake_amount) > 0 && (
            <div className="flex items-center justify-between rounded-lg bg-muted/30 px-4 py-3">
              <span className="text-sm text-muted-foreground">Anti-spam stake</span>
              <span className="font-mono text-sm font-semibold">${Number(trade.stake_amount).toLocaleString()} USDC</span>
            </div>
          )}

          {/* Completed Banner */}
          {tradeStatus === "completed" && (
            <div className="flex flex-col items-center gap-3 rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-5 py-6 text-center">
              <CheckCircle weight="fill" size={40} className="text-emerald-500" />
              <div>
                <p className="text-lg font-semibold text-emerald-400">Trade Completed</p>
                <p className="text-sm text-muted-foreground">USDC has been released to your wallet</p>
                {Number(trade?.stake_amount) > 0 && (
                  <p className="text-sm text-emerald-400 mt-1">${Number(trade.stake_amount).toLocaleString()} USDC stake refunded</p>
                )}
              </div>
            </div>
          )}

          {/* Cancelled Banner */}
          {tradeStatus === "cancelled" && (
            <div className="flex flex-col items-center gap-3 rounded-xl border border-red-500/20 bg-red-500/5 px-5 py-6 text-center">
              <Warning weight="fill" size={40} className="text-red-400" />
              <div>
                <p className="text-lg font-semibold text-red-400">Trade Cancelled</p>
                <p className="text-sm text-muted-foreground">This trade has been cancelled</p>
              </div>
            </div>
          )}

          {/* Expired Banner */}
          {tradeStatus === "expired" && (
            <div className="flex flex-col items-center gap-3 rounded-xl border border-amber-500/20 bg-amber-500/5 px-5 py-6 text-center">
              <Warning weight="fill" size={40} className="text-amber-400" />
              <div>
                <p className="text-lg font-semibold text-amber-400">Trade Expired</p>
                <p className="text-sm text-muted-foreground">This trade expired before completion</p>
              </div>
            </div>
          )}

          {/* Dispute Evidence Upload */}
          {trade?.dispute && (
            <Card className={tradeStatus === "disputed" ? "border-red-500/30 bg-red-500/5 ring-1 ring-red-500/20" : "border-border/50"}>
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                  <Warning weight="fill" size={20} className={tradeStatus === "disputed" ? "text-red-400" : "text-muted-foreground"} />
                  {tradeStatus === "disputed" ? "Trade Under Dispute — Submit Your Evidence" : `Dispute ${trade.dispute.status?.replace("_", " ") || "Resolved"}`}
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  {tradeStatus === "disputed" && (
                    <div className="flex items-center gap-3 rounded-lg bg-amber-500/10 border border-amber-500/20 px-4 py-3">
                      <span className="relative flex size-3 shrink-0">
                        <span className="absolute inline-flex size-full animate-ping rounded-full bg-amber-400 opacity-75" />
                        <span className="relative inline-flex size-3 rounded-full bg-amber-500" />
                      </span>
                      <p className="text-sm font-medium text-amber-400">
                        USDC is held in escrow. Upload screenshots, receipts, or chat logs to support your case.
                      </p>
                    </div>
                  )}
                  {trade.dispute.reason && (
                    <div className="rounded-lg bg-muted/20 px-3 py-2">
                      <p className="text-xs font-medium text-muted-foreground mb-1">Dispute Reason</p>
                      <p className="text-sm">{trade.dispute.reason}</p>
                    </div>
                  )}
                  {trade.dispute.evidence && trade.dispute.evidence.length > 0 && (
                    <div className="space-y-2">
                      <p className="text-sm font-medium">Submitted Evidence ({trade.dispute.evidence.length})</p>
                      {trade.dispute.evidence.map((e, i) => (
                        <div key={i} className="flex items-center justify-between rounded-lg bg-muted/20 px-3 py-2">
                          <span className="text-sm truncate">{e.original_name}</span>
                          <span className="text-xs text-muted-foreground shrink-0 ml-2">{e.uploaded_at ? new Date(e.uploaded_at).toLocaleDateString() : ""}</span>
                        </div>
                      ))}
                    </div>
                  )}
                  {tradeStatus === "disputed" && (
                    <>
                      <textarea
                        value={evidenceNote}
                        onChange={(e) => setEvidenceNote(e.target.value)}
                        placeholder="Add a note for the admin (explain what happened)..."
                        rows={2}
                        maxLength={2000}
                        className="w-full rounded-lg border border-border/50 bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-amber-500/50"
                      />
                      <div className="space-y-2">
                        <Button
                          size="lg"
                          className="w-full gap-2 bg-amber-600 text-white hover:bg-amber-700"
                          disabled={uploadingEvidence}
                          onClick={() => evidenceInputRef.current?.click()}
                        >
                          <UploadSimple weight="bold" size={16} />
                          {uploadingEvidence ? "Uploading..." : "Upload Evidence"}
                        </Button>
                        <input
                          ref={evidenceInputRef}
                          type="file"
                          accept="image/*,.pdf,.mp4,.webm"
                          className="hidden"
                          onChange={(e) => e.target.files?.[0] && handleUploadEvidence(e.target.files[0])}
                        />
                        <p className="text-xs text-muted-foreground">JPG, PNG, PDF, MP4 — Max 10MB</p>
                      </div>
                    </>
                  )}
                </div>
              </CardContent>
            </Card>
          )}

          {/* Review Form — shows after trade completes */}
          {tradeStatus === "completed" && !reviewSubmitted && !trade?.review && (
            <Card className="border-emerald-500/20 bg-emerald-500/5">
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                  <Star weight="fill" size={20} className="text-amber-400" />
                  Rate this trade
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  <div className="flex items-center gap-1">
                    {[1, 2, 3, 4, 5].map((star) => (
                      <button
                        key={star}
                        type="button"
                        onClick={() => setReviewRating(star)}
                        className="transition-transform hover:scale-110"
                      >
                        <Star
                          weight={star <= reviewRating ? "fill" : "regular"}
                          size={32}
                          className={star <= reviewRating ? "text-amber-400" : "text-muted-foreground/30"}
                        />
                      </button>
                    ))}
                    {reviewRating > 0 && (
                      <span className="ml-2 text-sm text-muted-foreground">{reviewRating}/5</span>
                    )}
                  </div>
                  <textarea
                    value={reviewComment}
                    onChange={(e) => setReviewComment(e.target.value)}
                    placeholder="Leave a comment (optional)"
                    rows={3}
                    maxLength={1000}
                    className="w-full rounded-lg border border-border/50 bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/50"
                  />
                  <Button
                    className="w-full gap-2"
                    onClick={handleSubmitReview}
                    disabled={submittingReview || reviewRating < 1}
                  >
                    <Star weight="bold" size={16} />
                    {submittingReview ? "Submitting..." : "Submit Review"}
                  </Button>
                </div>
              </CardContent>
            </Card>
          )}

          {(reviewSubmitted || trade?.review) && tradeStatus === "completed" && (
            <div className="flex items-center justify-center gap-2 rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-4 py-3">
              <CheckCircle weight="fill" size={18} className="text-emerald-500" />
              <span className="text-sm font-medium text-emerald-400">Review submitted</span>
            </div>
          )}

          {/* Dispute Form */}
          {showDisputeForm && (
            <Card className="border-amber-500/20 bg-amber-500/5">
              <CardContent className="pt-5 space-y-3">
                <p className="text-sm font-medium text-amber-400">Describe the issue</p>
                <textarea
                  value={disputeReason}
                  onChange={(e) => setDisputeReason(e.target.value)}
                  placeholder="Explain why you're opening this dispute..."
                  rows={3}
                  maxLength={2000}
                  className="w-full rounded-lg border border-border/50 bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-amber-500/50"
                />
                <Button
                  className="w-full gap-2 bg-amber-600 hover:bg-amber-700"
                  onClick={handleDispute}
                  disabled={disputing || !disputeReason.trim()}
                >
                  {disputing ? "Submitting..." : "Submit Dispute"}
                </Button>
              </CardContent>
            </Card>
          )}

          {/* Action Buttons */}
          {!isTerminal && (
            <div className="space-y-3">
              {(tradeStatus === "pending" || tradeStatus === "escrow_locked") && (
                <Button
                  size="lg"
                  className="w-full gap-2 bg-emerald-600 text-base font-semibold text-white hover:bg-emerald-700"
                  disabled={markingPaid}
                  onClick={handleMarkPaid}
                >
                  <Handshake weight="bold" size={20} />
                  {markingPaid ? "Marking..." : "I Paid — Met the Seller"}
                </Button>
              )}
              {tradeStatus === "payment_sent" && (
                <div className="flex items-center justify-center gap-3 rounded-xl border border-amber-500/20 bg-amber-500/5 px-5 py-4">
                  <span className="relative flex size-3">
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-amber-400 opacity-75" />
                    <span className="relative inline-flex size-3 rounded-full bg-amber-500" />
                  </span>
                  <span className="text-sm font-semibold text-amber-400">Payment marked. Waiting for seller to confirm.</span>
                </div>
              )}
              <div className="flex items-center justify-center gap-4">
                {canCancel && (
                  <button
                    onClick={handleCancel}
                    disabled={cancelling}
                    className="text-sm font-medium text-red-400 transition-colors hover:text-red-300"
                  >
                    {cancelling ? "Cancelling..." : "Cancel Trade"}
                  </button>
                )}
                {tradeStatus === "payment_sent" && (
                  <button
                    onClick={() => setShowDisputeForm(!showDisputeForm)}
                    className="text-sm font-medium text-amber-400 transition-colors hover:text-amber-300"
                  >
                    Open Dispute
                  </button>
                )}
              </div>
            </div>
          )}

          {/* Footer Note */}
          <div className="flex items-start gap-2.5 rounded-lg bg-muted/30 px-4 py-3">
            <Info weight="fill" size={16} className="mt-0.5 shrink-0 text-muted-foreground" />
            <p className="text-sm text-muted-foreground">
              Soulbound Trade NFT will be burned after completion. This NFT cannot be transferred
              and serves as proof of the in-person trade.
            </p>
          </div>
        </div>
      </div>
    </div>
  )
}
