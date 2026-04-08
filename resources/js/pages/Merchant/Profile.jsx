import { useState, useEffect } from "react"
import { Link } from "@inertiajs/react"
import { api } from "@/lib/api"
import {
  Wallet,
  ShieldCheck,
  Bank,
  Copy,
  Clock,
  MapPin,
  ArrowsLeftRight,
  ChartLineUp,
  CheckCircle,
  Warning,
  CaretLeft,
  CaretRight,
  Globe,
  Handshake,
  CurrencyDollar,
  Timer,
  Info,
  ArrowRight,
  CreditCard,
  DeviceMobile,
  Users,
  QrCode,
} from "@phosphor-icons/react"
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Separator } from "@/components/ui/separator"
import { Avatar, AvatarImage, AvatarFallback } from "@/components/ui/avatar"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import ConnectWallet from "@/components/ConnectWallet"
import SiteLogo from "@/components/SiteLogo"
import MerchantRankBadge from "@/components/MerchantRankBadge"
import VerificationBadges from "@/components/VerificationBadges"
import ReliabilityCircle from "@/components/ReliabilityCircle"
import RiskWarning from "@/components/RiskWarning"
import ReviewStars from "@/components/ReviewStars"
import RecentTradesCarousel from "@/components/RecentTradesCarousel"
import PresetAmountButtons from "@/components/PresetAmountButtons"

const AMOUNTS = [50, 100, 500, 1000]

const currencyNameFormatter = new Intl.DisplayNames(["en"], { type: "currency" })
function getCurrencyName(code) {
  try { return currencyNameFormatter.of(code) } catch { return code }
}

const paymentTypeIcons = {
  bank_transfer: Bank,
  online_payment: Globe,
  mobile_payment: DeviceMobile,
  cash_meeting: Handshake,
}



