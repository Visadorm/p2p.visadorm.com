import { useEffect, useState } from "react"
import { router } from "@inertiajs/react"
import { toast } from "sonner"
import PublicHeader from "@/components/PublicHeader"
import PublicFooter from "@/components/PublicFooter"
import { Button } from "@/components/ui/button"
import ConnectWallet from "@/components/ConnectWallet"
import { useWallet } from "@/hooks/useWallet"
import { useReleaseSignature } from "@/hooks/useReleaseSignature"

export default function SellRelease({ tradeHash }) {
  const { isAuthenticated } = useWallet()
  const [acknowledged, setAcknowledged] = useState(false)
  const { payload, fetchPayload, signAndRelease, signing, submitting, released, error } =
    useReleaseSignature(tradeHash)

  useEffect(() => {
    if (isAuthenticated && !payload) {
      fetchPayload().catch(() => {})
    }
  }, [isAuthenticated, payload, fetchPayload])

  useEffect(() => {
    if (released) {
      toast.success("Release submitted on-chain")
      router.visit(`/sell/trade/${tradeHash}`)
    }
  }, [released, tradeHash])

  const sign = async () => {
    try { await signAndRelease() }
    catch (e) { toast.error(e?.message || "Signing failed") }
  }

  return (
    <div className="flex min-h-screen flex-col bg-[#0a0d14] text-[#e8edf7]">
      <PublicHeader />
      <main className="mx-auto w-full max-w-2xl flex-1 px-5 py-10">
        <h1 className="text-2xl font-semibold">Release Escrow</h1>
        <p className="mt-1 text-sm text-[#8b96b0]">
          You are about to authorise an irreversible on-chain release of USDC to the buyer.
        </p>

        {!isAuthenticated
          ? <div className="mt-6"><ConnectWallet /></div>
          : (
            <>
              <div className="mt-6 rounded-xl border border-[#fbbf24]/30 bg-[#fbbf24]/10 p-4 text-sm text-[#fbbf24]">
                <strong>Verify fiat receipt before signing.</strong> Once released, the trade is final.
                No dispute, no refund.
              </div>

              {payload && (
                <div className="mt-4 space-y-3 rounded-xl border border-[#1e2a42] bg-[#161c2d] p-5 text-sm">
                  <Row label="Trade" value={payload.meta.trade_hash} mono />
                  <Row label="USDC to release" value={`${Number(payload.meta.amount_usdc).toLocaleString()} USDC`} mono />
                  <Row label="Buyer wallet" value={payload.meta.buyer_wallet} mono />
                  <Row label="Signature deadline" value={payload.meta.deadline_iso} />
                  <Row label="Nonce" value={String(payload.message.nonce)} mono />
                </div>
              )}

              <label className="mt-4 flex items-start gap-2 text-sm text-[#8b96b0]">
                <input type="checkbox" checked={acknowledged} onChange={e => setAcknowledged(e.target.checked)} className="mt-1" />
                <span>I confirm I received the agreed fiat amount in full and understand this release is irreversible.</span>
              </label>

              <div className="mt-4 flex flex-wrap gap-2">
                <Button disabled={!payload || !acknowledged || signing || submitting} onClick={sign}>
                  {signing ? "Sign in wallet…" : submitting ? "Relaying…" : "Sign & release"}
                </Button>
                <Button variant="outline" disabled={signing || submitting} onClick={() => fetchPayload().catch(() => {})}>
                  Refresh nonce
                </Button>
              </div>

              {error && <div className="mt-3 text-sm text-[#f87171]">{error}</div>}
            </>
          )
        }
      </main>
      <PublicFooter />
    </div>
  )
}

function Row({ label, value, mono = false }) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-2">
      <span className="text-[#8b96b0]">{label}</span>
      <span className={`text-[#e8edf7] ${mono ? "font-mono text-xs" : ""}`}>{value}</span>
    </div>
  )
}
