import { useEffect } from "react"
import { Link, router, usePage } from "@inertiajs/react"
import {
  Wallet,
  ShieldCheck,
  Lock,
  Lightning,
  Globe,
  ArrowLeft,
  Fingerprint,
  CheckCircle,
} from "@phosphor-icons/react"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Separator } from "@/components/ui/separator"
import ConnectWallet from "@/components/ConnectWallet"
import SiteLogo from "@/components/SiteLogo"
import { useWallet } from "@/hooks/useWallet"

const SECURITY_POINTS = [
  { icon: Lock, text: "No private keys stored on server" },
  { icon: Fingerprint, text: "Sign a unique nonce to prove wallet ownership" },
  { icon: ShieldCheck, text: "Session secured via encrypted token" },
  { icon: Lightning, text: "No email or password needed" },
]

export default function Connect() {
  const { isAuthenticated, isNewMerchant, token } = useWallet()

  // If token exists, redirect immediately — don't wait for api.me()
  const hasToken = !!token

  useEffect(() => {
    if (hasToken) {
      if (isAuthenticated && isNewMerchant) {
        router.visit("/setup")
      } else {
        const returnUrl = sessionStorage.getItem("returnUrl")
        sessionStorage.removeItem("returnUrl")
        router.visit(returnUrl || "/dashboard")
      }
    }
  }, [hasToken, isAuthenticated, isNewMerchant])

  if (hasToken) return null

  return (
    <div className="min-h-screen overflow-x-hidden bg-background">
      {/* Nav */}
      <header className="border-b border-border/50 bg-background/80 backdrop-blur-xl">
        <div className="mx-auto flex h-16 max-w-6xl items-center px-4 lg:px-6">
          <Link href="/"><SiteLogo /></Link>
        </div>
      </header>

      <div className="mx-auto max-w-5xl px-4 py-8 lg:py-16 lg:px-6">
        <div className="grid grid-cols-1 gap-8 lg:grid-cols-2">
          {/* Left — Connect */}
          <div>
            <h1 className="text-3xl font-bold tracking-tight mb-3">Connect Your Wallet</h1>
            <p className="text-muted-foreground leading-relaxed mb-8">
              Connect your Web3 wallet to access the P2P trading platform. Your wallet is your identity — no registration required.
            </p>

            <Card className="border-border/50">
              <CardContent className="pt-8 pb-8">
                <div className="flex flex-col items-center text-center gap-6">
                  <div className="flex size-20 items-center justify-center rounded-3xl bg-primary/10">
                    <Wallet weight="duotone" className="size-10 text-primary" />
                  </div>

                  <div>
                    <h2 className="text-xl font-semibold mb-2">Wallet Authentication</h2>
                    <p className="text-sm text-muted-foreground max-w-sm">
                      Click below to connect your wallet. You'll be asked to sign a message to verify ownership — no transaction or gas fee required.
                    </p>
                  </div>

                  {usePage().props.features?.merchant_registration_enabled === false && (
                    <div className="w-full max-w-xs rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-400">
                      New merchant registration is currently paused. Existing wallets can still sign in.
                    </div>
                  )}

                  <ConnectWallet size="lg" className="w-full max-w-xs text-base" />

                  <div className="flex items-center gap-2 rounded-lg bg-muted/30 px-4 py-2.5 w-full max-w-xs">
                    <Globe weight="duotone" className="size-4 text-blue-400 shrink-0" />
                    <span className="text-sm text-muted-foreground">
                      Connecting to <span className="font-semibold text-foreground">Base Network</span>
                    </span>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Right — How it works */}
          <div>
            <h2 className="text-xl font-semibold mb-6">How Wallet Auth Works</h2>

            {/* Steps */}
            <div className="space-y-4 mb-8">
              {[
                { step: 1, title: "Connect Wallet", desc: "Choose MetaMask, Trust Wallet, Coinbase, or WalletConnect" },
                { step: 2, title: "Sign Message", desc: "Sign a unique nonce to prove you own the wallet — free, no gas" },
                { step: 3, title: "Session Created", desc: "Server verifies signature and creates a secure session" },
                { step: 4, title: "Start Trading", desc: "Access your merchant dashboard and start P2P trades" },
              ].map((item) => (
                <div key={item.step} className="flex items-start gap-4">
                  <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-base font-bold text-primary">
                    {item.step}
                  </div>
                  <div className="pt-1">
                    <h3 className="text-base font-semibold">{item.title}</h3>
                    <p className="text-sm text-muted-foreground">{item.desc}</p>
                  </div>
                </div>
              ))}
            </div>

            <Separator className="mb-8 opacity-30" />

            {/* Security */}
            <h3 className="text-base font-semibold mb-4 flex items-center gap-2">
              <ShieldCheck weight="duotone" className="size-5 text-emerald-500" />
              Security Guarantees
            </h3>
            <div className="space-y-3">
              {SECURITY_POINTS.map((point, i) => (
                <div key={i} className="flex items-center gap-3 rounded-lg bg-muted/20 px-4 py-3">
                  <point.icon weight="duotone" className="size-5 text-emerald-500 shrink-0" />
                  <span className="text-sm text-muted-foreground">{point.text}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
