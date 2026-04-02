import { Link } from "@inertiajs/react"
import {
  Wallet,
  ShieldCheck,
  ArrowsLeftRight,
  MagnifyingGlass,
  Lightning,
  Globe,
  Lock,
  Users,
  ChartLineUp,
  CheckCircle,
  ArrowRight,
  Star,
  SealCheck,
  Timer,
  Handshake,
} from "@phosphor-icons/react"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import { Separator } from "@/components/ui/separator"
import { Avatar, AvatarFallback } from "@/components/ui/avatar"
import { usePage } from "@inertiajs/react"
import ConnectWallet from "@/components/ConnectWallet"
import SiteLogo from "@/components/SiteLogo"

function usePlatformStats() {
  const { platform_stats } = usePage().props
  const s = platform_stats || {}
  return [
    { label: "Total Trades", value: (s.total_trades || 0).toLocaleString(), icon: ArrowsLeftRight },
    { label: "Volume", value: `$${(s.total_volume || 0).toLocaleString()}`, icon: ChartLineUp },
    { label: "Merchants", value: (s.total_merchants || 0).toLocaleString(), icon: Users },
    { label: "Avg Completion", value: `${s.avg_completion || 0}%`, icon: CheckCircle },
  ]
}

const STEPS = [
  {
    icon: Wallet,
    title: "Connect Your Wallet",
    desc: "Connect MetaMask, Trust Wallet, or any Web3 wallet. No email or password needed — your wallet is your identity.",
    color: "bg-primary/10 text-primary",
  },
  {
    icon: MagnifyingGlass,
    title: "Find a Merchant",
    desc: "Browse verified merchants, compare rates and payment methods. Choose bank transfer, Wise, PayPal, Zelle, or cash meeting.",
    color: "bg-blue-500/10 text-blue-400",
  },
  {
    icon: ShieldCheck,
    title: "Trade Securely",
    desc: "USDC is locked in smart contract escrow until payment is confirmed. 0.2% fee. Trades complete even if the website goes down.",
    color: "bg-emerald-500/10 text-emerald-400",
  },
]

const FEATURES = [
  { icon: Lock, title: "On-Chain Escrow", desc: "All funds locked in audited smart contracts on Base L2" },
  { icon: Lightning, title: "Instant Settlements", desc: "USDC released to your wallet the moment payment is confirmed" },
  { icon: Globe, title: "Multi-Currency", desc: "Trade with DOP, EUR, HTG, COP and more fiat currencies" },
  { icon: Handshake, title: "Cash Meetings", desc: "In-person trades with Soulbound NFT verification" },
  { icon: Timer, title: "Trade Protection", desc: "Auto-cancel timers protect both buyers and merchants" },
  { icon: SealCheck, title: "Verified Merchants", desc: "KYC-verified merchants with rank badges and reviews" },
]

const MERCHANTS = [
  { name: "CryptoKing", rank: "Elite", trades: 2450, completion: 98, available: 15000, badges: ["Verified", "Fast", "Liquidity"] },
  { name: "FastTrader", rank: "Hero", trades: 890, completion: 96, available: 8500, badges: ["Verified", "Fast"] },
  { name: "USDCDealer", rank: "Senior", trades: 340, completion: 94, available: 3200, badges: ["Verified"] },
  { name: "SafeSwap", rank: "Junior", trades: 45, completion: 91, available: 1500, badges: ["Verified"] },
]

const rankColors = {
  Elite: "bg-blue-500/15 text-blue-400 border-blue-500/20",
  Hero: "bg-yellow-500/15 text-yellow-400 border-yellow-500/20",
  Senior: "bg-slate-400/15 text-slate-300 border-slate-400/20",
  Junior: "bg-amber-800/15 text-amber-600 border-amber-700/20",
}

