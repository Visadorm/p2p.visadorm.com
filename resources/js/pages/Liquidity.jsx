import { useState, useEffect } from "react"
import DashboardLayout from "@/layouts/DashboardLayout"
import { useWallet } from "@/hooks/useWallet"
import { useEscrow } from "@/hooks/useEscrow"
import { toast } from "sonner"
import {
  Vault,
  ArrowDown,
  ArrowUp,
  Copy,
  Lock,
  Warning,
} from "@phosphor-icons/react"
import EscrowBalanceDisplay from "@/components/EscrowBalanceDisplay"
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Badge } from "@/components/ui/badge"
import { Separator } from "@/components/ui/separator"
import { Skeleton } from "@/components/ui/skeleton"

function truncateHash(hash) {
  if (!hash) return "—"
  return `${hash.slice(0, 8)}...${hash.slice(-4)}`
}

function timeAgo(dateStr) {
  if (!dateStr) return "—"
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return "just now"
  if (mins < 60) return `${mins}m ago`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours}h ago`
  return `${Math.floor(hours / 24)}d ago`
}

export default function Liquidity() {
  const { isAuthenticated, merchant } = useWallet()
  const {
    isLoading,
    error,
    retry,
    dashboardData,
    trades,
    escrowBalance,
    lockedBalance,
    availableBalance,
    depositState,
    withdrawState,
    deposit,
    withdraw,
  } = useEscrow()
  const [depositAmount, setDepositAmount] = useState("")
  const [withdrawAmount, setWithdrawAmount] = useState("")

  const isDepositBusy = depositState !== "idle" && depositState !== "done"
  const isWithdrawBusy = withdrawState !== "idle" && withdrawState !== "done"

  const depositLabel = depositState === "approving"  ? "Approving USDC..."
                     : depositState === "depositing" ? "Submitting..."
                     : depositState === "confirming" ? "Confirming..."
                     : "Deposit to Escrow"
  const withdrawLabel = withdrawState === "submitting" ? "Submitting..."
                      : withdrawState === "confirming"  ? "Confirming..."
                      : "Withdraw from Escrow"


  const handleCopy = (text) => {
    if (!text) {
      toast.error("Nothing to copy")
      return
    }
    navigator.clipboard.writeText(text)
    toast.success("Copied to clipboard")
  }

  const handleDeposit = () => deposit(depositAmount)
  const handleWithdraw = () => withdraw(withdrawAmount)

  if (error) {
    return (
      <div className="flex flex-col items-center justify-center py-20 text-center">
        <Vault className="size-16 text-muted-foreground/20 mb-4" weight="duotone" />
        <p className="text-lg font-semibold mb-2">Failed to load liquidity data</p>
        <p className="text-sm text-muted-foreground mb-6">Something went wrong. Please try again.</p>
        <Button onClick={retry}>Retry</Button>
      </div>
    )
  }

  if (isLoading || !dashboardData) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-40 w-full rounded-xl" />
        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
          <Skeleton className="h-64 rounded-xl" />
          <Skeleton className="h-64 rounded-xl" />
        </div>
        <Skeleton className="h-48 rounded-xl" />
      </div>
    )
  }

  const { stats, active_trades_count } = dashboardData
  const totalVolume = Number(stats?.total_volume || 0)
  return (
    <div className="space-y-6">
      {/* Hero Balance */}
      <Card className="border-border/50 bg-gradient-to-br from-card via-card to-primary/5">
        <CardContent className="pt-6">
          <div className="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
            <div className="space-y-4">
              <div className="flex items-center gap-3">
                <div className="flex size-12 items-center justify-center rounded-xl bg-primary/10">
                  <Vault className="size-6 text-primary" weight="duotone" />
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">Available Escrow Balance</p>
                  <p className="font-mono text-2xl sm:text-4xl font-bold tracking-tight">
                    ${availableBalance.toLocaleString()} <span className="text-lg text-muted-foreground">USDC</span>
                  </p>
                </div>
              </div>
              <EscrowBalanceDisplay
                available={availableBalance}
                locked={lockedBalance}
                totalVolume={totalVolume}
              />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Deposit & Withdraw */}
      <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
        {/* Deposit */}
        <Card className="border-border/50">
          <CardHeader>
            <div className="flex items-center gap-3">
              <div className="flex size-10 items-center justify-center rounded-xl bg-emerald-500/10">
                <ArrowDown className="size-5 text-emerald-500" weight="bold" />
              </div>
              <div>
                <CardTitle>Deposit USDC</CardTitle>
                <CardDescription>Add funds to your escrow balance</CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <div className="space-y-2">
                <Label>Amount</Label>
                <div className="relative">
                  <Input
                    type="number"
                    placeholder="0.00"
                    value={depositAmount}
                    onChange={(e) => setDepositAmount(e.target.value)}
                    className="pr-16"
                    min="0"
                    step="0.01"
                  />
                  <span className="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-medium text-muted-foreground">USDC</span>
                </div>
              </div>
              <div className="flex items-center justify-between rounded-lg bg-muted/30 px-4 py-2.5">
                <span className="text-sm text-muted-foreground">Wallet Address</span>
                <span className="font-mono text-sm font-semibold">{truncateHash(merchant?.wallet_address)}</span>
              </div>
              <Button className="w-full gap-2" size="lg" onClick={handleDeposit} disabled={isDepositBusy}>
                <ArrowDown className="size-5" weight="bold" />
                {depositLabel}
              </Button>
            </div>
          </CardContent>
        </Card>

        {/* Withdraw */}
        <Card className="border-border/50">
          <CardHeader>
            <div className="flex items-center gap-3">
              <div className="flex size-10 items-center justify-center rounded-xl bg-amber-500/10">
                <ArrowUp className="size-5 text-amber-500" weight="bold" />
              </div>
              <div>
                <CardTitle>Withdraw USDC</CardTitle>
                <CardDescription>Withdraw available funds from escrow</CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <div className="space-y-2">
                <Label>Amount</Label>
                <div className="relative">
                  <Input
                    type="number"
                    placeholder="0.00"
                    value={withdrawAmount}
                    onChange={(e) => setWithdrawAmount(e.target.value)}
                    className="pr-16"
                    min="0"
                    step="0.01"
                  />
                  <span className="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-medium text-muted-foreground">USDC</span>
                </div>
              </div>
              <div className="flex items-center justify-between rounded-lg bg-muted/30 px-4 py-2.5">
                <span className="text-sm text-muted-foreground">Available to Withdraw</span>
                <span className="font-mono text-sm font-semibold text-emerald-500">${availableBalance.toLocaleString()}</span>
              </div>
              <div className="flex items-center gap-2 rounded-lg bg-amber-500/10 px-4 py-2.5">
                <Warning className="size-4 text-amber-500 shrink-0" weight="fill" />
                <span className="text-sm text-amber-500">Only unlocked USDC can be withdrawn</span>
              </div>
              <Button variant="outline" className="w-full gap-2" size="lg" onClick={handleWithdraw} disabled={isWithdrawBusy}>
                <ArrowUp className="size-5" weight="bold" />
                {withdrawLabel}
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Active Trade Locks */}
      <Card className="border-border/50">
        <CardHeader>
          <div className="flex items-center gap-3">
            <div className="flex size-10 items-center justify-center rounded-xl bg-purple-500/10">
              <Lock className="size-5 text-purple-500" weight="duotone" />
            </div>
            <div>
              <CardTitle>Active Trade Locks</CardTitle>
              <CardDescription>USDC currently locked in active trades ({active_trades_count})</CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {trades.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-10 text-center">
              <Lock className="size-10 text-muted-foreground/20 mb-3" weight="duotone" />
              <p className="text-muted-foreground">No active trade locks</p>
              <p className="text-sm text-muted-foreground/60">All your escrow funds are available</p>
            </div>
          ) : (
            <div className="space-y-0">
              {trades.map((trade, i) => (
                <div key={trade.id || i}>
                  <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between rounded-lg px-3 py-3 transition-colors hover:bg-muted/20">
                    <div className="flex items-center gap-3">
                      <span className="font-mono text-sm font-semibold">{truncateHash(trade.trade_hash)}</span>
                      <span className="font-mono text-sm text-muted-foreground">{truncateHash(trade.buyer_wallet)}</span>
                    </div>
                    <div className="flex items-center gap-4">
                      <span className="font-mono text-sm font-semibold">
                        ${Number(trade.amount_usdc || 0).toLocaleString()} <span className="text-muted-foreground">{trade.currency_code}</span>
                      </span>
                      <Badge variant="outline" className="text-sm">{timeAgo(trade.created_at)}</Badge>
                    </div>
                  </div>
                  {i < trades.length - 1 && <Separator className="opacity-30" />}
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      {/* Contract Info */}
      <Card className="border-border/50">
        <CardContent className="pt-6">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div className="space-y-1">
              <p className="text-sm text-muted-foreground">Wallet Address</p>
              <div className="flex items-center gap-2">
                <span className="font-mono text-sm font-semibold">{truncateHash(merchant?.wallet_address)}</span>
                <Copy
                  className="size-4 cursor-pointer text-muted-foreground hover:text-foreground"
                  onClick={() => handleCopy(merchant?.wallet_address || "")}
                />
              </div>
            </div>
            <div className="space-y-1">
              <p className="text-sm text-muted-foreground">Network</p>
              <p className="text-sm font-semibold">Base (Coinbase L2)</p>
            </div>
            <div className="space-y-1">
              <p className="text-sm text-muted-foreground">Active Trades</p>
              <p className="font-mono text-sm font-semibold">{active_trades_count}</p>
            </div>
            <div className="space-y-1">
              <p className="text-sm text-muted-foreground">Total Locked</p>
              <p className="font-mono text-sm font-semibold text-amber-500">${lockedBalance.toLocaleString()} USDC</p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

Liquidity.layout = (page) => <DashboardLayout>{page}</DashboardLayout>
