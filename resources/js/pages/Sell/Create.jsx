import { useEffect, useState } from "react"
import { Link, router, usePage } from "@inertiajs/react"
import { toast } from "sonner"
import { ethers } from "ethers"
import DashboardLayout from "@/layouts/DashboardLayout"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Label } from "@/components/ui/label"
import { useWallet } from "@/hooks/useWallet"
import { api } from "@/lib/api"
import { ERC20_ABI, ESCROW_SELL_ABI, useBlockchainConfig, generateTradeId, toRawUsdc } from "@/lib/contracts"

const currencyNameFormatter = new Intl.DisplayNames(["en"], { type: "currency" })
const getCurrencyName = (code) => { try { return currencyNameFormatter.of(code) } catch { return code } }

export default function CreateSellOffer() {
  const { isAuthenticated, signer, phraseWallet } = useWallet()
  const { usdcAddress, escrowAddress, rpcUrl } = useBlockchainConfig()
  const { features } = usePage().props

  const [amount, setAmount] = useState("100")
  const [currency, setCurrency] = useState("DOP")
  const [rate, setRate] = useState("")
  const [rateEditedManually, setRateEditedManually] = useState(false)
  const [marketRates, setMarketRates] = useState({})
  const [availableMethods, setAvailableMethods] = useState([])
  const [methodsLoading, setMethodsLoading] = useState(true)
  const [selectedMethodIds, setSelectedMethodIds] = useState([])
  const [instructions, setInstructions] = useState("")
  const [requireKyc, setRequireKyc] = useState(false)
  const [isPrivate, setIsPrivate] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [step, setStep] = useState("idle") // idle | approving | funding | saving

  useEffect(() => {
    api.getExchangeRates()
      .then(res => {
        const rates = res.data || {}
        setMarketRates(rates)
        if (!rates[currency] && Object.keys(rates).length > 0) {
          setCurrency(Object.keys(rates).sort()[0])
        }
      })
      .catch(() => setMarketRates({}))
  }, [])

  useEffect(() => {
    if (rateEditedManually) return
    const fetched = marketRates[currency]
    if (fetched != null) setRate(String(fetched))
  }, [currency, marketRates, rateEditedManually])

  useEffect(() => {
    if (!isAuthenticated) { setMethodsLoading(false); return }
    api.getPaymentMethods()
      .then(res => {
        const all = res.data || []
        const active = all.filter(m => m.is_active !== false && m.type !== "cash_meeting")
        setAvailableMethods(active)
      })
      .catch(() => setAvailableMethods([]))
      .finally(() => setMethodsLoading(false))
  }, [isAuthenticated])

  const toggleMethod = (id) => setSelectedMethodIds(prev =>
    prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]
  )

  const submit = async () => {
    if (selectedMethodIds.length === 0) { toast.error("Pick at least one payment method"); return }
    if (!escrowAddress || !usdcAddress) { toast.error("Blockchain config missing"); return }
    if (!signer && !phraseWallet) { toast.error("Wallet not ready"); return }

    setSubmitting(true)
    try {
      const signerForTx = phraseWallet
        ? phraseWallet.connect(new ethers.providers.JsonRpcProvider(rpcUrl || "https://sepolia.base.org"))
        : signer

      const usdc = new ethers.Contract(usdcAddress, ERC20_ABI, signerForTx)
      const escrow = new ethers.Contract(escrowAddress, ESCROW_SELL_ABI, signerForTx)
      const sellerAddress = await signerForTx.getAddress()
      const amountRaw = toRawUsdc(amount).toString()

      const balance = await usdc.balanceOf(sellerAddress)
      if (balance.lt(amountRaw)) {
        throw new Error(`Insufficient USDC. Have ${ethers.utils.formatUnits(balance, 6)}, need ${amount}.`)
      }

      const allowance = await usdc.allowance(sellerAddress, escrowAddress)
      if (allowance.lt(amountRaw)) {
        setStep("approving")
        toast.info("Approving USDC for escrow…")
        const approveTx = await usdc.approve(escrowAddress, amountRaw)
        await approveTx.wait()
      }

      const tradeId = generateTradeId()
      const lockHours = Number(features?.fund_lock_hours) || 168
      const expiresAtUnix = Math.floor(Date.now() / 1000) + lockHours * 3600

      setStep("funding")
      toast.info("Locking USDC into escrow on-chain…")
      const fundTx = await escrow.fundSellTrade(tradeId, amountRaw, isPrivate, expiresAtUnix)
      const receipt = await fundTx.wait()
      const fundTxHash = receipt.transactionHash

      setStep("saving")
      const res = await api.createSellOffer({
        amount_usdc: Number(amount),
        currency_code: currency,
        fiat_rate: Number(rate),
        payment_methods: selectedMethodIds.map(id => ({ merchant_payment_method_id: id })),
        instructions: instructions || null,
        require_kyc: requireKyc,
        is_private: isPrivate,
        trade_id: tradeId,
        fund_tx_hash: fundTxHash,
      })
      toast.success("Offer live + USDC locked in escrow")
      router.visit(`/sell/o/${res.data.slug}`)
    } catch (err) {
      const msg = err?.reason || err?.data?.message || err?.message || "Failed to create offer"
      toast.error(msg)
    } finally {
      setSubmitting(false)
      setStep("idle")
    }
  }

  const submitLabel = step === "approving" ? "Approving USDC…"
    : step === "funding" ? "Locking USDC…"
    : step === "saving" ? "Saving offer…"
    : submitting ? "Creating offer…"
    : "Create offer & lock USDC"

  if (!features?.sell_enabled) {
    return (
      <DashboardLayout>
        <div className="mx-auto max-w-2xl rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-400">Sell flow disabled.</div>
      </DashboardLayout>
    )
  }

  return (
    <DashboardLayout>
      <div className="mx-auto max-w-2xl">
        <div className="mb-6 flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-semibold">Create Sell Offer</h1>
            <p className="mt-1 text-sm text-muted-foreground">Lock USDC into escrow and list it for buyers.</p>
          </div>
          <Button variant="outline" asChild><Link href="/sell/dashboard">Back to offers</Link></Button>
        </div>

        <div className="space-y-5 rounded-xl border border-border bg-card p-5">
          <Field label="USDC to sell">
            <Input type="number" min="10" value={amount} onChange={e => setAmount(e.target.value)} />
            <p className="mt-1 text-xs text-muted-foreground">One buyer takes the full amount. To sell more flexibly, create separate smaller offers.</p>
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Fiat currency">
              <select value={currency} onChange={e => { setCurrency(e.target.value); setRateEditedManually(false) }}
                className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm">
                {Object.keys(marketRates).length === 0
                  ? <option value="DOP">Loading currencies...</option>
                  : Object.keys(marketRates).sort().map(code => (
                      <option key={code} value={code}>{code} — {getCurrencyName(code)}</option>
                    ))}
              </select>
            </Field>
            <Field label={`Rate (1 USDC = ? ${currency})`}>
              <Input
                type="number"
                step="0.0001"
                value={rate}
                onChange={e => { setRate(e.target.value); setRateEditedManually(true) }}
                placeholder={marketRates[currency] ? `Market: ${marketRates[currency]}` : "Loading..."}
              />
              {marketRates[currency] && rateEditedManually && (
                <button type="button" onClick={() => { setRate(String(marketRates[currency])); setRateEditedManually(false) }}
                  className="mt-1 text-xs text-primary hover:underline">
                  Reset to market ({marketRates[currency]})
                </button>
              )}
            </Field>
          </div>

          <div>
            <div className="text-sm font-semibold">Payment methods buyers can use</div>
            <div className="mt-0.5 text-xs text-muted-foreground">
              Select from your saved payment methods. Buyers see the full account details when they take this offer.{" "}
              <Link href="/payments" className="text-primary hover:underline">Manage methods</Link>
            </div>

            <div className="mt-3">
              {methodsLoading ? (
                <div className="rounded-lg border border-border bg-background p-4 text-center text-sm text-muted-foreground">Loading payment methods…</div>
              ) : availableMethods.length === 0 ? (
                <div className="rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-400">
                  You haven't added any payment methods yet. Buyers need to know how to send you fiat.
                  <div className="mt-3"><Button asChild size="sm"><Link href="/payments">Add a payment method</Link></Button></div>
                </div>
              ) : (
                <div className="space-y-2">
                  {availableMethods.map(pm => {
                    const checked = selectedMethodIds.includes(pm.id)
                    return (
                      <label key={pm.id} className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors ${checked ? "border-primary bg-primary/5" : "border-border bg-background hover:border-muted-foreground/30"}`}>
                        <input type="checkbox" className="mt-0.5" checked={checked} onChange={() => toggleMethod(pm.id)} />
                        <div className="flex-1">
                          <div className="text-sm font-medium">{pm.label}</div>
                          <div className="mt-0.5 text-xs text-muted-foreground">{pm.provider}{pm.location ? ` · ${pm.location}` : ""}</div>
                        </div>
                      </label>
                    )
                  })}
                </div>
              )}
            </div>
          </div>

          <Field label="Instructions for buyer (optional)">
            <Textarea rows={4} value={instructions} onChange={e => setInstructions(e.target.value)} />
          </Field>

          <div className="space-y-3 text-sm">
            <label className="flex items-start gap-2">
              <input type="checkbox" className="mt-0.5" checked={requireKyc} onChange={e => setRequireKyc(e.target.checked)} />
              <span>
                Require KYC from buyers
                <span className="block text-xs text-muted-foreground">Only KYC-verified buyers can take this offer.</span>
              </span>
            </label>
            <label className="flex items-start gap-2">
              <input type="checkbox" className="mt-0.5" checked={isPrivate} onChange={e => setIsPrivate(e.target.checked)} />
              <span>
                Private offer (link-only)
                <span className="block text-xs text-muted-foreground">Hidden from the public marketplace. Only accessible via the direct offer link you share. Buyers skip the anti-spam stake.</span>
              </span>
            </label>
          </div>

          <Button className="w-full" disabled={submitting || availableMethods.length === 0} onClick={submit}>
            {submitLabel}
          </Button>
        </div>
      </div>
    </DashboardLayout>
  )
}

function Field({ label, children }) {
  return <div><Label className="text-sm">{label}</Label><div className="mt-1">{children}</div></div>
}