export default function Landing() {
  const STATS = usePlatformStats()
  return (
    <div className="min-h-screen overflow-x-hidden bg-background">
      {/* Nav */}
      <header className="sticky top-0 z-50 border-b border-border/50 bg-background/80 backdrop-blur-xl">
        <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 lg:px-6">
          <SiteLogo />
          <div className="flex items-center gap-3">
            <Button variant="ghost" size="sm" asChild>
              <a href="#merchants">Browse Merchants</a>
            </Button>
            <ConnectWallet size="sm" />
          </div>
        </div>
      </header>

      {/* Hero */}
      <section className="relative overflow-hidden">
        {/* Background decoration */}
        <div className="pointer-events-none absolute inset-0">
          <div className="absolute -top-40 -right-40 size-[600px] rounded-full bg-primary/5 blur-3xl" />
          <div className="absolute -bottom-40 -left-40 size-[500px] rounded-full bg-blue-600/5 blur-3xl" />
        </div>

        <div className="relative mx-auto max-w-6xl px-4 py-24 text-center lg:px-6 lg:py-32">
          <Badge variant="secondary" className="mb-6 gap-1.5 px-4 py-1.5 text-sm">
            <Globe weight="fill" className="size-4 text-blue-400" />
            Powered by Base (Coinbase L2)
          </Badge>

          <h1 className="mx-auto max-w-3xl text-3xl font-bold tracking-tight lg:text-5xl">
            P2P USDC Trading
            <span className="block bg-gradient-to-r from-primary via-blue-400 to-emerald-400 bg-clip-text text-transparent">
              Without Middlemen
            </span>
          </h1>

          <p className="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-muted-foreground">
            Buy and sell USDC directly with merchants using bank transfers, online payments, or in-person cash meetings. All trades secured by on-chain escrow.
          </p>

          <div className="mt-10 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
            <ConnectWallet size="lg" className="text-base px-8" />
            <Button variant="outline" size="lg" className="gap-2 text-base px-8" asChild>
              <a href="#merchants">
                Browse Merchants
                <ArrowRight weight="bold" className="size-4" />
              </a>
            </Button>
          </div>

          {/* Stats */}
          <div className="mt-16 grid grid-cols-2 gap-4 sm:grid-cols-4">
            {STATS.map(stat => (
              <Card key={stat.label} className="border-border/50 bg-card/50 backdrop-blur-sm">
                <CardContent className="flex flex-col items-center gap-1 pt-6">
                  <stat.icon weight="duotone" className="size-6 text-muted-foreground mb-1" />
                  <span className="font-mono text-2xl font-bold">{stat.value}</span>
                  <span className="text-sm text-muted-foreground">{stat.label}</span>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* How It Works */}
      <section className="border-t border-border/50 bg-muted/20">
        <div className="mx-auto max-w-6xl px-4 py-20 lg:px-6">
          <div className="text-center mb-12">
            <h2 className="text-3xl font-bold tracking-tight">How It Works</h2>
            <p className="mt-3 text-muted-foreground">Three simple steps to start trading</p>
          </div>

          <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
            {STEPS.map((step, i) => (
              <Card key={i} className="border-border/50 relative overflow-hidden">
                <CardContent className="pt-8 pb-8">
                  {/* Step number */}
                  <div className="absolute top-4 right-4 font-mono text-6xl font-bold text-muted/20">
                    {i + 1}
                  </div>
                  <div className={`mb-5 flex size-14 items-center justify-center rounded-2xl ${step.color}`}>
                    <step.icon weight="duotone" className="size-7" />
                  </div>
                  <h3 className="text-lg font-semibold mb-2">{step.title}</h3>
                  <p className="text-sm leading-relaxed text-muted-foreground">{step.desc}</p>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* Features Grid */}
      <section className="border-t border-border/50">
        <div className="mx-auto max-w-6xl px-4 py-20 lg:px-6">
          <div className="text-center mb-12">
            <h2 className="text-3xl font-bold tracking-tight">Built for Security</h2>
            <p className="mt-3 text-muted-foreground">Every feature designed to protect your trades</p>
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {FEATURES.map((feat, i) => (
              <div key={i} className="flex items-start gap-4 rounded-xl border border-border/50 bg-card p-5">
                <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                  <feat.icon weight="duotone" className="size-6 text-primary" />
                </div>
                <div>
                  <h4 className="text-base font-semibold">{feat.title}</h4>
                  <p className="mt-1 text-sm leading-relaxed text-muted-foreground">{feat.desc}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Merchants */}
      <section id="merchants" className="border-t border-border/50 bg-muted/20">
        <div className="mx-auto max-w-6xl px-4 py-20 lg:px-6">
          <div className="text-center mb-8">
            <h2 className="text-3xl font-bold tracking-tight">Find a Merchant</h2>
            <p className="mt-3 text-muted-foreground">Browse verified merchants and start trading</p>
          </div>

          <div className="mx-auto mb-8 max-w-md relative">
            <MagnifyingGlass className="absolute left-3 top-1/2 -translate-y-1/2 size-5 text-muted-foreground" />
            <Input placeholder="Search merchants by username..." className="pl-10" />
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {MERCHANTS.map(m => (
              <Link key={m.name} href={`/merchant/${m.name}`}>
                <Card className="border-border/50 transition-colors hover:border-primary/30 hover:bg-primary/5 cursor-pointer">
                  <CardContent className="pt-6">
                    <div className="flex items-center gap-4 mb-4">
                      <Avatar className="size-12">
                        <AvatarFallback className="bg-gradient-to-br from-primary to-blue-600 text-lg font-bold text-white">
                          {m.name.charAt(0)}
                        </AvatarFallback>
                      </Avatar>
                      <div className="flex-1">
                        <div className="flex items-center gap-2">
                          <span className="text-base font-semibold">{m.name}</span>
                          <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-sm font-semibold ${rankColors[m.rank]}`}>
                            {m.rank}
                          </span>
                        </div>
                        <div className="flex items-center gap-1.5 mt-1">
                          {m.badges.map(b => (
                            <span key={b} className="inline-flex items-center gap-1 rounded-full bg-blue-500/10 px-2 py-0.5 text-sm text-blue-400">
                              <SealCheck weight="fill" size={12} />{b}
                            </span>
                          ))}
                        </div>
                      </div>
                    </div>

                    <Separator className="mb-4 opacity-30" />

                    <div className="flex items-center justify-between text-sm">
                      <div className="text-center">
                        <p className="font-mono font-semibold">{m.trades.toLocaleString()}</p>
                        <p className="text-muted-foreground">Trades</p>
                      </div>
                      <div className="text-center">
                        <p className="font-mono font-semibold">{m.completion}%</p>
                        <p className="text-muted-foreground">Completion</p>
                      </div>
                      <div className="text-center">
                        <p className="font-mono font-semibold text-emerald-500">${m.available.toLocaleString()}</p>
                        <p className="text-muted-foreground">Available</p>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </Link>
            ))}
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="border-t border-border/50">
        <div className="mx-auto max-w-6xl px-4 py-20 text-center lg:px-6">
          <h2 className="text-3xl font-bold tracking-tight">Ready to Trade?</h2>
          <p className="mt-3 text-muted-foreground">Connect your wallet and start trading USDC in minutes</p>
          <div className="mt-8">
            <ConnectWallet size="lg" className="text-base px-10" />
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="border-t border-border/50 bg-card">
        <div className="mx-auto max-w-6xl px-4 py-8 lg:px-6">
          <div className="flex flex-col items-center gap-4 sm:flex-row sm:justify-between">
            <SiteLogo />
            <div className="flex items-center gap-6 text-sm text-muted-foreground">
              <a href="#" className="hover:text-foreground">Terms</a>
              <a href="#" className="hover:text-foreground">Privacy</a>
              <a href="#" className="hover:text-foreground">Support</a>
              <a href="#" className="text-primary hover:underline">BaseScan</a>
            </div>
          </div>
          <p className="mt-4 text-center text-sm text-muted-foreground">
            All trades are fully escrowed on the Base blockchain
          </p>
        </div>
      </footer>
    </div>
  )
}
