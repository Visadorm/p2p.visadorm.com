import { useState, useEffect } from "react"
import DashboardLayout from "@/layouts/DashboardLayout"
import { api } from "@/lib/api"
import { useWallet } from "@/hooks/useWallet"
import {
  ShieldWarning,
  Medal,
  Wallet,
  CheckCircle,
  TrendUp,
  ShieldCheck,
} from "@phosphor-icons/react"
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from "@/components/ui/card"
import { Separator } from "@/components/ui/separator"
import { Skeleton } from "@/components/ui/skeleton"

function truncateAddress(addr) {
  if (!addr) return "—"
  return `${addr.slice(0, 6)}...${addr.slice(-4)}`
}

const securityTips = [
  "Never share your private keys or seed phrase with anyone.",
  "Always verify the buyer's payment in your bank account before releasing crypto.",
  "Enable two-factor authentication on all linked accounts.",
]

export default function Security() {
  const { isAuthenticated, merchant, address } = useWallet()
  const [dashboardData, setDashboardData] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!isAuthenticated) return
    api.getDashboard()
      .then((res) => setDashboardData(res.data))
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [isAuthenticated])

  if (loading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-8 w-60" />
        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
          <Skeleton className="h-64 rounded-xl" />
          <Skeleton className="h-64 rounded-xl" />
          <Skeleton className="h-64 rounded-xl" />
          <Skeleton className="h-64 rounded-xl" />
        </div>
      </div>
    )
  }

  const stats = dashboardData?.stats || {}
  const dashMerchant = dashboardData?.merchant || {}
  const disputeRate = stats.dispute_rate || merchant?.dispute_rate || 0
  const totalCompleted = stats.total_trades || merchant?.total_trades || 0
  const totalTrades = totalCompleted
  const completionRate = stats.completion_rate || merchant?.completion_rate || 0

  // Rank from dashboard merchant (which eager-loads rank relation)
  const rankName = dashMerchant?.rank?.name || merchant?.rank?.name || "New Member"

  // Progress: use rank thresholds from the plan
  const RANK_THRESHOLDS = [
    { name: "Junior Member", min: 21 },
    { name: "Senior Member", min: 101 },
    { name: "Hero Merchant", min: 1000 },
    { name: "Elite Merchant", min: 10000 },
    { name: "Legendary Merchant", min: 999999 },
  ]
  const currentRankIdx = RANK_THRESHOLDS.findIndex(r => r.name === rankName)
  const nextRank = currentRankIdx >= 0 && currentRankIdx < RANK_THRESHOLDS.length - 1
    ? RANK_THRESHOLDS[currentRankIdx + 1]
    : currentRankIdx < 0 ? RANK_THRESHOLDS[0] : null
  const nextLevelTrades = nextRank?.min || 10000
  const nextLevel = nextRank?.name || "Elite Merchant"
  const progress = nextLevelTrades > 0 ? Math.min(100, Math.round((totalTrades / nextLevelTrades) * 100)) : 0

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-xl font-bold tracking-tight">Security & Account</h2>
        <p className="mt-1 text-sm text-muted-foreground">
          Your trade health, merchant level, and wallet authentication status
        </p>
      </div>

      <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
        {/* Trade Health */}
        <Card className="border-border/50">
          <CardHeader>
            <div className="flex items-center gap-3">
              <div className="flex size-10 items-center justify-center rounded-xl bg-emerald-500/10">
                <ShieldWarning className="size-5 text-emerald-500" weight="duotone" />
              </div>
              <div>
                <CardTitle>Trade Health</CardTitle>
                <CardDescription>Dispute and completion metrics</CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            <div className="space-y-0">
              {[
                { label: "Dispute Rate", value: `${Number(disputeRate).toFixed(1)}%`, color: "text-emerald-500" },
                { label: "Total Trades", value: Number(totalCompleted).toLocaleString(), color: "" },
                { label: "Completion Rate", value: `${Number(completionRate).toFixed(1)}%`, color: "text-emerald-500" },
                { label: "Total Volume", value: `$${Number(stats.total_volume || merchant?.total_volume || 0).toLocaleString()}`, color: "" },
              ].map((item, i) => (
                <div key={item.label}>
                  <div className="flex items-center justify-between rounded-lg px-3 py-3 transition-colors hover:bg-muted/20">
                    <span className="text-sm text-muted-foreground">{item.label}</span>
                    <span className={`font-mono text-sm font-semibold ${item.color}`}>{item.value}</span>
                  </div>
                  {i < 3 && <Separator className="opacity-30" />}
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        {/* Merchant Level */}
        <Card className="border-border/50">
          <CardHeader>
            <div className="flex items-center gap-3">
              <div className="flex size-10 items-center justify-center rounded-xl bg-amber-500/10">
                <Medal className="size-5 text-amber-500" weight="duotone" />
              </div>
              <div>
                <CardTitle>Merchant Level</CardTitle>
                <CardDescription>Your rank and progress</CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <span className="inline-flex items-center rounded-full bg-amber-500/15 px-3 py-1 text-sm font-bold text-amber-500">{rankName}</span>
              </div>
              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-muted-foreground">Progress to {nextLevel}</span>
                  <span className="font-mono text-sm font-semibold">{progress}%</span>
                </div>
                <div className="h-2.5 w-full overflow-hidden rounded-full bg-muted">
                  <div className="h-full rounded-full bg-primary transition-all" style={{ width: `${progress}%` }} />
                </div>
                <div className="flex items-center justify-between">
                  <span className="font-mono text-sm text-muted-foreground">{Number(totalTrades).toLocaleString()} trades</span>
                  <span className="font-mono text-sm text-muted-foreground">{Number(nextLevelTrades).toLocaleString()} needed</span>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Wallet Auth */}
        <Card className="border-border/50">
          <CardHeader>
            <div className="flex items-center gap-3">
              <div className="flex size-10 items-center justify-center rounded-xl bg-blue-500/10">
                <Wallet className="size-5 text-blue-500" weight="duotone" />
              </div>
              <div>
                <CardTitle>Wallet Authentication</CardTitle>
                <CardDescription>Connected wallet and session</CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            <div className="space-y-0">
              {[
                { label: "Connected Wallet", value: truncateAddress(address || merchant?.wallet_address), mono: true },
                { label: "Network", value: "Base (Coinbase L2)", badge: true },
                { label: "Session", value: isAuthenticated ? "Active" : "Inactive", active: isAuthenticated },
              ].map((item, i) => (
                <div key={item.label}>
                  <div className="flex items-center justify-between rounded-lg px-3 py-3 transition-colors hover:bg-muted/20">
                    <span className="text-sm text-muted-foreground">{item.label}</span>
                    {item.active ? (
                      <div className="flex items-center gap-1.5">
                        <CheckCircle className="size-4 text-emerald-500" weight="fill" />
                        <span className="text-sm font-semibold text-emerald-500">{item.value}</span>
                      </div>
                    ) : item.badge ? (
                      <span className="inline-flex items-center rounded-full bg-blue-500/15 px-2.5 py-0.5 text-sm font-medium text-blue-500">{item.value}</span>
                    ) : (
                      <span className={`text-sm font-semibold ${item.mono ? "font-mono" : ""}`}>{item.value}</span>
                    )}
                  </div>
                  {i < 2 && <Separator className="opacity-30" />}
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        {/* Security Tips */}
        <Card className="border-border/50">
          <CardHeader>
            <div className="flex items-center gap-3">
              <div className="flex size-10 items-center justify-center rounded-xl bg-emerald-500/10">
                <ShieldCheck className="size-5 text-emerald-500" weight="duotone" />
              </div>
              <div>
                <CardTitle>Security Tips</CardTitle>
                <CardDescription>Best practices for safe trading</CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {securityTips.map((tip, i) => (
                <div key={i} className="flex items-start gap-3 rounded-lg bg-muted/20 px-4 py-3">
                  <div className="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-500/10">
                    <ShieldCheck className="size-3.5 text-emerald-500" weight="fill" />
                  </div>
                  <span className="text-sm leading-relaxed text-muted-foreground">{tip}</span>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}

Security.layout = (page) => <DashboardLayout>{page}</DashboardLayout>
