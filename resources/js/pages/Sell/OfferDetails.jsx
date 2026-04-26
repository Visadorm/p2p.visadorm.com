import { useEffect, useState } from "react"
import { Link, router, usePage } from "@inertiajs/react"
import { toast } from "sonner"
import { ethers } from "ethers"
import { ShieldCheck, Warning, IdentificationCard } from "@phosphor-icons/react"
import PublicHeader from "@/components/PublicHeader"
import PublicFooter from "@/components/PublicFooter"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import ConnectWallet from "@/components/ConnectWallet"
import { useWallet } from "@/hooks/useWallet"
import { api } from "@/lib/api"
import { ERC20_ABI, ESCROW_SELL_ABI, STAKE_AMOUNT, useBlockchainConfig } from "@/lib/contracts"

export default function OfferDetails({ slug }) {
  const { isAuthenticated, signer, phraseWallet } = useWallet()
  const { usdcAddress, escrowAddress, rpcUrl } = useBlockchainConfig()
  const { features } = usePage().props

  const [offer, setOffer] = useState(null)
  const [loading, setLoading] = useState(true)
  const [chosenMethodId, setChosenMethodId] = useState(null)
  const [taking, setTaking] = useState(false)
  const [step, setStep] = useState("idle")
  const [me, setMe] = useState(null)

  useEffect(() => {
    api.getSellOffer(slug)
      .then(res => {
        setOffer(res.data)
        const first = res.data?.payment_methods?.[0]
        if (first?.merchant_payment_method_id) {
          setChosenMethodId(first.merchant_payment_method_id)
        }
      })
      .catch(() => setOffer(null))
      .finally(() => setLoading(false))
  }, [slug])

  useEffect(() => {
    if (!isAuthenticated) { setMe(null); return }
    api.me()
      .then(res => setMe(res.data))
      .catch(() => setMe(null))
  }, [isAuthenticated])

  const buyerKycApproved = me?.kyc_status === "approved"
  const isOwnOffer = me && offer && me.wallet_address?.toLowerCase() === offer.seller_wallet?.toLowerCase()
  const kycBlocked = offer?.require_kyc && !buyerKycApproved && !isOwnOffer

  const fiatPreview = offer ? (Number(offer.amount_usdc) * Number(offer.fiat_rate)).toFixed(2) : ""

  const take = async () => {
    if (!chosenMethodId) { toast.error("Select a payment method"); return }
    if (!offer?.trade_id) { toast.error("Offer missing on-chain trade id — cannot take"); return }
    if (!escrowAddress || !usdcAddress) { toast.error("Blockchain config missing"); return }
    if (!signer && !phraseWallet) { toast.error("Wallet not ready"); return }

    setTaking(true)
    try {
      const signerForTx = phraseWallet
        ? phraseWallet.connect(new ethers.providers.JsonRpcProvider(rpcUrl || "https://sepolia.base.org"))
        : signer
      const escrow = new ethers.Contract(escrowAddress, ESCROW_SELL_ABI, signerForTx)

      // Public offers require buyer to lock $5 USDC anti-spam stake
      if (!offer.is_private) {
        const usdc = new ethers.Contract(usdcAddress, ERC20_ABI, signerForTx)
        const buyerAddress = await signerForTx.getAddress()
        const balance = await usdc.balanceOf(buyerAddress)
        if (balance.lt(STAKE_AMOUNT)) {
          throw new Error("Need at least $5 USDC for the anti-spam stake.")
        }
        const allowance = await usdc.allowance(buyerAddress, escrowAddress)
        if (allowance.lt(STAKE_AMOUNT)) {
          setStep("approving")
          toast.info("Approving $5 USDC stake…")
          const approveTx = await usdc.approve(escrowAddress, STAKE_AMOUNT)
          await approveTx.wait()
        }
      }

      setStep("taking")
      toast.info("Joining trade on-chain…")
      const takeTx = await escrow.takeSellTrade(offer.trade_id)
      const receipt = await takeTx.wait()

      setStep("saving")
      const res = await api.takeSellOffer(slug, {
        merchant_payment_method_id: chosenMethodId,
        take_tx_hash: receipt.transactionHash,
      })
      toast.success("Trade started — USDC locked")
      router.visit(`/sell/trade/${res.data.trade_hash}`)
    } catch (err) {
      const msg = err?.reason || err?.data?.message || err?.message || "Failed to take offer"
      toast.error(msg)
    } finally {
      setTaking(false)
      setStep("idle")
    }
  }

  const takeLabel = step === "approving" ? "Approving stake…"
    : step === "taking" ? "Joining trade…"
    : step === "saving" ? "Saving…"
    : taking ? "Starting…"
    : `Take this offer (${offer ? Number(offer.amount_usdc).toLocaleString() : "—"} USDC)`

  if (loading) return <Shell><div className="p-8 text-center text-[#8b96b0]">Loading…</div></Shell>
  if (!offer) return <Shell><div className="p-8 text-center text-[#8b96b0]">Offer not found.</div></Shell>

  return (
    <Shell>
      <h1 className="text-2xl font-semibold">Sell Offer</h1>
      <p className="mt-1 text-sm text-[#8b96b0]">Seller: <span className="font-mono">{offer.seller_wallet}</span></p>

      <div className="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
        <Stat label="Available" value={`${Number(offer.amount_remaining_usdc).toLocaleString()} USDC`} accent="text-[#22c98a]" />
        <Stat label="Rate" value={`1 USDC = ${Number(offer.fiat_rate).toFixed(2)} ${offer.currency_code}`} />
        <Stat label="Min" value={`${Number(offer.min_trade_usdc).toFixed(0)} USDC`} />
        <Stat label="Max" value={`${Number(offer.max_trade_usdc).toFixed(0)} USDC`} />
      </div>

      {(offer.require_kyc || offer.is_private) && (
        <div className="mt-5 rounded-xl border border-[#1e2a42] bg-[#161c2d] p-5">
          <div className="text-sm font-semibold text-[#e8edf7]">Seller requirements</div>
          <div className="mt-3 space-y-2 text-sm">
            {offer.require_kyc && (
              <div className="flex items-start gap-2">
                <ShieldCheck weight="duotone" className={`mt-0.5 size-5 shrink-0 ${buyerKycApproved || isOwnOffer ? "text-[#22c98a]" : "text-[#fbbf24]"}`} />
                <div>
                  <span className="text-[#e8edf7]">KYC verification required</span>
                  <span className="block text-xs text-[#8b96b0]">
                    {isOwnOffer
                      ? "This is your offer."
                      : isAuthenticated
                        ? buyerKycApproved
                          ? "Your KYC is approved."
                          : me?.kyc_status === "pending"
                            ? "Your KYC is pending review."
                            : me?.kyc_status === "rejected"
                              ? "Your KYC was rejected — re-submit before taking this offer."
                              : "You haven't submitted KYC yet."
                        : "Connect your wallet to check your status."}
                  </span>
                </div>
              </div>
            )}
            {offer.is_private && (
              <div className="flex items-start gap-2">
                <Warning weight="duotone" className="mt-0.5 size-5 shrink-0 text-[#8b96b0]" />
                <div>
                  <span className="text-[#e8edf7]">Private offer (link-only)</span>
                  <span className="block text-xs text-[#8b96b0]">No anti-spam stake required from buyer.</span>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      <div className="mt-5 rounded-xl border border-[#1e2a42] bg-[#161c2d] p-5">
        <div className="text-sm font-semibold text-[#e8edf7]">Accepted payment methods</div>
        <div className="mt-2 flex flex-wrap gap-2">
          {(offer.payment_methods || []).map((pm, i) => {
            const id = pm.merchant_payment_method_id
            const active = chosenMethodId === id
            return (
              <button key={i} type="button"
                onClick={() => id && setChosenMethodId(id)}
                className={`rounded-lg border px-3 py-1.5 text-xs ${
                  active
                    ? "border-[#4f6ef7] bg-[#4f6ef7]/10 text-[#4f6ef7]"
                    : "border-[#1e2a42] text-[#8b96b0] hover:border-[#263350]"
                }`}>
                {pm.label || pm.type}
              </button>
            )
          })}
        </div>
        {offer.instructions && (
          <p className="mt-3 whitespace-pre-line text-sm text-[#8b96b0]">{offer.instructions}</p>
        )}
      </div>

      {!features?.sell_enabled ? (
        <div className="mt-6 rounded-lg border border-[#fbbf24]/30 bg-[#fbbf24]/10 p-4 text-sm text-[#fbbf24]">Sell flow disabled.</div>
      ) : !isAuthenticated ? (
        <div className="mt-6"><ConnectWallet /></div>
      ) : isOwnOffer ? (
        <div className="mt-6 rounded-lg border border-[#4f6ef7]/30 bg-[#4f6ef7]/10 p-4 text-sm text-[#4f6ef7]">
          This is your own offer. Manage it from your <Link href="/sell/dashboard" className="underline">sell dashboard</Link>.
        </div>
      ) : kycBlocked ? (
        <div className="mt-6 rounded-xl border border-[#fbbf24]/30 bg-[#fbbf24]/10 p-5">
          <div className="flex items-start gap-3">
            <IdentificationCard weight="duotone" className="mt-0.5 size-6 shrink-0 text-[#fbbf24]" />
            <div className="flex-1">
              <div className="text-sm font-semibold text-[#fbbf24]">KYC verification required to take this offer</div>
              <p className="mt-1 text-xs text-[#fbbf24]/80">
                {me?.kyc_status === "pending"
                  ? "Your KYC submission is awaiting admin review. You'll be notified once approved."
                  : me?.kyc_status === "rejected"
                    ? "Your previous KYC was rejected. Submit again with the requested corrections."
                    : "Submit your identity documents — most reviews complete within 24h."}
              </p>
              {me?.kyc_status !== "pending" && (
                <Button asChild className="mt-3" size="sm">
                  <Link href="/kyc">{me?.kyc_status === "rejected" ? "Re-submit KYC" : "Submit KYC"}</Link>
                </Button>
              )}
            </div>
          </div>
        </div>
      ) : (
        <div className="mt-6 rounded-xl border border-[#1e2a42] bg-[#161c2d] p-5">
          {(() => {
            const feePct = Number(features?.p2p_fee_percent) || 0.2
            const gross = Number(offer.amount_usdc)
            const fee = +(gross * feePct / 100).toFixed(6)
            const net = +(gross - fee).toFixed(6)
            return (
              <>
                <div className="text-sm">
                  You will receive <span className="font-mono font-semibold text-[#22c98a]">{net.toLocaleString()} USDC</span>
                  {" "}for paying <span className="font-mono font-semibold text-[#e8edf7]">{fiatPreview} {offer.currency_code}</span> off-chain.
                </div>
                <div className="mt-2 rounded-lg border border-[#1e2a42] bg-[#0a0d14] p-3 text-xs">
                  <div className="flex justify-between text-[#8b96b0]"><span>Offer amount</span><span className="font-mono">{gross.toLocaleString()} USDC</span></div>
                  <div className="flex justify-between text-[#8b96b0]"><span>Network fee ({feePct}%)</span><span className="font-mono">−{fee.toLocaleString()} USDC</span></div>
                  <div className="mt-1 flex justify-between border-t border-[#1e2a42] pt-1 font-semibold text-[#e8edf7]"><span>You receive</span><span className="font-mono text-[#22c98a]">{net.toLocaleString()} USDC</span></div>
                </div>
              </>
            )
          })()}
          {!offer.is_private && (
            <p className="mt-2 text-xs text-[#8b96b0]">
              Public offer — taking requires locking a $5 USDC anti-spam stake (refunded on completion).
            </p>
          )}
          <Button className="mt-4 w-full" disabled={taking} onClick={take}>
            {takeLabel}
          </Button>
        </div>
      )}
    </Shell>
  )
}

function Shell({ children }) {
  return (
    <div className="flex min-h-screen flex-col bg-[#0a0d14] text-[#e8edf7]">
      <PublicHeader />
      <main className="mx-auto w-full max-w-3xl flex-1 px-5 py-10">{children}</main>
      <PublicFooter />
    </div>
  )
}

function Stat({ label, value, accent = "text-[#e8edf7]" }) {
  return (
    <div className="rounded-xl border border-[#1e2a42] bg-[#161c2d] p-4">
      <div className="text-xs text-[#8b96b0]">{label}</div>
      <div className={`mt-1 font-mono text-lg font-semibold ${accent}`}>{value}</div>
    </div>
  )
}
