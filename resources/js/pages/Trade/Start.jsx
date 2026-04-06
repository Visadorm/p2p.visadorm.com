import { useState, useEffect } from "react"
import { Link, router } from "@inertiajs/react"
import { ethers } from "ethers"
import { toast } from "sonner"
import { ERC20_ABI, STAKE_AMOUNT, useBlockchainConfig } from "@/lib/contracts"
import {
  Info,
  Warning,
  ArrowLeft,
  Bank,
  Globe,
  CreditCard,
  DeviceMobile,
  Handshake,
  CurrencyDollar,
  ShieldCheck,
} from "@phosphor-icons/react"
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Separator } from "@/components/ui/separator"
import { Avatar, AvatarFallback } from "@/components/ui/avatar"
import { Skeleton } from "@/components/ui/skeleton"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import ConnectWallet from "@/components/ConnectWallet"
import SiteLogo from "@/components/SiteLogo"
import RiskWarning from "@/components/RiskWarning"
import MerchantRankBadge from "@/components/MerchantRankBadge"
import { useWallet } from "@/hooks/useWallet"
import { api } from "@/lib/api"

const currencyNameFormatter = new Intl.DisplayNames(["en"], { type: "currency" })
function getCurrencyName(code) {
  try { return currencyNameFormatter.of(code) } catch { return code }
}

const PAYMENT_ICONS = {
  bank: Bank,
  bank_transfer: Bank,
  wise: Globe,
  paypal: CreditCard,
  zelle: CurrencyDollar,
  mobile: DeviceMobile,
  mobile_payment: DeviceMobile,
  cash: Handshake,
  cash_meeting: Handshake,
}


