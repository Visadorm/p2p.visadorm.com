import { useState, useEffect } from "react"
import { router } from "@inertiajs/react"
import DashboardLayout from "@/layouts/DashboardLayout"
import { BalanceSkeleton, StatsSkeleton, TradesListSkeleton, CardSkeleton } from "@/components/Skeletons"
import { api } from "@/lib/api"
import { useWallet } from "@/hooks/useWallet"
import { toast } from "sonner"
import {
  ShieldCheck,
  ArrowsLeftRight,
  CheckCircle,
  ChartLineUp,
  Plus,
  Wallet,
  GearSix,
  Link as LinkIcon,
  CreditCard,
  Eye,
  ArrowDown,
  ArrowUp,
} from "@phosphor-icons/react"
import EscrowBalanceDisplay from "@/components/EscrowBalanceDisplay"
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Separator } from "@/components/ui/separator"

const iconColorMap = {
  emerald: "bg-emerald-500/10 text-emerald-500",
  blue: "bg-blue-500/10 text-blue-500",
  amber: "bg-amber-500/10 text-amber-500",
  purple: "bg-purple-500/10 text-purple-500",
}

const STATUS_STYLES = {
  completed: { label: "Completed", className: "bg-emerald-500/15 text-emerald-500" },
  payment_sent: { label: "Payment Sent", className: "bg-blue-500/15 text-blue-500" },
  pending: { label: "Pending", className: "bg-amber-500/15 text-amber-500" },
  disputed: { label: "Disputed", className: "bg-red-500/15 text-red-500" },
  escrow_locked: { label: "Escrow Locked", className: "bg-purple-500/15 text-purple-500" },
  cancelled: { label: "Cancelled", className: "bg-muted-foreground/15 text-muted-foreground" },
  expired: { label: "Expired", className: "bg-muted-foreground/15 text-muted-foreground" },
}

