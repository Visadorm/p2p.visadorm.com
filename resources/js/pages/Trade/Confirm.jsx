import { useState, useEffect, useRef, useCallback } from "react"
import { toast } from "sonner"
import { Link, router } from "@inertiajs/react"
import {
  Copy,
  UploadSimple,
  CheckCircle,
  Warning,
  ArrowLeft,
  IdentificationCard,
  ShieldCheck,
  FileImage,
  Lock,
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
    { label: "Escrow Locked", completed: currentIndex >= 1, current: currentIndex === 1 },
    { label: "Payment Sent", completed: currentIndex >= 2, current: currentIndex === 2 },
    { label: "Completed", completed: currentIndex >= 3, current: currentIndex === 3 },
  ]
}


export default function TradeConfirm({ tradeHash }) {
  const { isAuthenticated } = useWallet()
  const [trade, setTrade] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const [timeLeft, setTimeLeft] = useState(0)
  const [bankProof, setBankProof] = useState(null)
  const [idProof, setIdProof] = useState(null)
  const [copied, setCopied] = useState(false)
  const [markingPaid, setMarkingPaid] = useState(false)
  const [uploadingBank, setUploadingBank] = useState(false)
  const [uploadingId, setUploadingId] = useState(false)
  const [cancelling, setCancelling] = useState(false)

  // Review state
  const [reviewRating, setReviewRating] = useState(0)
  const [reviewComment, setReviewComment] = useState("")
  const [submittingReview, setSubmittingReview] = useState(false)
  const [reviewSubmitted, setReviewSubmitted] = useState(false)

  // Dispute state
  const [showDisputeForm, setShowDisputeForm] = useState(false)
  const [disputeReason, setDisputeReason] = useState("")
  const [disputing, setDisputing] = useState(false)
  const [uploadingEvidence, setUploadingEvidence] = useState(false)
  const [evidenceNote, setEvidenceNote] = useState("")
  const evidenceInputRef = useRef(null)

  const bankInputRef = useRef(null)
  const idInputRef = useRef(null)

  // Fetch trade status
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

  const handleCopy = (text) => {
    navigator.clipboard.writeText(text)
    setCopied(true)
    toast.success("Copied to clipboard")
    setTimeout(() => setCopied(false), 2000)
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

  const handleUploadBankProof = async (file) => {
    setBankProof(file)
    setUploadingBank(true)
    try {
      const res = await api.uploadBankProof(tradeHash, file)
      setTrade(res.data)
      toast.success(res.message || "Bank proof uploaded")
    } catch (err) {
      toast.error(err.message || "Failed to upload bank proof")
      setBankProof(null)
      if (bankInputRef.current) bankInputRef.current.value = ""
    } finally {
      setUploadingBank(false)
    }
  }

  const handleUploadId = async (file) => {
    setIdProof(file)
    setUploadingId(true)
    try {
      const res = await api.uploadBuyerId(tradeHash, file)
      setTrade(res.data)
      toast.success(res.message || "ID document uploaded")
    } catch (err) {
      toast.error(err.message || "Failed to upload ID")
      setIdProof(null)
      if (idInputRef.current) idInputRef.current.value = ""
    } finally {
      setUploadingId(false)
    }
  }

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
          <Skeleton className="h-32 w-full rounded-xl" />
          <Skeleton className="h-48 w-full rounded-xl" />
          <Skeleton className="h-48 w-full rounded-xl" />
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
  const isCashMeeting = ["cash_meeting", "cash meeting"].includes(paymentMethodName.toLowerCase())
  const hasBankProof = !!trade?.bank_proof_path || !!bankProof
  const hasIdProof = !!trade?.buyer_id_path || !!idProof

  // Determine if actions are available based on status
  const isUploading = uploadingBank || uploadingId
  const canMarkPaid = (tradeStatus === "pending" || tradeStatus === "escrow_locked") && !isUploading && !isCashMeeting
  const canCancel = (tradeStatus === "pending" || tradeStatus === "escrow_locked") && !isUploading
  const isTerminal = ["completed", "cancelled", "expired", "disputed"].includes(tradeStatus)

  // Payment details from trading link
  const tradingLink = trade?.trading_link || trade?.tradingLink || null

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
                <span className="text-sm font-semibold text-amber-400">USDC is being locked in escrow... This usually takes 10-30 seconds.</span>
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

          {/* Timer Card */}
          {!isTerminal && timeLeft > 0 && (
            <TradeCountdown timeLeft={timeLeft} />
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
              </div>
            </CardContent>
          </Card>

          {/* Cash Meeting: QR Code + Meeting Location */}
          {isCashMeeting && !isTerminal && (
            <>
              <NFTQRCode tradeHash={trade?.trade_hash} tokenId={trade?.nft_token_id} amountUsdc={amountUsdc} />
              {trade?.meeting_location && (
                <Card className="border-border/50">
                  <CardContent className="flex items-center gap-3 py-4">
                    <div className="flex size-10 items-center justify-center rounded-xl bg-amber-500/10">
                      <span className="text-amber-400 text-lg">📍</span>
                    </div>
                    <div>
                      <p className="text-sm font-semibold">Meeting Location</p>
                      <p className="text-sm text-muted-foreground">{trade.meeting_location}</p>
                    </div>
                  </CardContent>
                </Card>
              )}
              {tradeStatus === "escrow_locked" && (
                <Card className="border-blue-500/20 bg-blue-500/5">
                  <CardContent className="flex items-center gap-3 py-4">
                    <span className="relative flex size-3">
                      <span className="absolute inline-flex size-full animate-ping rounded-full bg-blue-400 opacity-75" />
                      <span className="relative inline-flex size-3 rounded-full bg-blue-500" />
                    </span>
                    <span className="text-sm font-semibold text-blue-400">Show this QR code to the merchant at your meeting. They will scan it to verify and release USDC.</span>
                  </CardContent>
                </Card>
              )}
            </>
          )}

          {/* Payment Details from merchant's payment method (non-cash only) */}
          {!isCashMeeting && trade?.merchant && (
            <Card className="border-border/50">
              <CardHeader>
                <CardTitle className="text-base">Payment Details</CardTitle>
                <p className="text-sm text-muted-foreground">
                  Send your fiat payment to the merchant using the details below
                </p>
              </CardHeader>
              <CardContent>
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">Payment Method</span>
                    <span className="text-sm font-semibold">{trade?.payment_method_label || paymentMethodName}</span>
                  </div>
                  {trade?.payment_method_details && typeof trade.payment_method_details === "object" && (
                    <>
                      <Separator />
                      {Object.entries(trade.payment_method_details).map(([key, value]) => (
                        <div key={key} className="flex items-center justify-between">
                          <span className="text-sm text-muted-foreground capitalize">{key.replace(/_/g, " ")}</span>
                          <div className="flex items-center gap-2">
                            <span className="font-mono text-sm font-semibold">{value}</span>
                            <button
                              onClick={() => handleCopy(String(value))}
                              className="text-muted-foreground transition-colors hover:text-foreground"
                            >
                              <Copy weight="duotone" size={14} />
                            </button>
                          </div>
                        </div>
                      ))}
                    </>
                  )}
                  <Separator />
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">Amount to Send</span>
                    <div className="flex items-center gap-2">
                      <span className="font-mono text-sm font-semibold text-emerald-400">
                        {amountFiat.toLocaleString()} {currencyCode}
                      </span>
                      <button
                        onClick={() => handleCopy(`${amountFiat}`)}
                        className="text-muted-foreground transition-colors hover:text-foreground"
                      >
                        {copied ? (
                          <CheckCircle weight="fill" size={16} className="text-emerald-500" />
                        ) : (
                          <Copy weight="duotone" size={16} />
                        )}
                      </button>
                    </div>
                  </div>
                  <Separator />
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">Reference</span>
                    <span className="font-mono text-sm font-semibold text-primary">
                      {trade?.trade_hash ? `Trade #${trade.trade_hash.slice(0, 8)}` : "---"}
                    </span>
                  </div>
                </div>
              </CardContent>
            </Card>
          )}

          {/* Upload Bank Proof (non-cash only) */}
          {!isTerminal && !isCashMeeting && (
            <Card className="border-border/50">
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                  <FileImage weight="duotone" size={20} className="text-blue-400" />
                  Upload Bank Proof
                </CardTitle>
              </CardHeader>
              <CardContent>
                <input
                  ref={bankInputRef}
                  type="file"
                  accept="image/*,.pdf"
                  className="hidden"
                  onChange={(e) => {
                    const f = e.target.files?.[0] || null
                    if (f) handleUploadBankProof(f)
                  }}
                />
                {hasBankProof ? (
                  <div className="flex items-center gap-3 rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-4">
                    <CheckCircle weight="fill" size={24} className="text-emerald-500" />
                    <div className="flex-1">
                      <p className="text-sm font-semibold text-emerald-400">
                        {bankProof?.name || "Bank proof uploaded"}
                      </p>
                      <p className="text-sm text-muted-foreground">
                        {bankProof ? `${(bankProof.size / 1024).toFixed(1)} KB` : "Uploaded successfully"}
                      </p>
                    </div>
                    {!trade?.bank_proof_path && (
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                          setBankProof(null)
                          if (bankInputRef.current) bankInputRef.current.value = ""
                        }}
                      >
                        Remove
                      </Button>
                    )}
                  </div>
                ) : (
                  <button
                    onClick={() => bankInputRef.current?.click()}
                    disabled={uploadingBank}
                    className="flex w-full flex-col items-center gap-3 rounded-xl border-2 border-dashed border-border/50 bg-muted/10 p-8 transition-colors hover:border-primary/30 hover:bg-primary/5"
                  >
                    <UploadSimple weight="duotone" size={32} className="text-muted-foreground" />
                    <div className="text-center">
                      <p className="text-sm font-medium">
                        {uploadingBank ? "Uploading..." : "Upload bank receipt"}
                      </p>
                      <p className="text-sm text-muted-foreground">
                        PNG, JPG or PDF up to 5MB
                      </p>
                    </div>
                  </button>
                )}
              </CardContent>
            </Card>
          )}

          {/* Upload ID (Optional, non-cash only) */}
          {!isTerminal && !isCashMeeting && (
            <Card className="border-border/50">
              <CardHeader>
                <div className="flex items-center justify-between">
                  <CardTitle className="flex items-center gap-2 text-base">
                    <IdentificationCard weight="duotone" size={20} className="text-purple-400" />
                    Upload ID
                  </CardTitle>
                  <Badge variant="secondary">Optional</Badge>
                </div>
              </CardHeader>
              <CardContent>
                <input
                  ref={idInputRef}
                  type="file"
                  accept="image/*,.pdf"
                  className="hidden"
                  onChange={(e) => {
                    const f = e.target.files?.[0] || null
                    if (f) handleUploadId(f)
                  }}
                />
                {hasIdProof ? (
                  <div className="flex items-center gap-3 rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-4">
                    <CheckCircle weight="fill" size={24} className="text-emerald-500" />
                    <div className="flex-1">
                      <p className="text-sm font-semibold text-emerald-400">
                        {idProof?.name || "ID document uploaded"}
                      </p>
                      <p className="text-sm text-muted-foreground">
                        {idProof ? `${(idProof.size / 1024).toFixed(1)} KB` : "Uploaded successfully"}
                      </p>
                    </div>
                    {!trade?.buyer_id_path && (
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                          setIdProof(null)
                          if (idInputRef.current) idInputRef.current.value = ""
                        }}
                      >
                        Remove
                      </Button>
                    )}
                  </div>
                ) : (
                  <button
                    onClick={() => idInputRef.current?.click()}
                    disabled={uploadingId}
                    className="flex w-full flex-col items-center gap-3 rounded-xl border-2 border-dashed border-border/50 bg-muted/10 p-8 transition-colors hover:border-primary/30 hover:bg-primary/5"
                  >
                    <IdentificationCard
                      weight="duotone"
                      size={32}
                      className="text-muted-foreground"
                    />
                    <div className="text-center">
                      <p className="text-sm font-medium">
                        {uploadingId ? "Uploading..." : "Upload government ID"}
                      </p>
                      <p className="text-sm text-muted-foreground">
                        Encrypted and only visible to the merchant
                      </p>
                    </div>
                  </button>
                )}
                <div className="mt-3 flex items-start gap-2 rounded-lg bg-muted/30 px-3 py-2.5">
                  <Lock weight="duotone" size={14} className="mt-0.5 shrink-0 text-muted-foreground" />
                  <p className="text-sm text-muted-foreground">
                    Your ID is encrypted end-to-end and automatically deleted after 24 hours
                  </p>
                </div>
              </CardContent>
            </Card>
          )}

          {/* Stake Info — during active trade */}
          {!isTerminal && Number(trade?.stake_amount) > 0 && (
            <div className="flex items-center justify-between rounded-lg bg-muted/30 px-4 py-3">
              <span className="text-sm text-muted-foreground">Anti-spam stake</span>
              <span className="font-mono text-sm font-semibold">${Number(trade.stake_amount).toLocaleString()} USDC</span>
            </div>
          )}

          {/* Trade Completed Banner */}
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

          {tradeStatus === "cancelled" && (
            <div className="flex flex-col items-center gap-3 rounded-xl border border-red-500/20 bg-red-500/5 px-5 py-6 text-center">
              <Warning weight="fill" size={40} className="text-red-400" />
              <div>
                <p className="text-lg font-semibold text-red-400">Trade Cancelled</p>
                <p className="text-sm text-muted-foreground">This trade has been cancelled</p>
                {Number(trade?.stake_amount) > 0 && (
                  <p className="text-sm text-red-400 mt-1">${Number(trade.stake_amount).toLocaleString()} USDC stake forfeited</p>
                )}
              </div>
            </div>
          )}

          {tradeStatus === "expired" && (
            <div className="flex flex-col items-center gap-3 rounded-xl border border-amber-500/20 bg-amber-500/5 px-5 py-6 text-center">
              <Warning weight="fill" size={40} className="text-amber-400" />
              <div>
                <p className="text-lg font-semibold text-amber-400">Trade Expired</p>
                <p className="text-sm text-muted-foreground">This trade expired before completion</p>
                {Number(trade?.stake_amount) > 0 && (
                  <p className="text-sm text-amber-400 mt-1">${Number(trade.stake_amount).toLocaleString()} USDC stake forfeited</p>
                )}
              </div>
            </div>
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
                          <UploadSimple weight="bold" size={18} />
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

          {/* Action Buttons */}
          {!isTerminal && (
            <div className="space-y-3">
              {canMarkPaid && (
                <Button
                  size="lg"
                  className="w-full gap-2 bg-emerald-600 text-base font-semibold text-white hover:bg-emerald-700"
                  disabled={markingPaid || isUploading}
                  onClick={handleMarkPaid}
                >
                  <CheckCircle weight="bold" size={20} />
                  {isUploading ? "Uploading..." : markingPaid ? "Marking..." : "I Paid"}
                </Button>
              )}
              {(tradeStatus === "payment_sent" || (isCashMeeting && tradeStatus === "escrow_locked")) && (
                <div className="flex items-center justify-center gap-3 rounded-xl border border-amber-500/20 bg-amber-500/5 px-5 py-4">
                  <span className="relative flex size-3">
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-amber-400 opacity-75" />
                    <span className="relative inline-flex size-3 rounded-full bg-amber-500" />
                  </span>
                  <span className="text-sm font-semibold text-amber-400">
                    Payment marked. Waiting for seller to confirm.
                  </span>
                </div>
              )}
              {canCancel && (
                <div className="flex items-center justify-center gap-4">
                  <button
                    onClick={handleCancel}
                    disabled={cancelling}
                    className="text-sm font-medium text-red-400 transition-colors hover:text-red-300"
                  >
                    {cancelling ? "Cancelling..." : "Cancel Trade"}
                  </button>
                  {(tradeStatus === "escrow_locked" || tradeStatus === "payment_sent") && (
                    <button
                      onClick={() => setShowDisputeForm(!showDisputeForm)}
                      className="text-sm font-medium text-amber-400 transition-colors hover:text-amber-300"
                    >
                      Open Dispute
                    </button>
                  )}
                </div>
              )}
              {!canCancel && (tradeStatus === "escrow_locked" || tradeStatus === "payment_sent") && (
                <div className="text-center">
                  <button
                    onClick={() => setShowDisputeForm(!showDisputeForm)}
                    className="text-sm font-medium text-amber-400 transition-colors hover:text-amber-300"
                  >
                    Open Dispute
                  </button>
                </div>
              )}
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
            </div>
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

          {tradeStatus === "completed" && (
            <>
              <ReviewCard heading="Buyer's review of seller" review={trade?.review} />
              <ReviewCard heading="Seller's review of buyer" review={trade?.merchant_review} />
            </>
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
