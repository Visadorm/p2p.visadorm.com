import { useState, useEffect, useCallback, useRef } from "react"
import { toast } from "sonner"
import { Link, router } from "@inertiajs/react"
import {
  CheckCircle,
  ShieldCheck,
  Warning,
  ArrowLeft,
  FileImage,
  IdentificationCard,
  Scales,
  DownloadSimple,
  MapPin,
  Eye,
  Star,
} from "@phosphor-icons/react"
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Separator } from "@/components/ui/separator"
import { Skeleton } from "@/components/ui/skeleton"
import ConnectWallet from "@/components/ConnectWallet"
import SiteLogo from "@/components/SiteLogo"
import TradeStatusTimeline from "@/components/TradeStatusTimeline"
import { useWallet } from "@/hooks/useWallet"
import { api } from "@/lib/api"
import { useTradeChannel } from "@/hooks/use-trade-channel"

function getStepsFromStatus(status) {
  const statusOrder = ["pending", "escrow_locked", "payment_sent", "completed"]
  const currentIndex = statusOrder.indexOf(status)

  return [
    { label: "Started", completed: currentIndex >= 0 },
    { label: "Payment Sent", completed: currentIndex >= 2, current: currentIndex === 2 },
    { label: "Confirmed", completed: currentIndex >= 3, current: currentIndex === 3 },
    { label: "Completed", completed: currentIndex >= 3, current: false },
  ]
}