function statusBadge(status) {
  const style = STATUS_STYLES[status] || { label: status, className: "bg-muted text-muted-foreground" }
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-sm font-medium ${style.className}`}>
      {style.label}
    </span>
  )
}

function truncateHash(hash) {
  if (!hash) return "—"
  return `${hash.slice(0, 8)}...${hash.slice(-4)}`
}

function formatVolume(volume) {
  const num = Number(volume) || 0
  if (num >= 1_000_000) return `$${(num / 1_000_000).toFixed(1)}M`
  if (num >= 1_000) return `$${(num / 1_000).toFixed(0)}K`
  return `$${num.toFixed(0)}`
}

function timeAgo(dateStr) {
  if (!dateStr) return "—"
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return "just now"
  if (mins < 60) return `${mins} min ago`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  return `${days}d ago`
}

export default function Dashboard() {
  const { isAuthenticated } = useWallet()
  const [loading, setLoading] = useState(true)
  const [dashboardData, setDashboardData] = useState(null)
  const [recentTrades, setRecentTrades] = useState([])
  const [error, setError] = useState(false)


  useEffect(() => {
    if (!isAuthenticated) return

    const fetchData = async () => {
      try {
        const [dashRes, tradesRes] = await Promise.all([
          api.getDashboard(),
          api.getMerchantTrades("per_page=5"),
        ])
        setDashboardData(dashRes.data)
        setRecentTrades(tradesRes.data?.data || tradesRes.data || [])
      } catch (err) {
        toast.error("Failed to load dashboard")
        setError(true)
      } finally {
        setLoading(false)
      }
    }

    fetchData()
  }, [isAuthenticated])

  if (error) {
    return (
      <div className="flex flex-col items-center justify-center py-20 text-center">
        <ArrowsLeftRight className="size-16 text-muted-foreground/20 mb-4" weight="duotone" />
        <p className="text-lg font-semibold mb-2">Failed to load dashboard</p>
        <p className="text-sm text-muted-foreground mb-6">Something went wrong. Please try again.</p>
        <Button onClick={() => { setError(false); setLoading(true); location.reload() }}>
          Retry
        </Button>
      </div>
    )
  }

  if (loading || !dashboardData) {
    return (
      <div className="space-y-6">
        <BalanceSkeleton />
        <StatsSkeleton />
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <div className="lg:col-span-2"><TradesListSkeleton /></div>
          <CardSkeleton />
        </div>
      </div>
    )
  }

  const { stats, active_trades_count, open_disputes_count, escrow_balance, locked_balance } = dashboardData
  const totalEscrow = Number(escrow_balance || 0)
  const locked = Number(locked_balance || 0)
  const available = Math.max(totalEscrow - locked, 0)
  const statCards = [
    { title: "Reliability", value: Number(stats.reliability_score || 0).toFixed(1), suffix: "/10", icon: ShieldCheck, color: "emerald" },
    { title: "Total Trades", value: Number(stats.total_trades || 0).toLocaleString(), suffix: "", icon: ArrowsLeftRight, color: "blue" },
    { title: "Completion", value: Number(stats.completion_rate || 0).toFixed(0), suffix: "%", icon: CheckCircle, color: "amber" },
    { title: "Volume", value: formatVolume(stats.total_volume), suffix: " USDC", icon: ChartLineUp, color: "purple" },
  ]

  const quickActions = [
    { label: "Deposit USDC", icon: Plus, desc: "Add funds to escrow", href: "/liquidity" },
    { label: "Withdraw", icon: Wallet, desc: "Withdraw from escrow", href: "/liquidity" },
    { label: "Settings", icon: GearSix, desc: "Account preferences", href: "/settings" },
    { label: "New Link", icon: LinkIcon, desc: "Create trading link", href: "/links" },
    { label: "Add Payment", icon: CreditCard, desc: "Payment method", href: "/payments" },
    { label: "View Trades", icon: Eye, desc: "All transactions", href: "/trades" },
  ]

  return (
    <div className="space-y-6">
      {/* Hero Balance */}
      <Card className="border-border/50 bg-gradient-to-br from-card via-card to-primary/5">
        <CardContent className="pt-6">
          <div className="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
            <div className="space-y-4">
              <div className="flex items-center gap-3">
                <div className="flex size-12 items-center justify-center rounded-xl bg-primary/10">
                  <Wallet className="size-6 text-primary" weight="duotone" />
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">Portfolio / Escrow Balance</p>
                  <p className="font-mono text-2xl sm:text-4xl font-bold tracking-tight">
                    ${available.toLocaleString()} <span className="text-lg text-muted-foreground">USDC</span>
                  </p>
                </div>
              </div>
              <EscrowBalanceDisplay
                available={available}
                locked={locked}
                totalVolume={totalEscrow}
                activeTradesCount={active_trades_count}
                openDisputesCount={open_disputes_count}
              />
            </div>
            <div className="flex flex-col gap-3 sm:flex-row">
              <Button size="lg" className="gap-2" onClick={() => router.visit("/liquidity")}>
                <ArrowDown className="size-5" weight="bold" /> Deposit
              </Button>
              <Button variant="outline" size="lg" className="gap-2" onClick={() => router.visit("/liquidity")}>
                <ArrowUp className="size-5" weight="bold" /> Withdraw
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Stats Row */}
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {statCards.map((stat, index) => {
          const delayClass = ["", "animate-delay-100", "animate-delay-200", "animate-delay-300"][index] || ""
          return (
            <Card key={stat.title} className={`border-border/50 opacity-0 animate-slide-up ${delayClass}`}>
              <CardContent className="pt-6">
                <div className="flex items-start justify-between">
                  <div className="space-y-2">
                    <p className="text-sm text-muted-foreground">{stat.title}</p>
                    <div className="flex items-baseline gap-1">
                      <span className="font-mono text-xl sm:text-3xl font-bold tracking-tight">{stat.value}</span>
                      {stat.suffix && <span className="text-lg text-muted-foreground">{stat.suffix}</span>}
                    </div>
                  </div>
                  <div className={`flex size-11 items-center justify-center rounded-xl ${iconColorMap[stat.color]}`}>
                    <stat.icon className="size-6" weight="duotone" />
                  </div>
                </div>
              </CardContent>
            </Card>
          )
        })}
      </div>

      {/* Recent Trades + Quick Actions */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <Card className="border-border/50 lg:col-span-2">
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="text-lg">Recent Trades</CardTitle>
                <CardDescription>Your latest P2P transactions</CardDescription>
              </div>
              <Button variant="outline" size="sm" onClick={() => router.visit("/trades")}>View All</Button>
            </div>
          </CardHeader>
          <CardContent className="overflow-x-auto">
            {recentTrades.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-12 text-center">
                <ArrowsLeftRight className="size-12 text-muted-foreground/30 mb-3" weight="duotone" />
                <p className="text-muted-foreground">No trades yet</p>
                <p className="text-sm text-muted-foreground/60">Your trade history will appear here</p>
              </div>
            ) : (
              <>
                <div className="min-w-[500px] grid grid-cols-[auto_1fr_auto_auto_auto] items-center gap-4 rounded-lg bg-muted/30 px-4 py-2.5">
                  <span className="text-sm font-medium text-muted-foreground">Trade ID</span>
                  <span className="text-sm font-medium text-muted-foreground">Buyer</span>
                  <span className="text-right text-sm font-medium text-muted-foreground">Amount</span>
                  <span className="text-sm font-medium text-muted-foreground">Status</span>
                  <span className="text-right text-sm font-medium text-muted-foreground">Time</span>
                </div>
                <div className="mt-1 min-w-[500px]">
                  {recentTrades.map((trade, index) => (
                    <div key={trade.id || index} className="grid grid-cols-[auto_1fr_auto_auto_auto] items-center gap-4 rounded-lg px-4 py-3 transition-colors hover:bg-muted/20">
                      <span className="font-mono text-sm font-semibold">{truncateHash(trade.trade_hash)}</span>
                      <span className="font-mono text-sm text-muted-foreground">{truncateHash(trade.buyer_wallet)}</span>
                      <span className="text-right font-mono text-sm font-semibold">${Number(trade.amount_usdc || 0).toLocaleString()}</span>
                      {statusBadge(trade.status)}
                      <span className="text-right text-sm text-muted-foreground">{timeAgo(trade.created_at)}</span>
                      {index < recentTrades.length - 1 && <Separator className="col-span-5 mt-1 opacity-50" />}
                    </div>
                  ))}
                </div>
              </>
            )}
          </CardContent>
        </Card>

        <Card className="border-border/50">
          <CardHeader>
            <CardTitle className="text-lg">Quick Actions</CardTitle>
            <CardDescription>Common operations</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 gap-3">
              {quickActions.map((action) => (
                <button key={action.label} onClick={() => router.visit(action.href)} className="flex flex-col items-center gap-2 rounded-xl border border-border/50 bg-card px-3 py-5 transition-colors hover:bg-primary/10">
                  <div className="flex size-12 items-center justify-center rounded-xl bg-primary/10">
                    <action.icon className="size-7 text-primary" weight="duotone" />
                  </div>
                  <span className="text-sm font-medium">{action.label}</span>
                  <span className="text-sm text-muted-foreground">{action.desc}</span>
                </button>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}

Dashboard.layout = (page) => <DashboardLayout>{page}</DashboardLayout>