export default function TradeStart({ slug }) {
  const { isAuthenticated, signer: walletSigner, phraseWallet, isCorrectChain } = useWallet()
  const { usdcAddress, escrowAddress, rpcUrl } = useBlockchainConfig()

  // Read prefilled values from query params (from merchant profile page)
  const params = typeof window !== "undefined" ? new URLSearchParams(window.location.search) : null
  const prefillAmount = params?.get("amount") || ""
  const prefillCurrency = params?.get("currency") || ""

  const [amount, setAmount] = useState(prefillAmount)
  const [currency, setCurrency] = useState(prefillCurrency)
  const [paymentMethod, setPaymentMethod] = useState("")
  const [submitting, setSubmitting] = useState(false)

  // API data
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [tradingLink, setTradingLink] = useState(null)
  const [merchant, setMerchant] = useState(null)
  const [currencies, setCurrencies] = useState([])
  const [paymentMethods, setPaymentMethods] = useState([])
  const [escrowBalance, setEscrowBalance] = useState(0)

  // Fetch trading link details on mount
  useEffect(() => {
    setLoading(true)
    setError(null)
    api.getTradingLinkDetails(slug)
      .then((res) => {
        const { trading_link, merchant: m, currencies: c, payment_methods: pm, escrow_balance } = res.data
        setTradingLink(trading_link)
        setMerchant(m)
        setCurrencies(c || [])
        setPaymentMethods(pm || [])
        setEscrowBalance(escrow_balance || 0)
        // Default to first currency if no prefill
        if (c && c.length > 0 && !prefillCurrency) {
          setCurrency(c[0].currency_code)
        }
      })
      .catch((err) => {
        setError(err.message || "Failed to load trading link details")
        toast.error(err.message || "Failed to load trading link details")
      })
      .finally(() => setLoading(false))
  }, [slug])

  const selectedCurrency = currencies.find((c) => c.currency_code === currency)
  const marketRate = selectedCurrency ? (Number(selectedCurrency.market_rate) || 0) : 0
  const markup = selectedCurrency ? (Number(selectedCurrency.markup_percent) || 0) : 0
  const rate = marketRate * (1 + markup / 100)
  const numAmount = Number(amount) || 0
  const fiatAmount = numAmount * rate

  // Min/max come from the selected currency, not the trading link
  const minAmount = selectedCurrency ? Number(selectedCurrency.min_amount) || 0 : 0
  const maxAmount = selectedCurrency ? Number(selectedCurrency.max_amount) || 0 : 0

  const [tradeStep, setTradeStep] = useState("idle") // idle | approving | initiating

  const handleStartTrade = async () => {
    if (!amount || !currency || !paymentMethod) {
      toast.error("Please fill all fields")
      return
    }
    if (numAmount < minAmount || (maxAmount > 0 && numAmount > maxAmount)) {
      toast.error(`Amount must be between ${minAmount} and ${maxAmount} USDC`)
      return
    }

    const signer = phraseWallet || walletSigner
    if (!signer) {
      toast.error("Wallet not connected")
      return
    }

    setSubmitting(true)
    try {
      // Step 0: Pre-check — verify no active trade with this merchant before spending gas
      try {
        await api.initiateTrade(slug, {
          amount_usdc: numAmount,
          currency_code: currency,
          payment_method: paymentMethod,
          dry_run: true,
        })
      } catch (preErr) {
        toast.error(preErr.message || "Cannot initiate trade")
        setSubmitting(false)
        setTradeStep("idle")
        return
      }

      // Step 1: Approve $5 USDC stake for escrow contract (public trades only)
      const isPrivateLink = tradingLink?.type === "private"
      if (usdcAddress && escrowAddress && !isPrivateLink) {
        const signerForTx = phraseWallet
          ? phraseWallet.connect(new ethers.providers.JsonRpcProvider(rpcUrl || "https://sepolia.base.org"))
          : signer

        const usdc = new ethers.Contract(usdcAddress, ERC20_ABI, signerForTx)
        const signerAddress = await signerForTx.getAddress()

        // Check buyer has enough USDC for the stake
        const balance = await usdc.balanceOf(signerAddress)
        if (balance.lt(STAKE_AMOUNT)) {
          toast.error("Insufficient USDC balance. You need at least $5 USDC for the anti-spam stake.")
          setSubmitting(false)
          setTradeStep("idle")
          return
        }

        const allowance = await usdc.allowance(signerAddress, escrowAddress)

        if (allowance.lt(STAKE_AMOUNT)) {
          setTradeStep("approving")
          const approveTx = await usdc.approve(escrowAddress, STAKE_AMOUNT)
          await approveTx.wait()
          await new Promise((r) => setTimeout(r, 1500))
        }
      }

      // Step 2: Call API to initiate trade (backend locks escrow on-chain)
      setTradeStep("initiating")
      const res = await api.initiateTrade(slug, {
        amount_usdc: numAmount,
        currency_code: currency,
        payment_method: paymentMethod,
      })
      toast.success(res.message || "Trade initiated")
      const tradeHash = res.data?.trade_hash
      if (tradeHash) {
        const isCashMeeting = paymentMethod === "cash_meeting" || paymentMethods.find(m => (m.provider || m.label) === paymentMethod)?.type === "cash_meeting"
        router.visit(isCashMeeting ? `/trade/${tradeHash}/meeting` : `/trade/${tradeHash}/confirm`)
      }
    } catch (err) {
      const isRejection = err?.code === 4001 || err?.code === "ACTION_REJECTED"
      toast.error(isRejection ? "Transaction cancelled" : (err.message || "Failed to initiate trade"))
    } finally {
      setSubmitting(false)
      setTradeStep("idle")
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
          <Skeleton className="h-24 w-full rounded-xl" />
          <Skeleton className="h-96 w-full rounded-xl" />
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
              <Link href="/" className="text-sm text-muted-foreground hover:text-foreground">
                Go back home
              </Link>
            </CardContent>
          </Card>
        </div>
      </div>
    )
  }

  const rankName = merchant?.rank?.name || "New Member"

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
          href={`/merchant/${merchant?.username || slug}`}
          className="mb-6 inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
        >
          <ArrowLeft weight="bold" size={16} />
          Back to Merchant
        </Link>

        {/* Merchant Mini Header */}
        <Card className="mb-6 border-border/50">
          <CardContent className="flex items-center gap-4 py-4">
            <Avatar className="size-12">
              <AvatarFallback className="bg-gradient-to-br from-primary to-blue-600 text-lg font-bold text-white">
                {merchant?.username?.charAt(0) || "?"}
              </AvatarFallback>
            </Avatar>
            <div className="flex-1">
              <div className="flex items-center gap-3">
                <h2 className="text-lg font-bold">{merchant?.username || "Merchant"}</h2>
                <MerchantRankBadge rank={rankName} />
              </div>
              <div className="flex items-center gap-4 mt-1 text-sm text-muted-foreground">
                {merchant?.total_trades != null && (
                  <span>{merchant.total_trades} trades</span>
                )}
                {merchant?.completion_rate != null && (
                  <span>{merchant.completion_rate}% completion</span>
                )}
                {merchant?.avg_response_minutes != null && (
                  <span>~{merchant.avg_response_minutes}min response</span>
                )}
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Trade Form */}
        <Card className="border-border/50">
          <CardHeader>
            <CardTitle className="text-xl">Start a Trade</CardTitle>
          </CardHeader>
          <CardContent className="space-y-6">
            {/* Amount Input */}
            <div className="space-y-2">
              <Label>Amount</Label>
              <div className="relative">
                <Input
                  type="number"
                  placeholder="Enter amount"
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                  className="pr-16"
                />
                <span className="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-medium text-muted-foreground">
                  USDC
                </span>
              </div>
              <div className="flex items-center justify-between text-sm text-muted-foreground">
                <span>
                  Min: <span className="font-mono">${minAmount}</span>
                </span>
                <span>
                  Max: <span className="font-mono">${maxAmount > 0 ? maxAmount.toLocaleString() : "No limit"}</span>
                </span>
              </div>
            </div>

            {/* Currency Selector */}
            <div className="space-y-2">
              <Label>Currency</Label>
              <Select value={currency} onValueChange={setCurrency}>
                <SelectTrigger>
                  <SelectValue placeholder="Select currency" />
                </SelectTrigger>
                <SelectContent>
                  {currencies.map((c) => (
                    <SelectItem key={c.currency_code} value={c.currency_code}>
                      {c.currency_code} — {getCurrencyName(c.currency_code)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {rate > 0 && (
                <div className="flex items-center justify-between rounded-lg bg-muted/30 px-4 py-3">
                  <span className="text-sm text-muted-foreground">Rate</span>
                  <span className="font-mono text-sm font-semibold">
                    1 USDC = {rate.toLocaleString()} {currency}
                  </span>
                </div>
              )}
              {numAmount > 0 && rate > 0 && (
                <div className="flex items-center justify-between rounded-lg bg-emerald-500/10 border border-emerald-500/20 px-4 py-3">
                  <span className="text-sm text-muted-foreground">You pay</span>
                  <span className="font-mono text-base font-bold text-emerald-400">
                    {fiatAmount.toLocaleString()} {currency}
                  </span>
                </div>
              )}
            </div>

            {/* Payment Method */}
            <div className="space-y-2">
              <Label>Payment Method</Label>
              <Select value={paymentMethod} onValueChange={setPaymentMethod}>
                <SelectTrigger>
                  <SelectValue placeholder="Select payment method" />
                </SelectTrigger>
                <SelectContent>
                  {paymentMethods.map((m) => (
                    <SelectItem key={m.id || m.provider || m.label} value={m.provider || m.label}>
                      {m.label || m.provider}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {/* Buyer Verification Info */}
            {merchant?.buyer_verification === "required" && (
              <div className="flex items-start gap-2.5 rounded-lg border border-red-500/20 bg-red-500/5 px-4 py-3">
                <ShieldCheck weight="fill" size={18} className="mt-0.5 shrink-0 text-red-400" />
                <div>
                  <p className="text-sm font-medium text-red-400">ID Verification Required</p>
                  <p className="text-sm text-muted-foreground">
                    This merchant requires approved KYC verification before you can trade. Upload your ID on the KYC page first.
                  </p>
                </div>
              </div>
            )}
            {merchant?.buyer_verification === "optional" && (
              <div className="flex items-start gap-2.5 rounded-lg border border-amber-500/20 bg-amber-500/5 px-4 py-3">
                <ShieldCheck weight="fill" size={18} className="mt-0.5 shrink-0 text-amber-400" />
                <div>
                  <p className="text-sm font-medium">ID Verification Recommended</p>
                  <p className="text-sm text-muted-foreground">
                    This merchant encourages buyers to verify their identity for faster trades
                  </p>
                </div>
              </div>
            )}

            {/* Trade Instructions */}
            {merchant?.trade_instructions && (
              <div className="flex items-start gap-2.5 rounded-lg border border-border/50 bg-muted/10 px-4 py-3">
                <Info weight="fill" size={18} className="mt-0.5 shrink-0 text-muted-foreground" />
                <div>
                  <p className="text-sm font-medium">Merchant Instructions</p>
                  <p className="text-sm text-muted-foreground">{merchant.trade_instructions}</p>
                </div>
              </div>
            )}

            <Separator />

            {/* Stake Info — only for public links */}
            {tradingLink?.type !== "private" && (
              <div className="flex items-start gap-2.5 rounded-lg border border-primary/20 bg-primary/5 px-4 py-3">
                <Info weight="fill" size={18} className="mt-0.5 shrink-0 text-primary" />
                <div>
                  <p className="text-sm font-medium">$5 USDC anti-spam stake</p>
                  <p className="text-sm text-muted-foreground">
                    Refunded after successful trade. Forfeited if you cancel.
                  </p>
                </div>
              </div>
            )}

            {/* Escrow Balance */}
            {escrowBalance != null && (
              <div className="flex items-center justify-between rounded-lg bg-muted/30 px-4 py-3">
                <span className="text-sm text-muted-foreground">Merchant Escrow Balance</span>
                <span className="font-mono text-sm font-semibold text-emerald-400">
                  {Number(escrowBalance).toLocaleString()} USDC
                </span>
              </div>
            )}

            {/* Start Trade / Connect Wallet */}
            {isAuthenticated ? (
              <Button
                size="lg"
                className="w-full text-base font-semibold"
                disabled={submitting || !amount || !currency || !paymentMethod}
                onClick={handleStartTrade}
              >
                {tradeStep === "approving" ? "Approving USDC Stake..." : tradeStep === "initiating" ? "Initiating Trade..." : "Start Trade"}
              </Button>
            ) : (
              <ConnectWallet size="lg" className="w-full text-base font-semibold" />
            )}

            {/* Risk Warning */}
            <RiskWarning />

            {/* Cancel */}
            <div className="text-center">
              <Link
                href={`/merchant/${merchant?.username || slug}`}
                className="text-sm text-muted-foreground transition-colors hover:text-foreground"
              >
                Cancel and go back
              </Link>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