export default function TradeRelease({ tradeHash }) {
  const { isAuthenticated } = useWallet()
  const [trade, setTrade] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [confirming, setConfirming] = useState(false)
  const [disputing, setDisputing] = useState(false)
  const [disputeReason, setDisputeReason] = useState("")
  const [showDisputeForm, setShowDisputeForm] = useState(false)
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
      const res = await api.getMerchantTradeDetail(tradeHash)
      const tradeData = {
        ...(res.data.trade || res.data),
        buyer_username: res.data.buyer_username,
        buyer_verified_name: res.data.buyer_verified_name,
        buyer_business_name: res.data.buyer_business_name,
      }
      setTrade(tradeData)
      return tradeData
    } catch {
      // Fallback to buyer status endpoint
      const res = await api.getTradeStatus(tradeHash)
      const tradeData = res.data.trade || res.data
      setTrade(tradeData)
      return tradeData
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

  const handleConfirm = async () => {
    setConfirming(true)
    try {
      await api.confirmTrade(tradeHash)
      toast.success("USDC released to buyer")
      // Re-fetch full trade data to update the UI
      await fetchTrade()
    } catch (err) {
      toast.error(err.message || "Failed to confirm trade")
    } finally {
      setConfirming(false)
    }
  }

  const handleDispute = async () => {
    if (!disputeReason.trim()) {
      toast.error("Please provide a reason for the dispute")
      return
    }
    setDisputing(true)
    try {
      const res = await api.openDispute(tradeHash, disputeReason)
      toast.success(res.message || "Dispute opened")
      // Refresh trade data
      await fetchTrade()
      setShowDisputeForm(false)
      setDisputeReason("")
    } catch (err) {
      toast.error(err.message || "Failed to open dispute")
    } finally {
      setDisputing(false)
    }
  }

  const handleUploadEvidence = async (file) => {
    if (!file || !trade?.dispute?.id || !trade?.trade_hash) return
    setUploadingEvidence(true)
    try {
      await api.uploadDisputeEvidence(trade.trade_hash, file, evidenceNote.trim() || undefined)
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
          <Skeleton className="h-32 w-full rounded-xl" />
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
  const feeAmount = Number(trade?.fee_amount) || 0
  const merchantPays = amountUsdc + feeAmount

  const hasBankProof = !!trade?.bank_proof_path
  const hasIdProof = !!trade?.buyer_id_path
  const bankProofStatus = trade?.bank_proof_status?.value || trade?.bank_proof_status || null
  const idProofStatus = trade?.buyer_id_status?.value || trade?.buyer_id_status || null

  const isCashMeeting = ["cash_meeting", "cash meeting"].includes((trade?.payment_method || "").toLowerCase())
  const canConfirm = tradeStatus === "payment_sent" || (isCashMeeting && (tradeStatus === "escrow_locked" || tradeStatus === "pending"))
  const canDispute = ["pending", "escrow_locked", "payment_sent"].includes(tradeStatus)
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
                <ShieldCheck weight="duotone" size={20} className="text-primary" />
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
                {trade?.buyer_verified_name && (
                  <>
                    <Separator />
                    <div className="flex items-center justify-between">
                      <span className="text-sm text-muted-foreground">Buyer Name</span>
                      <span className="text-sm font-semibold">{trade.buyer_verified_name}</span>
                    </div>
                  </>
                )}
                {trade?.buyer_business_name && (
                  <>
                    <Separator />
                    <div className="flex items-center justify-between">
                      <span className="text-sm text-muted-foreground">Buyer Business</span>
                      <span className="text-sm font-semibold">{trade.buyer_business_name}</span>
                    </div>
                  </>
                )}
                <Separator />
                <div className="flex items-center justify-between">
                  <span className="text-sm text-muted-foreground">Amount</span>
                  <div className="text-right">
                    <span className="font-mono text-sm font-semibold">
                      {amountUsdc} USDC
                    </span>
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
                  <span className="text-sm text-muted-foreground">Buyer</span>
                  <span className="text-sm font-semibold">{trade?.buyer_username || truncatedWallet}</span>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Cash Meeting: Verification link + Location */}
          {isCashMeeting && !isTerminal && (
            <Card className="border-border/50">
              <CardContent className="pt-6 space-y-4">
                <div className="flex items-center gap-3">
                  <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10">
                    <ShieldCheck weight="duotone" size={20} className="text-primary" />
                  </div>
                  <div>
                    <p className="text-sm font-semibold">Cash Meeting Trade</p>
                    <p className="text-sm text-muted-foreground">
                      Scan the buyer's QR code to verify the trade, then confirm and release.
                    </p>
                  </div>
                </div>
                {trade?.meeting_location && (
                  <div className="flex items-center gap-3 rounded-lg border border-border/50 p-3">
                    <MapPin weight="duotone" size={18} className="text-amber-400" />
                    <span className="text-sm">{trade.meeting_location}</span>
                  </div>
                )}
                {trade?.safety_note && (
                  <div className="flex items-start gap-2 rounded-lg bg-amber-500/10 border border-amber-500/20 px-3 py-2.5 mt-3">
                    <Warning weight="fill" size={14} className="mt-0.5 shrink-0 text-amber-400" />
                    <p className="text-sm text-amber-400">{trade.safety_note}</p>
                  </div>
                )}
              </CardContent>
            </Card>
          )}

          {/* Bank Proof (non-cash only) */}
          {!isCashMeeting && <Card className="border-border/50">
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle className="flex items-center gap-2 text-base">
                  <FileImage weight="duotone" size={20} className="text-blue-400" />
                  Bank Proof
                </CardTitle>
                {hasBankProof ? (
                  <span className="inline-flex items-center rounded-full bg-amber-500/15 px-2.5 py-0.5 text-sm font-medium text-amber-400">
                    Uploaded
                  </span>
                ) : (
                  <span className="inline-flex items-center rounded-full bg-muted/30 px-2.5 py-0.5 text-sm font-medium text-muted-foreground">
                    Not uploaded
                  </span>
                )}
              </div>
            </CardHeader>
            <CardContent>
              {hasBankProof ? (
                <div className="space-y-3">
                  <div className="flex items-center gap-4 rounded-xl border border-border/50 bg-muted/10 p-4">
                    <div className="flex size-16 items-center justify-center rounded-lg bg-muted/30">
                      <FileImage weight="duotone" size={28} className="text-muted-foreground" />
                    </div>
                    <div className="flex-1">
                      <p className="text-sm font-semibold">Bank proof document</p>
                      <p className="text-sm text-muted-foreground">
                        Status: {bankProofStatus || "Uploaded"}
                      </p>
                      {trade?.bank_proof_path && (
                        <p className="text-sm text-muted-foreground mt-1 font-mono truncate">
                          {trade.bank_proof_path.split("/").pop()}
                        </p>
                      )}
                    </div>
                    <Badge variant="outline" className="gap-1.5 text-emerald-400 border-emerald-500/30">
                      <CheckCircle weight="fill" size={14} />
                      Uploaded
                    </Badge>
                  </div>
                  <div className="flex gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      className="gap-1.5"
                      onClick={async () => {
                        try {
                          const res = await api.downloadBankProof(tradeHash)
                          if (!res.ok) throw new Error("Download failed")
                          const blob = await res.blob()
                          const url = URL.createObjectURL(blob)
                          window.open(url, "_blank")
                        } catch { toast.error("Failed to load bank proof") }
                      }}
                    >
                      <Eye size={16} /> View
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      className="gap-1.5"
                      onClick={async () => {
                        try {
                          const res = await api.downloadBankProof(tradeHash)
                          if (!res.ok) throw new Error("Download failed")
                          const blob = await res.blob()
                          const url = URL.createObjectURL(blob)
                          const a = document.createElement("a")
                          a.href = url
                          a.download = trade?.bank_proof_path?.split("/").pop() || "bank-proof"
                          a.click()
                          URL.revokeObjectURL(url)
                        } catch { toast.error("Failed to download bank proof") }
                      }}
                    >
                      <DownloadSimple size={16} /> Download
                    </Button>
                  </div>
                </div>
              ) : (
                <div className="flex items-center justify-center rounded-xl border border-border/50 bg-muted/10 p-8">
                  <p className="text-sm text-muted-foreground">Buyer has not uploaded bank proof yet</p>
                </div>
              )}
            </CardContent>
          </Card>}

          {/* ID Verification (non-cash only) */}
          {!isCashMeeting && <Card className="border-border/50">
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle className="flex items-center gap-2 text-base">
                  <IdentificationCard weight="duotone" size={20} className="text-purple-400" />
                  ID Verification
                </CardTitle>
                {hasIdProof ? (
                  <span className="inline-flex items-center rounded-full bg-blue-500/15 px-2.5 py-0.5 text-sm font-medium text-blue-400">
                    Submitted
                  </span>
                ) : (
                  <span className="inline-flex items-center rounded-full bg-muted/30 px-2.5 py-0.5 text-sm font-medium text-muted-foreground">
                    Not submitted
                  </span>
                )}
              </div>
            </CardHeader>
            <CardContent>
              {hasIdProof ? (
                <div className="space-y-3">
                  <div className="flex items-center gap-4 rounded-xl border border-border/50 bg-muted/10 p-4">
                    <div className="flex size-16 items-center justify-center rounded-lg bg-muted/30">
                      <IdentificationCard weight="duotone" size={28} className="text-muted-foreground" />
                    </div>
                    <div className="flex-1">
                      <p className="text-sm font-semibold">ID document</p>
                      <p className="text-sm text-muted-foreground">
                        Status: {idProofStatus || "Submitted"} - Encrypted end-to-end
                      </p>
                      {trade?.buyer_id_path && (
                        <p className="text-sm text-muted-foreground mt-1 font-mono truncate">
                          {trade.buyer_id_path.split("/").pop()}
                        </p>
                      )}
                    </div>
                    <Badge variant="outline" className="gap-1.5 text-blue-400 border-blue-500/30">
                      <CheckCircle weight="fill" size={14} />
                      Submitted
                    </Badge>
                  </div>
                  <div className="flex gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      className="gap-1.5"
                      onClick={async () => {
                        try {
                          const res = await api.downloadBuyerId(tradeHash)
                          if (!res.ok) throw new Error("Download failed")
                          const blob = await res.blob()
                          const url = URL.createObjectURL(blob)
                          window.open(url, "_blank")
                        } catch { toast.error("Failed to load ID document") }
                      }}
                    >
                      <Eye size={16} /> View
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      className="gap-1.5"
                      onClick={async () => {
                        try {
                          const res = await api.downloadBuyerId(tradeHash)
                          if (!res.ok) throw new Error("Download failed")
                          const blob = await res.blob()
                          const url = URL.createObjectURL(blob)
                          const a = document.createElement("a")
                          a.href = url
                          a.download = trade?.buyer_id_path?.split("/").pop() || "buyer-id"
                          a.click()
                          URL.revokeObjectURL(url)
                        } catch { toast.error("Failed to download ID document") }
                      }}
                    >
                      <DownloadSimple size={16} /> Download
                    </Button>
                  </div>
                </div>
              ) : (
                <div className="flex items-center justify-center rounded-xl border border-border/50 bg-muted/10 p-8">
                  <p className="text-sm text-muted-foreground">Buyer has not submitted ID verification</p>
                </div>
              )}
            </CardContent>
          </Card>}

          {/* Platform Fee */}
          <Card className="border-border/50">
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <Scales weight="duotone" size={20} className="text-amber-400" />
                Platform Fee
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-muted-foreground">Buyer Receives</span>
                  <span className="font-mono text-sm font-semibold text-emerald-400">
                    {amountUsdc.toFixed(2)} USDC
                  </span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-sm text-muted-foreground">Platform Fee (0.2%)</span>
                  <span className="font-mono text-sm font-semibold text-amber-400">
                    {feeAmount.toFixed(2)} USDC
                  </span>
                </div>
                <Separator />
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">Deducted from Escrow</span>
                  <span className="font-mono text-base font-bold text-foreground">
                    {merchantPays.toFixed(2)} USDC
                  </span>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Dispute Form */}
          {showDisputeForm && (
            <Card className="border-red-500/20">
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base text-red-400">
                  <Warning weight="duotone" size={20} />
                  Open Dispute
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <textarea
                  value={disputeReason}
                  onChange={(e) => setDisputeReason(e.target.value)}
                  placeholder="Describe the reason for the dispute..."
                  className="w-full rounded-lg border border-border/50 bg-muted/10 p-3 text-sm placeholder:text-muted-foreground focus:border-primary focus:outline-none min-h-[100px] resize-none"
                />
                <div className="flex gap-3">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => {
                      setShowDisputeForm(false)
                      setDisputeReason("")
                    }}
                  >
                    Cancel
                  </Button>
                  <Button
                    variant="destructive"
                    size="sm"
                    disabled={disputing || !disputeReason.trim()}
                    onClick={handleDispute}
                  >
                    {disputing ? "Submitting..." : "Submit Dispute"}
                  </Button>
                </div>
              </CardContent>
            </Card>
          )}

          {/* Dispute Evidence Upload */}
          {trade?.dispute && (
            <Card className="border-amber-500/20 bg-amber-500/5">
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base text-amber-400">
                  <Warning weight="fill" size={20} />
                  {tradeStatus === "disputed" ? "Trade Under Dispute — Submit Your Evidence" : `Dispute ${(trade.dispute.status || "resolved").replace(/_/g, " ")}`}
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
                        Upload screenshots, receipts, or chat logs to support your case. The admin will review your evidence.
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
                      <p className="text-sm font-medium">Evidence & Notes ({trade.dispute.evidence.length})</p>
                      {trade.dispute.evidence.map((e, i) => {
                        const isBuyer = e.uploaded_by && e.uploaded_by.toLowerCase() === trade?.buyer_wallet?.toLowerCase()
                        const isAdmin = e.uploaded_by === "admin"
                        const role = isAdmin ? "Admin" : isBuyer ? "Buyer" : "Seller"
                        const roleColor = isAdmin ? "text-purple-400" : isBuyer ? "text-blue-400" : "text-emerald-400"
                        return (
                          <div key={i} className="rounded-lg bg-muted/20 px-3 py-2 space-y-1">
                            <div className="flex items-center justify-between">
                              <span className={`text-xs font-semibold ${roleColor}`}>{role}</span>
                              <span className="text-xs text-muted-foreground">{e.uploaded_at ? new Date(e.uploaded_at).toLocaleDateString() : ""}</span>
                            </div>
                            {e.original_name && e.original_name !== "Evidence Request" && (
                              <p className="text-sm">{e.original_name}</p>
                            )}
                            {e.note && (
                              <p className="text-sm text-muted-foreground">{e.note}</p>
                            )}
                          </div>
                        )
                      })}
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
                          <FileImage weight="bold" size={18} />
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

          {/* Trade Completed Banner */}
          {tradeStatus === "completed" && (
            <div className="flex flex-col items-center gap-3 rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-5 py-6 text-center">
              <CheckCircle weight="fill" size={40} className="text-emerald-500" />
              <div>
                <p className="text-lg font-semibold text-emerald-400">Trade Completed</p>
                <p className="text-sm text-muted-foreground">USDC has been released to the buyer</p>
              </div>
            </div>
          )}

          {/* Review Form — shows after trade completes */}
          {tradeStatus === "completed" && !reviewSubmitted && !trade?.merchant_review && (
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

          {tradeStatus === "completed" && (
            <>
              <ReviewCard heading="Buyer's review of seller" review={trade?.review} />
              <ReviewCard heading="Seller's review of buyer" review={trade?.merchant_review} />
            </>
          )}

          {/* Action Buttons */}
          {!isTerminal && (
            <div className="space-y-3">
              {canConfirm && (
                <Button
                  size="lg"
                  className="w-full gap-2 bg-emerald-600 text-base font-semibold text-white hover:bg-emerald-700"
                  disabled={confirming}
                  onClick={handleConfirm}
                >
                  <CheckCircle weight="bold" size={20} />
                  {confirming ? "Releasing..." : "Approve & Release USDC"}
                </Button>
              )}
              {canDispute && !showDisputeForm && (
                <Button
                  variant="outline"
                  size="lg"
                  className="w-full gap-2 text-red-400 border-red-500/20 hover:bg-red-500/10 hover:text-red-400"
                  onClick={() => setShowDisputeForm(true)}
                >
                  <Warning weight="bold" size={20} />
                  Open Dispute
                </Button>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

function ReviewCard({ heading, review }) {
  if (!review) return null
  return (
    <div className="rounded-xl border border-border/50 bg-card p-4">
      <div className="flex items-center justify-between gap-3">
        <div className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{heading}</div>
        <div className="flex items-center gap-0.5">
          {[1, 2, 3, 4, 5].map(s => (
            <Star key={s} weight={s <= review.rating ? "fill" : "regular"} size={16}
              className={s <= review.rating ? "text-amber-400" : "text-muted-foreground/30"} />
          ))}
          <span className="ml-1 text-xs text-muted-foreground">{review.rating}/5</span>
        </div>
      </div>
      {review.comment && (
        <p className="mt-2 whitespace-pre-line text-sm text-foreground">{review.comment}</p>
      )}
      <div className="mt-2 text-xs text-muted-foreground/70">
        Submitted {review.created_at ? new Date(review.created_at).toLocaleString() : ""}
      </div>
    </div>
  )
}
