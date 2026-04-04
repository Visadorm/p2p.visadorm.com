import { useState, useEffect } from "react"
import { Link } from "@inertiajs/react"
import {
  ShieldCheck,
  CheckCircle,
  Warning,
  Wallet,
  CurrencyDollar,
  MapPin,
  Clock,
} from "@phosphor-icons/react"
import { Card, CardContent } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Skeleton } from "@/components/ui/skeleton"
import SiteLogo from "@/components/SiteLogo"

const STATUS_CONFIG = {
  pending: { label: "Pending", color: "bg-yellow-500/15 text-yellow-400" },
  escrow_locked: { label: "Escrow Locked", color: "bg-blue-500/15 text-blue-400" },
  payment_sent: { label: "Payment Sent", color: "bg-purple-500/15 text-purple-400" },
  completed: { label: "Completed", color: "bg-emerald-500/15 text-emerald-400" },
  disputed: { label: "Disputed", color: "bg-red-500/15 text-red-400" },
  cancelled: { label: "Cancelled", color: "bg-muted/30 text-muted-foreground" },
  expired: { label: "Expired", color: "bg-muted/30 text-muted-foreground" },
}

export default function Verify({ tradeHash }) {
  const [trade, setTrade] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    async function fetchTrade() {
      try {
        const res = await fetch(`/api/trade/${tradeHash}/verify`)
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || "Trade not found")
        setTrade(data.data)
      } catch (err) {
        setError(err.message)
      } finally {
        setLoading(false)
      }
    }
    fetchTrade()
  }, [tradeHash])

  const status = trade?.status?.value || trade?.status || "pending"
  const statusConfig = STATUS_CONFIG[status] || STATUS_CONFIG.pending
  const isEscrowed = ["escrow_locked", "payment_sent", "completed"].includes(status)

  return (
    <div className="min-h-screen bg-background">
      <header className="border-b border-border/50 bg-background/80 backdrop-blur-xl">
        <div className="mx-auto flex h-16 max-w-6xl items-center px-4 lg:px-6">
          <Link href="/"><SiteLogo /></Link>
        </div>
      </header>

      <div className="mx-auto max-w-md px-4 py-8 lg:px-6">
        {loading && (
          <div className="space-y-4">
            <Skeleton className="h-8 w-48 mx-auto" />
            <Skeleton className="h-64 w-full rounded-xl" />
          </div>
        )}

        {error && (
          <Card className="border-red-500/20 bg-red-500/5">
            <CardContent className="flex flex-col items-center gap-4 py-12">
              <Warning weight="fill" size={48} className="text-red-400" />
              <p className="text-lg font-semibold text-red-400">{error}</p>
            </CardContent>
          </Card>
        )}

        {trade && (
          <div className="space-y-6">
            {/* Header */}
            <div className="text-center space-y-2">
              <div className="inline-flex items-center gap-2 rounded-full bg-primary/10 px-4 py-2">
                <ShieldCheck weight="fill" size={20} className="text-primary" />
                <span className="text-sm font-semibold text-primary">Trade Verification</span>
              </div>
              <p className="text-sm text-muted-foreground">
                This trade is recorded on the Base blockchain
              </p>
            </div>

            {/* Status */}
            <Card className="border-border/50">
              <CardContent className="pt-6">
                <div className="flex flex-col items-center gap-4">
                  {isEscrowed ? (
                    <CheckCircle weight="fill" size={56} className="text-emerald-500" />
                  ) : (
                    <Warning weight="fill" size={56} className="text-yellow-500" />
                  )}
                  <Badge className={`text-sm px-3 py-1 ${statusConfig.color}`}>
                    {statusConfig.label}
                  </Badge>
                  {status === "completed" ? (
                    <p className="text-sm text-emerald-400 font-medium">
                      USDC has been released to the buyer
                    </p>
                  ) : status === "disputed" ? (
                    <p className="text-sm text-red-400 font-medium">
                      USDC is held in escrow pending dispute resolution
                    </p>
                  ) : isEscrowed ? (
                    <p className="text-sm text-emerald-400 font-medium">
                      USDC is locked in escrow smart contract
                    </p>
                  ) : null}
                </div>
              </CardContent>
            </Card>

            {/* Trade Details */}
            <Card className="border-border/50">
              <CardContent className="pt-6 space-y-4">
                <div className="flex items-center gap-3">
                  <CurrencyDollar weight="duotone" size={20} className="text-primary" />
                  <div className="flex-1">
                    <p className="text-xs text-muted-foreground">Amount</p>
                    <p className="text-lg font-bold">{Number(trade.amount_usdc)} USDC</p>
                  </div>
                  {trade.amount_fiat && (
                    <p className="text-sm text-muted-foreground">
                      ≈ {Number(trade.amount_fiat).toLocaleString()} {trade.currency_code}
                    </p>
                  )}
                </div>

                <div className="flex items-center gap-3">
                  <Wallet weight="duotone" size={20} className="text-blue-400" />
                  <div className="flex-1">
                    <p className="text-xs text-muted-foreground">Buyer Wallet</p>
                    <p className="font-mono text-sm font-semibold">{trade.buyer_wallet}</p>
                  </div>
                </div>

                <div className="flex items-center gap-3">
                  <Wallet weight="duotone" size={20} className="text-purple-400" />
                  <div className="flex-1">
                    <p className="text-xs text-muted-foreground">Merchant</p>
                    <p className="text-sm font-semibold">{trade.merchant_name || "Merchant"}</p>
                  </div>
                </div>

                {trade.meeting_location && (
                  <div className="flex items-center gap-3">
                    <MapPin weight="duotone" size={20} className="text-amber-400" />
                    <div className="flex-1">
                      <p className="text-xs text-muted-foreground">Meeting Location</p>
                      <p className="text-sm font-semibold">{trade.meeting_location}</p>
                    </div>
                  </div>
                )}

                {trade.nft_token_id && (
                  <div className="flex items-center gap-3">
                    <ShieldCheck weight="duotone" size={20} className="text-emerald-400" />
                    <div className="flex-1">
                      <p className="text-xs text-muted-foreground">Soulbound NFT</p>
                      <p className="text-sm font-semibold">Minted (on-chain proof)</p>
                    </div>
                  </div>
                )}

                <div className="flex items-center gap-3">
                  <Clock weight="duotone" size={20} className="text-muted-foreground" />
                  <div className="flex-1">
                    <p className="text-xs text-muted-foreground">Trade Hash</p>
                    <p className="font-mono text-xs text-muted-foreground break-all">{trade.trade_hash}</p>
                  </div>
                </div>
              </CardContent>
            </Card>

            <p className="text-center text-xs text-muted-foreground">
              This is a public verification page. No login required.
            </p>
          </div>
        )}
      </div>
    </div>
  )
}
