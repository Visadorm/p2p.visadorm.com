import { useEffect, useState } from "react"
import { Link } from "@inertiajs/react"
import { toast } from "sonner"
import { ShieldCheck, EyeSlash, Copy, Check } from "@phosphor-icons/react"
import DashboardLayout from "@/layouts/DashboardLayout"
import { Button } from "@/components/ui/button"
import { useWallet } from "@/hooks/useWallet"
import { api } from "@/lib/api"

export default function SellDashboard() {
  const { isAuthenticated } = useWallet()
  const [offers, setOffers] = useState([])
  const [loading, setLoading] = useState(true)
  const [copiedSlug, setCopiedSlug] = useState(null)

  const copyLink = async (slug) => {
    const url = `${window.location.origin}/sell/o/${slug}`
    try {
      await navigator.clipboard.writeText(url)
      setCopiedSlug(slug)
      toast.success("Link copied")
      setTimeout(() => setCopiedSlug(prev => prev === slug ? null : prev), 2000)
    } catch {
      toast.error("Copy failed — copy manually: " + url)
    }
  }

  useEffect(() => {
    if (!isAuthenticated) { setLoading(false); return }
    api.getMySellOffers()
      .then(res => setOffers(res.data || []))
      .catch(() => setOffers([]))
      .finally(() => setLoading(false))
  }, [isAuthenticated])

  const cancelOffer = async (slug) => {
    if (!confirm("Cancel this offer?")) return
    try {
      await api.cancelSellOffer(slug)
      toast.success("Offer cancelled")
      setOffers(prev => prev.map(o => o.slug === slug ? { ...o, is_active: false } : o))
    } catch (err) {
      toast.error(err?.message || "Cancel failed")
    }
  }

  return (
    <DashboardLayout>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Your sell offers</h1>
        <Button asChild><Link href="/sell/create">New offer</Link></Button>
      </div>

      {loading
        ? <div className="rounded-xl border border-border bg-card p-8 text-center text-sm text-muted-foreground">Loading…</div>
        : offers.length === 0
          ? <div className="rounded-xl border border-border bg-card p-8 text-center text-sm text-muted-foreground">No offers yet.</div>
          : <div className="space-y-3">
              {offers.map(o => (
                <div key={o.slug} className="rounded-xl border border-border bg-card p-4">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0 flex-1">
                      <Link href={`/sell/o/${o.slug}`} className="block truncate font-mono text-sm text-primary hover:underline">
                        /sell/o/{o.slug}
                      </Link>
                      <div className="mt-1 font-mono text-lg font-semibold">
                        {Number(o.amount_remaining_usdc).toLocaleString()} / {Number(o.amount_usdc).toLocaleString()} USDC
                      </div>
                      <div className="text-xs text-muted-foreground">
                        {o.currency_code} @ {Number(o.fiat_rate).toFixed(2)}
                      </div>
                      <div className="mt-2 flex flex-wrap items-center gap-1.5">
                        <span className={`rounded-full px-2 py-0.5 text-xs ${o.is_active ? "bg-emerald-500/15 text-emerald-400" : "bg-muted text-muted-foreground"}`}>
                          {o.is_active ? "Active" : "Inactive"}
                        </span>
                        {o.is_private ? (
                          <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/15 px-2 py-0.5 text-xs text-amber-400">
                            <EyeSlash weight="duotone" className="size-3" /> Private
                          </span>
                        ) : (
                          <span className="rounded-full bg-primary/15 px-2 py-0.5 text-xs text-primary">Public</span>
                        )}
                        {o.require_kyc && (
                          <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs text-emerald-400">
                            <ShieldCheck weight="duotone" className="size-3" /> KYC required
                          </span>
                        )}
                      </div>
                      {Array.isArray(o.payment_methods) && o.payment_methods.length > 0 && (
                        <div className="mt-2 flex flex-wrap gap-1.5">
                          {o.payment_methods.map((pm, i) => (
                            <span key={i} className="rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                              {pm.label || pm.type}
                            </span>
                          ))}
                        </div>
                      )}
                    </div>
                    <div className="flex flex-col items-end gap-1.5">
                      {o.is_active && (
                        <Button variant="outline" size="sm" onClick={() => cancelOffer(o.slug)}>Cancel</Button>
                      )}
                      <Button variant="outline" size="sm" onClick={() => copyLink(o.slug)}>
                        {copiedSlug === o.slug
                          ? <><Check weight="bold" className="size-3" /> Copied</>
                          : <><Copy weight="duotone" className="size-3" /> Copy link</>}
                      </Button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
      }
    </DashboardLayout>
  )
}