function ProfileSkeleton() {
  return (
    <div className="min-h-screen overflow-x-hidden bg-background">
      <header className="sticky top-0 z-50 border-b border-border/50 bg-background/80 backdrop-blur-xl">
        <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 lg:px-6">
          <Link href="/"><SiteLogo /></Link>
          <ConnectWallet size="sm" />
        </div>
      </header>
      <div className="mx-auto max-w-6xl px-4 py-6 lg:px-6">
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
          <div className="space-y-6 lg:col-span-8">
            <Card className="border-border/50 overflow-hidden pt-0">
              <div className="h-24 bg-gradient-to-r from-primary/20 via-blue-600/10 to-purple-600/10" />
              <CardContent className="relative pt-0">
                <div className="-mt-12 flex flex-col gap-4 sm:flex-row sm:items-end sm:gap-6">
                  <Skeleton className="size-16 sm:size-24 rounded-full" />
                  <div className="flex-1 pb-1 space-y-3">
                    <div className="flex items-center gap-3">
                      <Skeleton className="h-8 w-[160px]" />
                      <Skeleton className="h-7 w-[80px] rounded-full" />
                    </div>
                    <div className="flex gap-2">
                      <Skeleton className="h-7 w-[90px] rounded-full" />
                      <Skeleton className="h-7 w-[70px] rounded-full" />
                      <Skeleton className="h-7 w-[80px] rounded-full" />
                    </div>
                  </div>
                </div>
                <div className="mt-5 space-y-3">
                  <Skeleton className="h-4 w-full" />
                  <Skeleton className="h-4 w-3/4" />
                  <div className="flex gap-4">
                    <Skeleton className="h-4 w-[120px]" />
                    <Skeleton className="h-4 w-[120px]" />
                  </div>
                  <Skeleton className="h-10 w-[260px] rounded-lg" />
                </div>
              </CardContent>
            </Card>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
              {Array.from({ length: 4 }).map((_, i) => (
                <Card key={i} className="border-border/50">
                  <CardContent className="flex flex-col items-center gap-2 pt-6">
                    <Skeleton className="h-[72px] w-[72px] rounded-full" />
                    <Skeleton className="h-4 w-[80px]" />
                  </CardContent>
                </Card>
              ))}
            </div>
            <Card className="border-border/50">
              <CardHeader><Skeleton className="h-6 w-[160px]" /></CardHeader>
              <CardContent>
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                  {Array.from({ length: 4 }).map((_, i) => (
                    <div key={i} className="space-y-2">
                      <Skeleton className="h-4 w-[80px]" />
                      <Skeleton className="h-7 w-[100px]" />
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </div>
          <div className="order-first lg:order-none lg:col-span-4 lg:sticky lg:top-24 lg:self-start">
            <Card className="border-border/50 overflow-hidden pt-0">
              <Skeleton className="h-14 w-full" />
              <div className="p-5 space-y-5">
                <Skeleton className="h-10 w-full rounded-full mx-auto" />
                <div className="grid grid-cols-4 gap-2">
                  {Array.from({ length: 4 }).map((_, i) => (
                    <Skeleton key={i} className="h-10 rounded-lg" />
                  ))}
                </div>
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-12 w-full rounded-lg" />
              </div>
            </Card>
          </div>
        </div>
      </div>
    </div>
  )
}

function mapBadges(merchant) {
  return {
    verified: merchant.kyc_status === "approved",
    fast: !!merchant.is_fast_responder,
    liquidity: !!merchant.has_liquidity,
    business: !!merchant.business_verified,
    email: !!merchant.email_verified,
    bank: !!merchant.bank_verified,
  }
}

function buildCurrencyRates(currencies) {
  const rates = {}
  if (currencies && currencies.length > 0) {
    currencies.forEach(c => {
      const marketRate = Number(c.market_rate) || 0
      const markup = Number(c.markup_percent) || 0
      rates[c.currency_code] = marketRate * (1 + markup / 100)
    })
  }
  return rates
}

function getPaymentMethodsByType(methods) {
  const bank = []
  const online = []
  const cash = []
  if (!methods) return { bank, online, cash }
  methods.forEach(m => {
    const icon = paymentTypeIcons[m.type] || Globe
    const item = { name: m.label || m.provider, type: m.type, icon, location: m.location }
    if (m.type === "bank_transfer") bank.push(item)
    else if (m.type === "online_payment" || m.type === "mobile_payment") online.push(item)
    else if (m.type === "cash_meeting") cash.push(item)
  })
  return { bank, online, cash }
}

function formatResponseTime(minutes) {
  if (!minutes && minutes !== 0) return "N/A"
  if (minutes < 1) return "< 1 min"
  return `~${Math.round(minutes)} min`
}

export default function MerchantProfile({ username }) {
  const [merchant, setMerchant] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [selectedAmount, setSelectedAmount] = useState(null)
  const [showAllReviews, setShowAllReviews] = useState(false)
  const [cashPage, setCashPage] = useState(0)
  const [customAmount, setCustomAmount] = useState("")
  const [currency, setCurrency] = useState("")

  useEffect(() => {
    if (!username) return
    setLoading(true)
    setError(null)
    api.getMerchantProfile(username)
      .then(res => {
        const data = res.data.merchant
        setMerchant(data)
        // Set default currency from the merchant's currencies
        if (data.currencies && data.currencies.length > 0) {
          setCurrency(data.currencies[0].currency_code)
        }
      })
      .catch(err => {
        if (err.status === 404) {
          setError("not_found")
        } else {
          setError("server")
        }
      })
      .finally(() => setLoading(false))
  }, [username])

  if (loading) {
    return <ProfileSkeleton />
  }

  if (error === "not_found") {
    return (
      <div className="min-h-screen overflow-x-hidden bg-background">
        <header className="sticky top-0 z-50 border-b border-border/50 bg-background/80 backdrop-blur-xl">
          <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 lg:px-6">
            <Link href="/"><SiteLogo /></Link>
            <ConnectWallet size="sm" />
          </div>
        </header>
        <div className="mx-auto max-w-6xl px-4 py-6 lg:px-6">
          <div className="flex flex-col items-center justify-center py-20 text-center">
            <Warning className="size-16 text-muted-foreground/20 mb-4" weight="duotone" />
            <p className="text-lg font-semibold mb-2">Merchant not found</p>
            <p className="text-sm text-muted-foreground mb-6">The merchant you are looking for does not exist or is no longer active.</p>
            <Link href="/">
              <Button>Back to Home</Button>
            </Link>
          </div>
        </div>
      </div>
    )
  }

  if (error === "server") {
    return (
      <div className="min-h-screen overflow-x-hidden bg-background">
        <header className="sticky top-0 z-50 border-b border-border/50 bg-background/80 backdrop-blur-xl">
          <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 lg:px-6">
            <Link href="/"><SiteLogo /></Link>
            <ConnectWallet size="sm" />
          </div>
        </header>
        <div className="mx-auto max-w-6xl px-4 py-6 lg:px-6">
          <div className="flex flex-col items-center justify-center py-20 text-center">
            <Warning className="size-16 text-muted-foreground/20 mb-4" weight="duotone" />
            <p className="text-lg font-semibold mb-2">Something went wrong</p>
            <p className="text-sm text-muted-foreground mb-6">Failed to load merchant profile. Please try again.</p>
            <Button onClick={() => window.location.reload()}>Retry</Button>
          </div>
        </div>
      </div>
    )
  }

  const badges = mapBadges(merchant)
  const rates = buildCurrencyRates(merchant.currencies)
  const { bank: bankMethods, online: onlineMethods, cash: cashMethods } = getPaymentMethodsByType(merchant.payment_methods)
  const reviews = merchant.reviews || []
  const recentTrades = merchant.recent_trades || []
  const tradingLinks = merchant.trading_links || []
  const primaryLink = tradingLinks.find(l => l.is_primary) ?? tradingLinks[0] ?? null

  // Trade instructions: can be a string or array from API
  const instructions = merchant.trade_instructions
    ? (Array.isArray(merchant.trade_instructions) ? merchant.trade_instructions : merchant.trade_instructions.split("\n").filter(s => s.trim()))
    : []

  // Trade limits from first currency or "Varies"
  const currencies = merchant.currencies || []
  const hasMultipleCurrencies = currencies.length > 1
  const firstCurrency = currencies.length > 0 ? currencies[0] : null
  const minTrade = hasMultipleCurrencies ? "Varies" : (firstCurrency ? `$${Number(firstCurrency.min_amount).toLocaleString()}` : "N/A")
  const maxTrade = hasMultipleCurrencies ? "Varies" : (firstCurrency ? `$${Number(firstCurrency.max_amount).toLocaleString()}` : "N/A")

  const amount = selectedAmount || Number(customAmount) || 0
  const currentRate = rates[currency] || 0
  const fiatAmount = amount * currentRate

  const truncatedWallet = merchant.wallet_address
    ? `${merchant.wallet_address.slice(0, 6)}...${merchant.wallet_address.slice(-4)}`
    : ""

  return (
    <div className="min-h-screen overflow-x-hidden bg-background">
      {/* Top Navigation Bar */}
      <header className="sticky top-0 z-50 border-b border-border/50 bg-background/80 backdrop-blur-xl">
        <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 lg:px-6">
          <Link href="/"><SiteLogo /></Link>
          <ConnectWallet size="sm" />
        </div>
      </header>

      <div className="mx-auto max-w-6xl px-4 py-6 lg:px-6">
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">

          {/* ===== LEFT COLUMN ===== */}
          <div className="space-y-6 lg:col-span-8">

            {/* Merchant Header */}
            <Card className="border-border/50 overflow-hidden pt-0">
              {/* Gradient banner */}
              <div className="h-24 bg-gradient-to-r from-primary/20 via-blue-600/10 to-purple-600/10" />
              <CardContent className="relative pt-0">
                {/* Avatar overlapping banner */}
                <div className="-mt-12 flex flex-col gap-4 sm:flex-row sm:items-end sm:gap-6">
                  <Avatar className="size-16 sm:size-24 border-4 border-card shadow-xl">
                    <AvatarImage src={merchant.avatar} alt={merchant.username} />
                    <AvatarFallback className="bg-gradient-to-br from-primary to-blue-600 text-3xl font-bold text-white">
                      {merchant.username.charAt(0).toUpperCase()}
                    </AvatarFallback>
                  </Avatar>
                  <div className="flex-1 pb-1">
                    <div className="flex flex-wrap items-center gap-3">
                      <h1 className="text-2xl font-bold tracking-tight">{merchant.username}</h1>
                      {merchant.rank && (
                        <MerchantRankBadge rank={merchant.rank} />
                      )}
                      <div className="flex items-center gap-1.5">
                        <span className={`size-2.5 rounded-full ${merchant.is_online ? "bg-emerald-500 shadow-[0_0_8px_rgba(34,197,94,0.5)]" : "bg-muted-foreground"}`} />
                        <span className="text-sm text-muted-foreground">{merchant.is_online ? "Online" : "Offline"}</span>
                      </div>
                    </div>
                    <div className="mt-2">
                      <VerificationBadges badges={badges} />
                    </div>
                  </div>
                </div>

                <div className="mt-5 space-y-3">
                  {merchant.bio && <p className="text-sm leading-relaxed text-muted-foreground">{merchant.bio}</p>}
                  <div className="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                    <span className="flex items-center gap-1.5"><Clock weight="duotone" size={16} /> Response: {formatResponseTime(merchant.avg_response_minutes)}</span>
                    {merchant.member_since && <span className="flex items-center gap-1.5"><Users weight="duotone" size={16} /> Since {merchant.member_since}</span>}
                  </div>
                  {/* Wallet */}
                  {merchant.wallet_address && (
                    <div className="flex items-center gap-2 rounded-lg bg-muted/30 px-4 py-2.5 w-fit">
                      <Wallet weight="duotone" size={16} className="text-muted-foreground" />
                      <span className="font-mono text-sm">{truncatedWallet}</span>
                      <button
                        className="text-muted-foreground transition-colors hover:text-foreground"
                        onClick={() => navigator.clipboard.writeText(merchant.wallet_address)}
                      >
                        <Copy size={14} />
                      </button>
                    </div>
                  )}
                </div>
              </CardContent>
            </Card>

            {/* Stats */}
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
              <Card className="border-border/50">
                <CardContent className="flex flex-col items-center gap-2 pt-6">
                  <ReliabilityCircle rate={merchant.reliability_score ?? 0} max={10} color="text-emerald-500" />
                  <span className="text-sm text-muted-foreground">Reliability</span>
                </CardContent>
              </Card>
              <Card className="border-border/50">
                <CardContent className="flex flex-col items-center gap-2 pt-6">
                  <div className="flex items-center gap-2">
                    <ArrowsLeftRight className="size-5 text-blue-400" weight="duotone" />
                    <span className="font-mono text-2xl font-bold">{(merchant.total_trades || 0).toLocaleString()}</span>
                  </div>
                  <span className="text-sm text-muted-foreground">Trades</span>
                </CardContent>
              </Card>
              <Card className="border-border/50">
                <CardContent className="flex flex-col items-center gap-2 pt-6">
                  <div className="text-center">
                    <span className="font-mono text-2xl font-bold">{merchant.completion_rate ?? 0}%</span>
                    <div className="mt-1"><ReviewStars rating={merchant.avg_rating ?? 0} size={14} /></div>
                  </div>
                  <span className="text-sm text-muted-foreground">Completion</span>
                </CardContent>
              </Card>
              <Card className="border-border/50">
                <CardContent className="flex flex-col items-center gap-2 pt-6">
                  <div className="flex items-center gap-2">
                    <ChartLineUp className="size-5 text-purple-400" weight="duotone" />
                    <span className="font-mono text-2xl font-bold">${((merchant.total_volume || 0) / 1000).toFixed(0)}K</span>
                  </div>
                  <span className="text-sm text-muted-foreground">Volume</span>
                </CardContent>
              </Card>
            </div>

            {/* Escrow Liquidity */}
            <Card className="border-border/50">
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Wallet weight="duotone" className="size-5 text-emerald-500" />
                  Escrow Liquidity
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid grid-cols-3 gap-4">
                  <div>
                    <p className="text-sm text-muted-foreground">Available</p>
                    <p className="font-mono text-xl font-bold text-emerald-500">${Math.max((merchant.escrow_balance ?? 0) - (merchant.locked_balance ?? 0), 0).toLocaleString()}</p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">Active Trades</p>
                    <p className="font-mono text-xl font-bold">{merchant.active_trades ?? 0}</p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">Total Locked</p>
                    <p className="font-mono text-xl font-bold text-amber-500">${(merchant.locked_balance ?? 0).toLocaleString()}</p>
                  </div>
                </div>
                {merchant.escrow_address && (
                  <div className="flex items-center gap-2 rounded-lg bg-muted/20 px-3 py-2 w-fit">
                    <ShieldCheck weight="duotone" size={14} className="text-muted-foreground shrink-0" />
                    <span className="font-mono text-xs text-muted-foreground">
                      {merchant.escrow_address.slice(0, 6)}...{merchant.escrow_address.slice(-4)}
                    </span>
                    <button
                      className="text-muted-foreground transition-colors hover:text-foreground"
                      onClick={() => navigator.clipboard.writeText(merchant.escrow_address)}
                    >
                      <Copy size={12} />
                    </button>
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Trade Limits */}
            <Card className="border-border/50">
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Timer weight="duotone" className="size-5 text-blue-400" />
                  Trade Limits
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                  <div className="rounded-lg bg-muted/20 p-4 text-center">
                    <p className="text-sm text-muted-foreground">Minimum</p>
                    <p className="font-mono text-lg font-bold">{minTrade}</p>
                  </div>
                  <div className="rounded-lg bg-muted/20 p-4 text-center">
                    <p className="text-sm text-muted-foreground">Maximum</p>
                    <p className="font-mono text-lg font-bold">{maxTrade}</p>
                  </div>
                  <div className="rounded-lg bg-muted/20 p-4 text-center">
                    <p className="text-sm text-muted-foreground">Timer</p>
                    <p className="font-mono text-lg font-bold">{merchant.trade_timer_minutes ?? 30} min</p>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Payment Methods */}
            {(bankMethods.length > 0 || onlineMethods.length > 0 || cashMethods.length > 0) && (
              <Card className="border-border/50">
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <CreditCard weight="duotone" className="size-5 text-amber-400" />
                    Accepted Payment Methods
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-4">
                    {/* Banks */}
                    {bankMethods.length > 0 && (
                      <div>
                        <p className="mb-2 text-sm font-semibold uppercase tracking-wider text-muted-foreground">Bank Accounts</p>
                        <div className="flex flex-wrap gap-2">
                          {bankMethods.slice(0, 10).map(m => (
                            <span key={m.name} className="inline-flex items-center gap-2 rounded-lg border border-border/50 bg-muted/20 px-3 py-2 text-sm font-medium">
                              <m.icon weight="duotone" size={18} className="text-muted-foreground" />
                              {m.name}
                            </span>
                          ))}
                        </div>
                      </div>
                    )}
                    {/* Online/Mobile */}
                    {onlineMethods.length > 0 && (
                      <div>
                        <p className="mb-2 text-sm font-semibold uppercase tracking-wider text-muted-foreground">Online & Mobile</p>
                        <div className="flex flex-wrap gap-2">
                          {onlineMethods.slice(0, 10).map(m => (
                            <span key={m.name} className="inline-flex items-center gap-2 rounded-lg border border-border/50 bg-muted/20 px-3 py-2 text-sm font-medium">
                              <m.icon weight="duotone" size={18} className="text-muted-foreground" />
                              {m.name}
                            </span>
                          ))}
                        </div>
                      </div>
                    )}
                    {/* Cash */}
                    {cashMethods.length > 0 && (
                      <div>
                        <p className="mb-2 text-sm font-semibold uppercase tracking-wider text-muted-foreground">In-Person</p>
                        <div className="flex flex-wrap gap-2">
                          {cashMethods.slice(0, 10).map(m => (
                            <span key={m.name} className="inline-flex items-center gap-2 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-sm font-medium text-emerald-400">
                              <m.icon weight="duotone" size={18} />
                              {m.name}
                              {m.location && <span className="text-xs text-muted-foreground">({m.location})</span>}
                              <MapPin weight="fill" size={14} />
                            </span>
                          ))}
                        </div>
                      </div>
                    )}
                  </div>
                </CardContent>
              </Card>
            )}

            {/* Trade Instructions */}
            {instructions.length > 0 && (
              <Card className="border-border/50">
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Info weight="duotone" className="size-5 text-blue-400" />
                    Trade Instructions
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <ol className="space-y-3">
                    {instructions.slice(0, 10).map((step, i) => (
                      <li key={i} className="flex items-start gap-3">
                        <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/15 text-sm font-bold text-primary">
                          {i + 1}
                        </span>
                        <span className="pt-0.5 text-sm leading-relaxed text-muted-foreground">{step}</span>
                      </li>
                    ))}
                  </ol>
                </CardContent>
              </Card>
            )}

            {/* Recent Trades Carousel (non-cash) */}
            <RecentTradesCarousel trades={recentTrades.filter(t => !["cash_meeting", "cash meeting"].includes((t.payment_method || "").toLowerCase()))} />

            {/* Cash Meeting Trades */}
            {(() => {
              const cashTrades = recentTrades.filter(t => ["cash_meeting", "cash meeting"].includes((t.payment_method || "").toLowerCase()))
              const totalCashPages = Math.ceil(cashTrades.length / 4)
              const pagedCashTrades = cashTrades.slice(cashPage * 4, (cashPage + 1) * 4)
              return cashTrades.length > 0 && (
              <Card className="border-border/50">
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <CardTitle className="flex items-center gap-2">
                      <Handshake weight="duotone" className="size-5 text-emerald-400" />
                      Cash Meeting Trades
                    </CardTitle>
                    {totalCashPages > 1 && (
                      <div className="flex items-center gap-2">
                        <button onClick={() => setCashPage(p => Math.max(0, p - 1))} disabled={cashPage === 0} className="rounded-md border border-border/50 p-1 disabled:opacity-30">
                          <CaretLeft size={16} />
                        </button>
                        <span className="text-xs text-muted-foreground">{cashPage + 1}/{totalCashPages}</span>
                        <button onClick={() => setCashPage(p => Math.min(totalCashPages - 1, p + 1))} disabled={cashPage >= totalCashPages - 1} className="rounded-md border border-border/50 p-1 disabled:opacity-30">
                          <CaretRight size={16} />
                        </button>
                      </div>
                    )}
                  </div>
                </CardHeader>
                <CardContent>
                  <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    {pagedCashTrades.map((trade, i) => (
                      <div key={i} className="rounded-xl border border-border/50 bg-muted/10 p-4 space-y-3">
                        <div className="flex items-center justify-between">
                          <span className="font-mono text-sm font-semibold">#{trade.trade_hash ? trade.trade_hash.slice(2, 8) : "---"}</span>
                          <div className="flex items-center gap-2">
                            <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                              trade.role === "buy" ? "bg-emerald-500/15 text-emerald-400" : "bg-blue-500/15 text-blue-400"
                            }`}>
                              {trade.role === "buy" ? "Buy" : "Sell"}
                            </span>
                            <span className="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-emerald-500/15 text-emerald-400">
                              Confirmed
                            </span>
                          </div>
                        </div>
                        <div className="space-y-1 text-sm">
                          <div className="flex justify-between">
                            <span className="text-muted-foreground">Buyer</span>
                            <span className="font-mono">{trade.counterparty}</span>
                          </div>
                          <div className="flex justify-between">
                            <span className="text-muted-foreground">Amount</span>
                            <span className="font-semibold">${Number(trade.amount).toLocaleString()} USDC</span>
                          </div>
                          {trade.meeting_location && (
                            <div className="flex justify-between">
                              <span className="text-muted-foreground">Location</span>
                              <span>{trade.meeting_location}</span>
                            </div>
                          )}
                        </div>
                        <div className="flex gap-2">
                          {trade.nft_token_id && (
                            <Button
                              variant="outline"
                              size="sm"
                              className="flex-1 gap-1.5 text-sm"
                              onClick={() => window.open(`https://sepolia.basescan.org/token/0xA31aaDAef8ED85ea73b4665291b3c4E7ED5F6bb6?a=${trade.nft_token_id}`, '_blank')}
                            >
                              <QrCode size={14} /> Verify NFT
                            </Button>
                          )}
                          {trade.trade_hash && (
                            <Button
                              variant="outline"
                              size="sm"
                              className="flex-1 text-sm"
                              onClick={() => window.open(`/verify/${trade.trade_hash}`, '_blank')}
                            >
                              View Proof
                            </Button>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                </CardContent>
              </Card>
            )})()}

            {/* Reviews */}
            {(reviews.length > 0 || merchant.review_count > 0) && (
              <div>
                <div className="mb-4 flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <h3 className="text-lg font-semibold">Reviews</h3>
                    <ReviewStars rating={merchant.avg_rating ?? 0} size={18} />
                    <span className="text-sm text-muted-foreground">({merchant.review_count ?? 0})</span>
                  </div>
                </div>
                <div className="space-y-3">
                  {(showAllReviews ? reviews : reviews.slice(0, 5)).map((review) => {
                    const reviewerDisplay = review.reviewer_wallet
                      ? `${review.reviewer_wallet.slice(0, 4)}***${review.reviewer_wallet.slice(-2)}`
                      : "Anonymous"
                    const reviewDate = review.created_at
                      ? new Date(review.created_at).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" })
                      : ""
                    const isBuyReview = review.reviewer_role === "buyer"
                    return (
                      <Card key={review.id} className="border-border/50">
                        <CardContent className="pt-5">
                          <div className="flex items-start justify-between">
                            <div className="flex items-center gap-3">
                              <Avatar className="size-9">
                                <AvatarFallback className="bg-muted text-sm">{reviewerDisplay.charAt(0)}</AvatarFallback>
                              </Avatar>
                              <div>
                                <div className="flex items-center gap-2">
                                  <span className="text-sm font-semibold">{reviewerDisplay}</span>
                                  <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                    isBuyReview ? "bg-emerald-500/15 text-emerald-400" : "bg-blue-500/15 text-blue-400"
                                  }`}>
                                    {isBuyReview ? "Buy" : "Sell"}
                                  </span>
                                </div>
                                <ReviewStars rating={review.rating} size={13} />
                              </div>
                            </div>
                            <span className="text-sm text-muted-foreground">{reviewDate}</span>
                          </div>
                          {review.comment && <p className="mt-3 text-sm leading-relaxed text-muted-foreground">{review.comment}</p>}
                        </CardContent>
                      </Card>
                    )
                  })}
                  {reviews.length > 5 && !showAllReviews && (
                    <div className="mt-4 text-center">
                      <button
                        onClick={() => setShowAllReviews(true)}
                        className="text-sm font-medium text-primary hover:underline"
                      >
                        Show All Reviews ({merchant.review_count})
                      </button>
                    </div>
                  )}
                  {showAllReviews && reviews.length > 5 && (
                    <div className="mt-4 text-center">
                      <button
                        onClick={() => setShowAllReviews(false)}
                        className="text-sm font-medium text-muted-foreground hover:underline"
                      >
                        Show Less
                      </button>
                    </div>
                  )}
                </div>
              </div>
            )}

          </div>

          {/* ===== RIGHT COLUMN — Buy Panel ===== */}
          <div className="order-first lg:order-none lg:col-span-4 lg:sticky lg:top-24 lg:self-start">
            <Card className="border-border/50 overflow-hidden pt-0">
              {/* Header */}
              <div className="relative h-14 flex items-center justify-center text-base font-semibold text-emerald-400 bg-emerald-500/5">
                Buy USDC
                <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-emerald-500" />
              </div>

                <div className="p-5 space-y-5">
                  {/* Rate */}
                  <div className="flex items-center justify-center">
                    <span className="inline-flex items-center gap-2 rounded-full border px-4 py-2 font-mono text-base font-bold border-emerald-500/30 bg-emerald-500/10 text-emerald-400">
                      {currentRate} {currency} = 1 USDC
                    </span>
                  </div>

                  {/* Preset Amounts */}
                  <PresetAmountButtons
                    amounts={AMOUNTS}
                    selectedAmount={selectedAmount}
                    activeTab="buy"
                    onSelect={(amt) => { setSelectedAmount(amt); setCustomAmount(String(amt)) }}
                  />

                  {/* Custom Amount */}
                  <div className="space-y-2">
                    <label className="text-sm text-muted-foreground">Amount (USDC)</label>
                    <div className="relative">
                      <Input
                        type="number"
                        placeholder="Enter amount"
                        value={customAmount}
                        onChange={e => { setCustomAmount(e.target.value); setSelectedAmount(null) }}
                        className="pr-16"
                      />
                      <span className="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-medium text-muted-foreground">USDC</span>
                    </div>
                  </div>

                  {/* Currency */}
                  {currencies.length > 0 && (
                    <div className="space-y-2">
                      <label className="text-sm text-muted-foreground">Currency</label>
                      <Select value={currency} onValueChange={setCurrency}>
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          {currencies.map(c => (
                            <SelectItem key={c.currency_code} value={c.currency_code}>{c.currency_code} — {getCurrencyName(c.currency_code)}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                  )}

                  {/* Fiat Equivalent */}
                  {amount > 0 && (
                    <div className="flex items-center justify-between rounded-lg bg-muted/30 px-4 py-3">
                      <span className="text-sm text-muted-foreground">You pay</span>
                      <span className="font-mono text-lg font-bold">{fiatAmount.toLocaleString()} {currency}</span>
                    </div>
                  )}

                  {/* Stake Info */}
                  <div className="flex items-start gap-2.5 rounded-lg border border-primary/20 bg-primary/5 px-4 py-3">
                    <Info weight="fill" size={18} className="mt-0.5 shrink-0 text-primary" />
                    <div>
                      <p className="text-sm font-medium">$5 USDC anti-spam stake</p>
                      <p className="text-sm text-muted-foreground">Refunded after successful trade</p>
                    </div>
                  </div>

                  {/* CTA Button */}
                  {primaryLink ? (
                    <Link href={`/trade/${primaryLink.slug}/start${amount > 0 || currency ? `?${amount > 0 ? `amount=${amount}` : ""}${amount > 0 && currency ? "&" : ""}${currency ? `currency=${currency}` : ""}` : ""}`} className="block">
                      <Button size="lg" className="w-full text-base font-semibold">
                        Start Trade <ArrowRight weight="bold" size={18} className="ml-2" />
                      </Button>
                    </Link>
                  ) : (
                    <div className="rounded-lg border border-amber-500/20 bg-amber-500/5 px-4 py-3 text-center">
                      <p className="text-sm font-medium text-amber-400">Trading not available yet</p>
                      <p className="text-sm text-muted-foreground">This merchant hasn't set up a trading link</p>
                    </div>
                  )}

                  {/* Risk Warning */}
                  <RiskWarning message="Only trade with funds you can afford to lose. Verify merchant reputation before trading." />
                </div>
            </Card>
          </div>

        </div>

        {/* Footer — full width */}
        <div className="mt-8 border-t border-border/50 pt-6 text-center">
          <p className="text-sm text-muted-foreground">All trades are fully escrowed on the Base blockchain</p>
          <a href="#" className="text-sm text-primary hover:underline">View Escrow Contract on BaseScan</a>
        </div>
      </div>
    </div>
  )
}
