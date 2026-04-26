import { useEffect, useState } from "react"
import { Link, usePage } from "@inertiajs/react"
import { ShieldCheck } from "@phosphor-icons/react"
import PublicHeader from "@/components/PublicHeader"
import PublicFooter from "@/components/PublicFooter"
import { Button } from "@/components/ui/button"
import { api } from "@/lib/api"

export default function SellMarketplace() {
  const { features } = usePage().props
  const sellEnabled = !!features?.sell_enabled

  const [offers, setOffers] = useState([])
  const [loading, setLoading] = useState(true)
  const [currency, setCurrency] = useState("")
  const [payment, setPayment] = useState("")

  useEffect(() => {
    if (!sellEnabled) { setLoading(false); return }
    const params = {}
    if (currency) params.currency = currency
    if (payment) params.payment = payment
    setLoading(true)
    api.getSellOffers(params)
      .then(res => setOffers(res.data?.offers || []))
      .catch(() => setOffers([]))
      .finally(() => setLoading(false))
  }, [sellEnabled, currency, payment])

  return (
    <div className="flex min-h-screen flex-col bg-[#0a0d14] text-[#e8edf7]">
      <PublicHeader />

      <main className="mx-auto w-full max-w-5xl flex-1 px-5 py-10">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold">Sell USDC</h1>
            <p className="mt-1 text-sm text-[#8b96b0]">Browse open sell offers from verified wallets.</p>
          </div>
          {sellEnabled && (
            <Button asChild>
              <Link href="/sell/create">Create offer</Link>
            </Button>
          )}
        </div>

        {!sellEnabled && (
          <div className="rounded-lg border border-[#fbbf24]/30 bg-[#fbbf24]/10 p-4 text-sm text-[#fbbf24]">
            Sell flow is currently disabled.
          </div>
        )}

        {sellEnabled && (
          <>
            <div className="mb-4 flex flex-wrap gap-2">
              <select value={currency} onChange={e => setCurrency(e.target.value)}
                className="min-w-[140px] rounded-lg border border-[#1e2a42] bg-[#161c2d] px-3 py-2 text-sm">
                <option value="">All currencies</option>
                <option value="DOP">DOP</option>
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
                <option value="HTG">HTG</option>
                <option value="COP">COP</option>
              </select>
              <input value={payment} onChange={e => setPayment(e.target.value)}
                placeholder="Payment method (bank, wise, ...)"
                className="min-w-[200px] flex-1 rounded-lg border border-[#1e2a42] bg-[#161c2d] px-3 py-2 text-sm" />
            </div>

            {loading
              ? <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                  {[0,1,2,3].map(i => <div key={i} className="h-36 animate-pulse rounded-xl border border-[#1e2a42] bg-[#161c2d]" />)}
                </div>
              : offers.length === 0
                ? <div className="rounded-xl border border-[#1e2a42] bg-[#161c2d] p-8 text-center text-sm text-[#8b96b0]">No active sell offers.</div>
                : <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                    {offers.map(o => {
                      const sellerShort = o.seller_wallet ? `${o.seller_wallet.slice(0, 6)}...${o.seller_wallet.slice(-4)}` : "Seller"
                      return (
                        <Link key={o.slug} href={`/sell/o/${o.slug}`}
                          className="group block rounded-xl border border-[#1e2a42] bg-[#161c2d] p-4 transition-colors hover:border-[#4f6ef7]">
                          <div className="flex items-start justify-between gap-2">
                            <div>
                              <div className="text-xs text-[#8b96b0]">Seller <span className="font-mono text-[#a8b3c7]">{sellerShort}</span></div>
                              <div className="mt-0.5 font-mono text-xl font-semibold text-[#22c98a]">
                                {Number(o.amount_remaining_usdc).toLocaleString()} USDC
                              </div>
                            </div>
                            <div className="flex flex-col items-end gap-1.5">
                              <div className="rounded bg-[#4f6ef7]/10 px-2 py-0.5 font-mono text-xs text-[#4f6ef7]">
                                {o.currency_code}
                              </div>
                              {o.require_kyc && (
                                <span className="inline-flex items-center gap-1 rounded-full bg-[#22c98a]/15 px-2 py-0.5 text-xs text-[#22c98a]">
                                  <ShieldCheck weight="duotone" className="size-3" /> KYC required
                                </span>
                              )}
                            </div>
                          </div>
                          <div className="mt-2 text-sm text-[#e8edf7]">
                            1 USDC = {Number(o.fiat_rate).toFixed(2)} {o.currency_code}
                          </div>
                          <div className="mt-2 text-xs text-[#4a5568]">
                            Min ${Number(o.min_trade_usdc).toFixed(0)} — Max ${Number(o.max_trade_usdc).toFixed(0)}
                          </div>
                          <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                            <div className="flex flex-wrap gap-1.5">
                              {(o.payment_methods || []).slice(0, 3).map((pm, i) => (
                                <span key={i} className="rounded bg-white/5 px-2 py-0.5 text-xs text-[#8b96b0]">
                                  {pm.label || pm.type}
                                </span>
                              ))}
                            </div>
                            <span className="inline-flex items-center gap-1 rounded-lg bg-[#4f6ef7] px-4 py-2 text-sm font-semibold text-white transition-colors group-hover:bg-[#3d5ad8]">
                              Buy USDC →
                            </span>
                          </div>
                        </Link>
                      )
                    })}
                  </div>
            }
          </>
        )}
      </main>

      <PublicFooter />
    </div>
  )
}
