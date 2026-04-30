import { useState } from "react"
import { Link, router, usePage } from "@inertiajs/react"
import { toast } from "sonner"
import {
  Wallet,
  Copy,
  SignOut,
  CheckCircle,
  ArrowsLeftRight,
  CaretDown,
  Warning,
  Globe,
  Lightning,
  ArrowLeft,
  Eye,
  EyeSlash,
  Key,
  LockKey,
  SquaresFour,
} from "@phosphor-icons/react"
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from "@/components/ui/dialog"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Button } from "@/components/ui/button"
import { Separator } from "@/components/ui/separator"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { useWallet } from "@/hooks/useWallet"

// ─── Wallet logos ──────────────────────────────────────────────────
function MetaMaskLogo({ size = 32 }) {
  return (
    <svg width={size} height={size} viewBox="0 0 40 40" fill="none">
      <rect width="40" height="40" rx="10" fill="#F6851B" fillOpacity="0.1" />
      <path d="M30.5 8L21.2 14.9L23 10.9L30.5 8Z" fill="#E2761B" stroke="#E2761B" strokeWidth="0.25" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M9.5 8L18.7 15L17 10.9L9.5 8Z" fill="#E4761B" stroke="#E4761B" strokeWidth="0.25" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M27.1 25.5L24.5 29.5L30 31L31.2 25.6L27.1 25.5Z" fill="#E4761B" stroke="#E4761B" strokeWidth="0.25" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M8.8 25.6L10 31L15.5 29.5L12.9 25.5L8.8 25.6Z" fill="#E4761B" stroke="#E4761B" strokeWidth="0.25" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M15.2 18.5L14 20.3L19.5 20.5L19.3 14.5L15.2 18.5Z" fill="#E4761B" stroke="#E4761B" strokeWidth="0.25" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M24.8 18.5L20.6 14.4L20.5 20.5L26 20.3L24.8 18.5Z" fill="#E4761B" stroke="#E4761B" strokeWidth="0.25" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M15.5 29.5L19.2 27.8L16 25.7L15.5 29.5Z" fill="#E4761B" stroke="#E4761B" strokeWidth="0.25" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M20.8 27.8L24.5 29.5L24 25.7L20.8 27.8Z" fill="#E4761B" stroke="#E4761B" strokeWidth="0.25" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  )
}

function TrustWalletLogo({ size = 32 }) {
  return (
    <svg width={size} height={size} viewBox="0 0 40 40" fill="none">
      <rect width="40" height="40" rx="10" fill="#3375BB" fillOpacity="0.1" />
      <path d="M20 10C20 10 12 14 12 20C12 26 20 32 20 32C20 32 28 26 28 20C28 14 20 10 20 10Z" fill="#3375BB" />
      <path d="M20 14L18 19H22L20 14Z" fill="white" />
      <path d="M18 19L16 26L20 23L18 19Z" fill="white" fillOpacity="0.8" />
      <path d="M22 19L24 26L20 23L22 19Z" fill="white" fillOpacity="0.8" />
    </svg>
  )
}

function CoinbaseLogo({ size = 32 }) {
  return (
    <svg width={size} height={size} viewBox="0 0 40 40" fill="none">
      <rect width="40" height="40" rx="10" fill="#0052FF" fillOpacity="0.1" />
      <circle cx="20" cy="20" r="10" fill="#0052FF" />
      <rect x="16" y="16" width="8" height="8" rx="2" fill="white" />
    </svg>
  )
}

function WalletConnectLogo({ size = 32 }) {
  return (
    <svg width={size} height={size} viewBox="0 0 40 40" fill="none">
      <rect width="40" height="40" rx="10" fill="#3B99FC" fillOpacity="0.1" />
      <path d="M14.5 17C17.5 14 22.5 14 25.5 17L26 17.5C26.2 17.7 26.2 18 26 18.2L24.8 19.4C24.7 19.5 24.5 19.5 24.4 19.4L24 19C21.8 16.8 18.2 16.8 16 19L15.5 19.5C15.4 19.6 15.2 19.6 15.1 19.5L13.9 18.3C13.7 18.1 13.7 17.8 13.9 17.6L14.5 17ZM28 19.5L29 20.5C29.2 20.7 29.2 21 29 21.2L23.5 26.7C23.3 26.9 23 26.9 22.8 26.7L19.5 23.4C19.4 23.3 19.3 23.3 19.2 23.4L15.9 26.7C15.7 26.9 15.4 26.9 15.2 26.7L9.7 21.2C9.5 21 9.5 20.7 9.7 20.5L10.7 19.5C10.9 19.3 11.2 19.3 11.4 19.5L14.7 22.8C14.8 22.9 14.9 22.9 15 22.8L18.3 19.5C18.5 19.3 18.8 19.3 19 19.5L22.3 22.8C22.4 22.9 22.5 22.9 22.6 22.8L25.9 19.5C26.1 19.3 26.4 19.3 26.6 19.5L28 19.5Z" fill="#3B99FC" />
    </svg>
  )
}

function PhraseLogo({ size = 32 }) {
  return (
    <div
      style={{ width: size, height: size }}
      className="flex items-center justify-center rounded-[10px] bg-emerald-500/10"
    >
      <Key weight="duotone" size={size * 0.55} className="text-emerald-400" />
    </div>
  )
}

// ─── Wallet list ───────────────────────────────────────────────────
const WALLETS = [
  { id: "metamask",     name: "MetaMask",               icon: MetaMaskLogo,      description: "Connect using browser extension",  popular: true  },
  { id: "trust",        name: "Trust Wallet",            icon: TrustWalletLogo,   description: "Extension or scan QR with mobile app", popular: true  },
  { id: "coinbase",     name: "Coinbase Wallet",         icon: CoinbaseLogo,      description: "Extension or scan QR with mobile app", popular: false },
  { id: "walletconnect",name: "WalletConnect",           icon: WalletConnectLogo, description: "Scan QR with any mobile wallet",    popular: false },
  { id: "phrase",       name: "Import Recovery Phrase",  icon: PhraseLogo,        description: "Use your 12 or 24-word seed phrase",popular: false },
]

function truncateAddress(addr) {
  if (!addr) return ""
  return `${addr.slice(0, 6)}...${addr.slice(-4)}`
}

// ─── Component ────────────────────────────────────────────────────
export default function ConnectWallet({ variant = "default", size = "default", className = "" }) {
  const [modalOpen, setModalOpen] = useState(false)
  const [view, setView] = useState("wallets") // "wallets" | "import_phrase"
  const [copied, setCopied] = useState(false)

  // Phrase import form state
  const [phrase, setPhrase] = useState("")
  const [password, setPassword] = useState("")
  const [confirmPassword, setConfirmPassword] = useState("")
  const [showPassword, setShowPassword] = useState(false)
  const [phraseSubmitting, setPhraseSubmitting] = useState(false)

  const {
    address, isConnected, isAuthenticated, isCorrectChain, isNewMerchant,
    connecting, authenticating, connectedWallet,
    connect, disconnect, switchChain,
  } = useWallet()

  const isBusy = connecting || authenticating || phraseSubmitting

  // ── redirect helper ──────────────────────────────────────────────
  function redirectAfterAuth(merchantData) {
    const isNew = !merchantData.username || merchantData.username.startsWith("user_")
    if (isNew) {
      router.visit("/setup")
    } else {
      const returnUrl = sessionStorage.getItem("returnUrl")
      sessionStorage.removeItem("returnUrl")
      router.visit(returnUrl || "/dashboard")
    }
  }

  // Save current page so we can return after auth
  function saveReturnUrl() {
    const path = window.location.pathname + window.location.search
    // Don't save auth pages as return targets
    if (!path.startsWith("/connect") && !path.startsWith("/setup") && path !== "/") {
      sessionStorage.setItem("returnUrl", path)
    }
  }

  // ── injected wallet handler ──────────────────────────────────────
  const handleConnect = async (walletId) => {
    saveReturnUrl()
    try {
      const merchantData = await connect(walletId)
      setModalOpen(false)
      toast.success("Wallet connected & authenticated")
      redirectAfterAuth(merchantData)
    } catch (err) {
      const isRejection =
        err?.code === 4001 ||
        err?.message?.toLowerCase().includes("user rejected") ||
        err?.message?.toLowerCase().includes("user denied") ||
        err?.message?.toLowerCase().includes("cancelled")
      toast.error(isRejection ? "Connection cancelled" : (err?.message || "Authentication failed. Please try again."))
    }
  }

  // ── phrase import handler ────────────────────────────────────────
  const handlePhraseImport = async (e) => {
    e.preventDefault()

    if (password !== confirmPassword) {
      toast.error("Passwords do not match.")
      return
    }
    if (password.length < 8) {
      toast.error("Password must be at least 8 characters.")
      return
    }

    saveReturnUrl()
    setPhraseSubmitting(true)
    try {
      const merchantData = await connect("phrase", { phrase, password })
      setModalOpen(false)
      resetModal()
      toast.success("Wallet imported & authenticated")
      redirectAfterAuth(merchantData)
    } catch (err) {
      toast.error(err?.message || "Failed to import wallet. Please check your phrase.")
    } finally {
      setPhraseSubmitting(false)
    }
  }

  // ── misc ─────────────────────────────────────────────────────────
  const resetModal = () => {
    setView("wallets")
    setPhrase("")
    setPassword("")
    setConfirmPassword("")
    setShowPassword(false)
  }

  const handleCopy = () => {
    if (address) {
      navigator.clipboard.writeText(address)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    }
  }

  const handleDisconnect = () => {
    disconnect()
    toast("Wallet disconnected")
    router.visit("/connect")
  }

  // ── wrong network (only show after connection settles, not during connect) ──
  if (isConnected && !isCorrectChain && !connecting && !authenticating) {
    return (
      <Button onClick={switchChain} variant="destructive" size={size} className={`gap-2 ${className}`}>
        <Warning weight="bold" className="size-4" />
        Wrong Network
      </Button>
    )
  }

  // ── connected + authenticated → dropdown ─────────────────────────
  if (isConnected && isAuthenticated) {
    return (
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            variant={variant === "outline" ? "outline" : "secondary"}
            size={size}
            className={`gap-2 font-mono ${className}`}
          >
            <span className="size-2 rounded-full bg-emerald-500 shadow-[0_0_6px_rgba(34,197,94,0.5)]" />
            {truncateAddress(address)}
            <CaretDown weight="bold" className="size-3.5 text-muted-foreground" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-64">
          <div className="px-3 py-3">
            <div className="flex items-center gap-3">
              <div className="flex size-10 items-center justify-center rounded-full bg-primary/10">
                <Wallet weight="duotone" className="size-5 text-primary" />
              </div>
              <div className="flex-1 min-w-0">
                <p className="text-sm font-semibold">Connected</p>
                <p className="truncate font-mono text-sm text-muted-foreground">{truncateAddress(address)}</p>
              </div>
            </div>
          </div>

          <DropdownMenuSeparator />

          <div className="px-3 py-2">
            <div className="flex items-center justify-between">
              <span className="text-sm text-muted-foreground">Network</span>
              <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-500/15 px-2.5 py-0.5 text-sm font-medium text-blue-400">
                <Globe weight="fill" className="size-3" />
                Base
              </span>
            </div>
          </div>

          <DropdownMenuSeparator />

          <DropdownMenuItem className="gap-2 cursor-pointer" asChild>
            <Link href="/dashboard">
              <SquaresFour weight="duotone" className="size-4" />
              Dashboard
            </Link>
          </DropdownMenuItem>

          <DropdownMenuItem onClick={handleCopy} className="gap-2 cursor-pointer">
            {copied ? (
              <><CheckCircle weight="fill" className="size-4 text-emerald-500" /><span className="text-emerald-500">Copied!</span></>
            ) : (
              <><Copy weight="duotone" className="size-4" />Copy Address</>
            )}
          </DropdownMenuItem>

          <DropdownMenuItem className="gap-2 cursor-pointer" asChild>
            <a href={`https://sepolia.basescan.org/address/${address}`} target="_blank" rel="noreferrer">
              <ArrowsLeftRight weight="duotone" className="size-4" />
              View on BaseScan
            </a>
          </DropdownMenuItem>

          <DropdownMenuSeparator />

          <DropdownMenuItem onClick={handleDisconnect} className="gap-2 cursor-pointer text-red-400 focus:text-red-400">
            <SignOut weight="duotone" className="size-4" />
            Disconnect
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    )
  }

  // ── not connected → button + modal ───────────────────────────────
  return (
    <>
      <Button
        onClick={() => { resetModal(); setModalOpen(true) }}
        size={size}
        className={`gap-2 ${className}`}
        disabled={isBusy}
      >
        <Wallet weight="bold" className="size-4" />
        {authenticating ? "Authenticating..." : connecting ? "Connecting..." : "Connect Wallet"}
      </Button>

      <Dialog open={modalOpen} onOpenChange={(open) => { if (!open) { setModalOpen(false); resetModal() } else setModalOpen(true) }}>
        <DialogContent className="sm:max-w-md">

          {/* ── Wallet list view ── */}
          {view === "wallets" && (
            <>
              <DialogHeader>
                <DialogTitle className="text-xl">Connect Wallet</DialogTitle>
                <DialogDescription>Choose a wallet to connect to {usePage().props.site?.name || 'Visadorm P2P'}</DialogDescription>
              </DialogHeader>

              <div className="mt-2 space-y-2">
                {WALLETS.map((wallet) => (
                  <button
                    key={wallet.id}
                    onClick={() => {
                      if (wallet.id === "phrase") {
                        setView("import_phrase")
                      } else {
                        handleConnect(wallet.id)
                      }
                    }}
                    disabled={isBusy}
                    className="flex w-full items-center gap-4 rounded-xl border border-border/50 bg-card p-4 transition-all hover:border-primary/30 hover:bg-primary/5 disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    <wallet.icon size={40} />
                    <div className="flex-1 text-left">
                      <div className="flex items-center gap-2">
                        <span className="text-base font-semibold">{wallet.name}</span>
                        {wallet.popular && (
                          <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-sm font-medium text-primary">
                            <Lightning weight="fill" size={12} />
                            Popular
                          </span>
                        )}
                      </div>
                      <p className="text-sm text-muted-foreground">{wallet.description}</p>
                    </div>
                    <CaretDown weight="bold" className="size-4 -rotate-90 text-muted-foreground" />
                  </button>
                ))}
              </div>

              <Separator className="my-2" />

              <div className="text-center">
                <p className="text-sm text-muted-foreground">
                  By connecting, you agree to the{" "}
                  <a href="#" className="text-primary hover:underline">Terms of Service</a>
                </p>
              </div>

              <div className="flex items-center justify-center gap-2 rounded-lg bg-muted/30 px-4 py-3">
                <Globe weight="duotone" className="size-4 text-blue-400" />
                <span className="text-sm text-muted-foreground">
                  Connecting to <span className="font-semibold text-foreground">Base Network</span>
                </span>
              </div>
            </>
          )}

          {/* ── Phrase import view ── */}
          {view === "import_phrase" && (
            <>
              <DialogHeader>
                <div className="flex items-center gap-3 mb-1">
                  <button
                    onClick={() => setView("wallets")}
                    className="flex size-8 items-center justify-center rounded-lg bg-muted/50 hover:bg-muted transition-colors"
                  >
                    <ArrowLeft weight="bold" className="size-4" />
                  </button>
                  <DialogTitle className="text-xl">Import Recovery Phrase</DialogTitle>
                </div>
                <DialogDescription>
                  Enter your 12 or 24-word recovery phrase. It is encrypted locally with your password — never sent to any server.
                </DialogDescription>
              </DialogHeader>

              <form onSubmit={handlePhraseImport} className="space-y-4 mt-2">
                {/* Mnemonic phrase */}
                <div className="space-y-2">
                  <Label>Recovery Phrase</Label>
                  <textarea
                    value={phrase}
                    onChange={(e) => setPhrase(e.target.value)}
                    placeholder="Enter your 12 or 24 word recovery phrase, separated by spaces..."
                    rows={4}
                    required
                    className="w-full resize-none rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring font-mono"
                  />
                  <p className="text-xs text-muted-foreground">Works with MetaMask, Trust Wallet, Coinbase, Visadorm, or any BIP39 wallet</p>
                </div>

                {/* Password */}
                <div className="space-y-2">
                  <Label>Password</Label>
                  <div className="relative">
                    <Input
                      type={showPassword ? "text" : "password"}
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      placeholder="Create a password for your wallet"
                      required
                      minLength={8}
                      className="pr-10"
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword((v) => !v)}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    >
                      {showPassword ? <EyeSlash weight="bold" className="size-4" /> : <Eye weight="bold" className="size-4" />}
                    </button>
                  </div>
                  <p className="text-xs text-muted-foreground">Losing this password means losing wallet access on this device. Visadorm can't reset it.</p>
                </div>

                {/* Confirm password */}
                <div className="space-y-2">
                  <Label>Confirm Password</Label>
                  <Input
                    type={showPassword ? "text" : "password"}
                    value={confirmPassword}
                    onChange={(e) => setConfirmPassword(e.target.value)}
                    placeholder="Confirm your password"
                    required
                  />
                </div>

                {/* Security notice */}
                <div className="flex items-start gap-3 rounded-lg bg-emerald-500/8 px-3 py-3">
                  <LockKey weight="duotone" className="size-5 text-emerald-400 shrink-0 mt-0.5" />
                  <p className="text-xs text-muted-foreground leading-relaxed">
                    Your phrase is encrypted on your device using AES-256-GCM with PBKDF2 (100,000 iterations). The raw phrase and private key never leave your browser.
                  </p>
                </div>

                <Button type="submit" className="w-full gap-2" disabled={phraseSubmitting}>
                  <Key weight="bold" className="size-4" />
                  {phraseSubmitting ? "Importing..." : "Import & Connect"}
                </Button>
              </form>
            </>
          )}

        </DialogContent>
      </Dialog>
    </>
  )
}
