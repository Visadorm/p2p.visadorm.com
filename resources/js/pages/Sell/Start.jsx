import { useEffect, useState } from "react"
import { router, usePage } from "@inertiajs/react"
import { ethers } from "ethers"
import { toast } from "sonner"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Switch } from "@/components/ui/switch"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Skeleton } from "@/components/ui/skeleton"
import { SpinnerIcon } from "@phosphor-icons/react"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import PublicHeader from "@/components/PublicHeader"
import PublicFooter from "@/components/PublicFooter"
import ConnectWallet from "@/components/ConnectWallet"
import { useWallet } from "@/hooks/useWallet"
import { api } from "@/lib/api"
import {
  ESCROW_SELL_ABI,
  ERC20_ABI,
  computeSellApproveAmount,
  useBlockchainConfig,
} from "@/lib/contracts"

export default function SellStart({ merchantUsername }) {
  const { features } = usePage().props
  const { merchant: caller, signer, phraseWallet, isAuthenticated } = useWallet()
  const { usdcAddress, escrowAddress } = useBlockchainConfig()

  const initialParams = typeof window !== "undefined" ? new URLSearchParams(window.location.search) : null
  const initialAmount = initialParams?.get("amount") || ""

  const [merchant, setMerchant] = useState(null)
  const [loading, setLoading] = useState(true)
  const [amount, setAmount] = useState(initialAmount)
  const [paymentMethodId, setPaymentMethodId] = useState("")
  const [isCash, setIsCash] = useState(false)
  const [meetingLocation, setMeetingLocation] = useState("")
  const [submitting, setSubmitting] = useState(false)
  const [step, setStep] = useState("idle") // idle | approving | opening | confirming

  useEffect(() => {
    api.getMerchantProfile?.(merchantUsername)
      ?.then((res) => setMerchant(res?.data?.merchant ?? null))
      ?.catch(() => setMerchant(null))
      ?.finally(() => setLoading(false))
  }, [merchantUsername])

  useEffect(() => {
    if (!features?.sell_enabled) router.visit("/")
  }, [features?.sell_enabled])

  // Effective rate = market_rate * (1 + markup_percent / 100)
  const firstCurrency = merchant?.currencies?.[0]
  const fiatRate = firstCurrency
    ? Number(firstCurrency.market_rate ?? 1) * (1 + Number(firstCurrency.markup_percent ?? 0) / 100)
    : 1
  const fiatCurrency = firstCurrency?.currency_code ?? "USD"
  const paymentMethods = (merchant?.payment_methods ?? []).filter((m) => {
    if (isCash) return m.type === "cash_meeting"
    return m.type !== "cash_meeting"
  })

  const requireStake = isCash
    ? features?.sell_require_stake_cash
    : features?.sell_require_stake_public
  const stakeUsdc = features?.sell_anti_spam_stake_usdc ?? 5
  const approveAmount = amount ? computeSellApproveAmount(amount, { stakeUsdc, requireStake }) : "0"

  async function handleSubmit() {
    if (!isAuthenticated) {
      toast.error("Connect wallet first")
      return
    }
    if (!amount || !paymentMethodId) {
      toast.error("Enter amount and payment method")
      return
    }
    if (!escrowAddress || !usdcAddress) {
      toast.error("Blockchain config missing")
      return
    }

    setSubmitting(true)
    try {
      // 1. Backend builds payload (DB row + calldata)
      setStep("opening")
      const res = await api.openSellTrade({
        merchant_wallet: merchant.wallet_address,
        amount,
        currency: fiatCurrency,
        payment_method_id: Number(paymentMethodId),
        fiat_rate: fiatRate,
        is_cash_trade: isCash,
        meeting_location: meetingLocation,
        entry_path: "merchant_page",
      })
      const payload = res.data
      const signerForTx = phraseWallet ?? signer

      // 2. usdc.approve
      setStep("approving")
      const usdc = new ethers.Contract(usdcAddress, ERC20_ABI, signerForTx)
      const approveTx = await usdc.approve(payload.escrow_address, payload.approve_amount)
      await approveTx.wait()

      // Brief delay for RPC node state propagation. Without this, the next
      // gas estimation can race ahead of the approve and revert with
      // UNPREDICTABLE_GAS_LIMIT (allowance read still 0).
      await new Promise((r) => setTimeout(r, 1500))

      // 3. Broadcast openSellTrade calldata. Explicit gasLimit skips
      // estimateGas (which can also race the approve propagation).
      setStep("opening")
      const tx = await signerForTx.sendTransaction({
        to: payload.escrow_address,
        data: payload.calldata,
        gasLimit: 500000,
      })
      const receipt = await tx.wait()
      if (receipt.status !== 1) throw new Error("Funding tx reverted")

      // 4. Record on backend
      setStep("confirming")
      await api.confirmSellFund(payload.trade_hash, { fund_tx_hash: tx.hash })

      toast.success("Sell trade funded")
      router.visit(`/sell/trade/${payload.trade_hash}`)
    } catch (e) {
      toast.error(e?.message ?? "Failed to open sell trade")
    } finally {
      setSubmitting(false)
      setStep("idle")
    }
  }

  if (loading) {
    return (
      <PageShell>
        <main className="mx-auto w-full max-w-xl flex-1 px-4 py-8">
          <Card>
            <CardHeader>
              <div className="flex items-center gap-3">
                <Skeleton className="h-10 w-10 rounded-full" />
                <div className="space-y-2">
                  <Skeleton className="h-5 w-48" />
                  <Skeleton className="h-3 w-32" />
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <div className="flex flex-col items-center justify-center gap-3 py-12 text-muted-foreground">
                <SpinnerIcon size={32} className="animate-spin" />
                <p className="text-sm">Loading merchant…</p>
              </div>
            </CardContent>
          </Card>
        </main>
      </PageShell>
    )
  }

  if (!merchant) {
    return (
      <PageShell>
        <main className="mx-auto w-full max-w-xl flex-1 px-4 py-8">
          <Card>
            <CardContent className="py-16 text-center">
              <p className="text-lg font-semibold">Merchant not found</p>
              <p className="mt-2 text-sm text-muted-foreground">
                The merchant you're looking for doesn't exist or is inactive.
              </p>
            </CardContent>
          </Card>
        </main>
      </PageShell>
    )
  }

  return (
    <PageShell>
      <main className="mx-auto w-full max-w-xl flex-1 px-4 py-8">
        <Card>
          <CardHeader>
            <div className="flex items-center gap-3">
              <Avatar>
                <AvatarImage src={merchant.avatar_url} />
                <AvatarFallback>{merchant.username?.[0]?.toUpperCase() ?? "M"}</AvatarFallback>
              </Avatar>
              <div>
                <CardTitle>Sell USDC to {merchant.username}</CardTitle>
                <p className="text-sm text-muted-foreground">
                  Rate: {fiatRate} {fiatCurrency} per USDC
                </p>
              </div>
            </div>
          </CardHeader>

          <CardContent className="space-y-4">
            {!isAuthenticated && <ConnectWallet />}

            <div className="space-y-2">
              <label className="text-sm font-medium">Amount (USDC)</label>
              <Input
                type="number"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                placeholder="100"
              />
            </div>

            {features?.sell_cash_trade_enabled && (
              <div className="flex items-center justify-between rounded-md border p-3">
                <div>
                  <p className="text-sm font-medium">Cash meeting</p>
                  <p className="text-xs text-muted-foreground">In-person trade with NFT proof</p>
                </div>
                <Switch checked={isCash} onCheckedChange={setIsCash} />
              </div>
            )}

            {isCash && (
              <div className="space-y-2">
                <label className="text-sm font-medium">Meeting location</label>
                <Input
                  value={meetingLocation}
                  onChange={(e) => setMeetingLocation(e.target.value)}
                  placeholder="Cafe, address, landmark…"
                />
              </div>
            )}

            <div className="space-y-2">
              <label className="text-sm font-medium">
                Where you'll receive fiat
              </label>
              <Select value={paymentMethodId} onValueChange={setPaymentMethodId}>
                <SelectTrigger><SelectValue placeholder="Pick a method" /></SelectTrigger>
                <SelectContent>
                  {paymentMethods.map((m) => (
                    <SelectItem key={m.id} value={String(m.id)}>
                      {m.label || m.type}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="rounded-md bg-muted p-3 text-sm">
              <div className="flex justify-between"><span>Amount</span><span className="font-mono">{amount || 0} USDC</span></div>
              <div className="flex justify-between"><span>Platform fee (0.2%)</span><span className="font-mono">{(Number(amount || 0) * 0.002).toFixed(4)} USDC</span></div>
              {requireStake && (
                <div className="flex justify-between"><span>Anti-spam stake</span><span className="font-mono">{stakeUsdc} USDC (refundable)</span></div>
              )}
              <div className="mt-2 flex justify-between border-t pt-2 font-medium">
                <span>You will approve</span>
                <span className="font-mono">{(Number(approveAmount) / 1_000_000).toFixed(4)} USDC</span>
              </div>
              <div className="mt-1 flex justify-between font-medium text-emerald-500">
                <span>You receive (fiat)</span>
                <span className="font-mono">{(Number(amount || 0) * fiatRate).toFixed(2)} {fiatCurrency}</span>
              </div>
            </div>

            <Button
              size="lg"
              className="w-full"
              disabled={submitting || !isAuthenticated || !amount || !paymentMethodId}
              onClick={handleSubmit}
            >
              {submitting
                ? (step === "approving" ? "Approving USDC…" : step === "opening" ? "Opening trade…" : "Confirming…")
                : `Sell ${amount || ""} USDC`}
            </Button>

            <p className="text-xs text-muted-foreground">
              You will sign two wallet transactions: USDC approval + open trade. After the merchant
              accepts and sends fiat, you sign one more tx to release.
            </p>
          </CardContent>
        </Card>
      </main>
    </PageShell>
  )
}

function PageShell({ children }) {
  return (
    <div className="flex min-h-screen flex-col bg-background">
      <PublicHeader />
      {children}
      <PublicFooter />
    </div>
  )
}
