import { useState, useEffect, useCallback } from "react"
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
  const meetingLocation = trade?.meeting_location || "Location not set"
  const tokenId = trade?.nft_token_id || "N/A"
  const escrowAmount = amountUsdc

  const canCancel = tradeStatus === "pending" || tradeStatus === "escrow_locked"
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
                  <span className="text-sm text-muted-foreground">Meeting Location</span>
                  <div className="flex items-center gap-1.5">
                    <MapPin weight="fill" size={14} className="text-primary" />
                    <span className="text-sm font-medium">{meetingLocation}</span>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Escrow Status Banner */}
          {!isTerminal && (
            <div className="flex flex-wrap items-center gap-3 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-5 py-4">
              <ShieldCheck weight="fill" size={28} className="text-emerald-500 shrink-0" />
              <div className="flex-1 min-w-0">
                <p className="text-sm font-semibold text-emerald-400">USDC Locked in Escrow</p>
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

          {/* Completed Banner */}
          {tradeStatus === "completed" && (
            <div className="flex flex-col items-center gap-3 rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-5 py-6 text-center">
              <CheckCircle weight="fill" size={40} className="text-emerald-500" />
              <div>
                <p className="text-lg font-semibold text-emerald-400">Trade Completed</p>
                <p className="text-sm text-muted-foreground">USDC has been released to your wallet</p>
              </div>
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
                  <span className="text-sm font-semibold text-amber-400">Waiting for merchant to confirm and release USDC...</span>
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
                {!isTerminal && (
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
