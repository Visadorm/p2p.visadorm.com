import { useEffect, useState, useRef, useCallback } from "react"
import { Link, usePage } from "@inertiajs/react"
import ConnectWallet from "@/components/ConnectWallet"
import PublicHeader from "@/components/PublicHeader"
import PublicFooter from "@/components/PublicFooter"

// ─── Helpers ─────────────────────────────────────────────────────────────────

function getInitials(name = "") {
  return name.slice(0, 2).toUpperCase()
}

function formatUsdc(n) {
  if (!n || n === 0) return "—"
  if (n >= 1000) return `$${(n / 1000).toFixed(1)}k`
  return `$${Number(n).toFixed(0)}`
}

function avatarGradient(name = "") {
  const gradients = [
    "linear-gradient(135deg,#4f6ef7,#7c3aed)",
    "linear-gradient(135deg,#059669,#0284c7)",
    "linear-gradient(135deg,#d97706,#dc2626)",
    "linear-gradient(135deg,#7c3aed,#db2777)",
    "linear-gradient(135deg,#0284c7,#059669)",
    "linear-gradient(135deg,#0f766e,#0284c7)",
  ]
  let hash = 0
  for (let i = 0; i < name.length; i++) hash = (hash * 31 + name.charCodeAt(i)) >>> 0
  return gradients[hash % gradients.length]
}

const rankStyle = {
  Elite:  "bg-[#4dabf7]/10 text-[#4dabf7] border-[#4dabf7]/25",
  Hero:   "bg-[#fbbf24]/10 text-[#fbbf24] border-[#fbbf24]/25",
  Senior: "bg-[#8b96b0]/10 text-[#8b96b0] border-[#8b96b0]/20",
  Junior: "bg-[#fb923c]/10 text-[#fb923c] border-[#fb923c]/25",
}

async function fetchMerchants({ top10 = false, currency = "", payment = "", page = 1 } = {}) {
  const params = new URLSearchParams()
  if (top10)    params.set("top10", "1")
  if (currency) params.set("currency", currency)
  if (payment)  params.set("payment", payment)
  if (!top10)   params.set("page", page)
  params.set("per_page", "20")

  const res = await fetch(`/api/merchants?${params}`, {
    headers: { Accept: "application/json" },
  })
  if (!res.ok) throw new Error("Failed to load merchants")
  return res.json()
}

// ─── Viewport hook ────────────────────────────────────────────────────────────

function useVisibleCount() {
  const compute = () => {
    if (typeof window === "undefined") return 3
    if (window.innerWidth >= 1024) return 3
    if (window.innerWidth >= 640) return 2
    return 1
  }
  const [n, setN] = useState(compute)
  useEffect(() => {
    const onResize = () => setN(compute())
    window.addEventListener("resize", onResize)
    return () => window.removeEventListener("resize", onResize)
  }, [])
  return n
}

// ─── Stats ─────────────────────────────────────────────────────────────────

function StatsGrid() {
  const { platform_stats } = usePage().props
  const s = platform_stats || {}
  const stats = [
    { val: (s.total_trades || 0).toLocaleString(), lbl: "Total Trades" },
    { val: `$${(s.total_volume || 0).toLocaleString()}`, lbl: "Volume", green: true },
    { val: (s.total_merchants || 0).toLocaleString(), lbl: "Merchants" },
    { val: `${s.avg_completion || 0}%`, lbl: "Avg Completion" },
  ]
  return (
    <div className="mx-auto grid max-w-5xl grid-cols-2 gap-3 px-5 pb-6 sm:grid-cols-4">
      {stats.map(s => (
        <div key={s.lbl} className="rounded-xl border border-[#1e2a42] bg-[#161c2d] p-4 text-center">
          <div className={`font-mono text-2xl font-semibold ${s.green ? "text-[#22c98a]" : "text-[#e8edf7]"}`}>
            {s.val}
          </div>
          <div className="mt-1 text-sm text-[#8b96b0]">{s.lbl}</div>
        </div>
      ))}
    </div>
  )
}

// ─── Top 10 Carousel ─────────────────────────────────────────────────────────

const GAP_PX = 12

function TopMerchantsCarousel() {
  const [merchants, setMerchants] = useState([])
  const [loading, setLoading]     = useState(true)
  const [current, setCurrent]     = useState(0)
  const [paused, setPaused]       = useState(false)
  const timerRef = useRef(null)
  const visible  = useVisibleCount()

  useEffect(() => {
    fetchMerchants({ top10: true })
      .then(d => setMerchants((d.data || []).slice(0, 10)))
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [])

  const slideCount = Math.max(1, merchants.length - visible + 1)

  useEffect(() => {
    setCurrent(c => Math.min(c, slideCount - 1))
  }, [slideCount])

  useEffect(() => {
    if (paused || merchants.length <= visible) return
    timerRef.current = setInterval(() => {
      setCurrent(c => (c + 1) % slideCount)
    }, 3500)
    return () => clearInterval(timerRef.current)
  }, [merchants.length, slideCount, visible, paused])

  const go = useCallback((dir) => {
    clearInterval(timerRef.current)
    setCurrent(c => (c + dir + slideCount) % slideCount)
  }, [slideCount])

  if (loading) {
    return (
      <div className="flex h-48 items-center justify-center gap-2 text-[#8b96b0]">
        <span className="animate-spin text-lg">⟳</span>
        <span className="text-sm">Loading top merchants…</span>
      </div>
    )
  }

  if (!merchants.length) return null

  // Each slot width = (100% - (visible-1)*gap) / visible. Step = slotWidth + gap.
  const stepCalc = `calc((100% - ${(visible - 1) * GAP_PX}px) / ${visible} + ${GAP_PX}px)`
  const slotWidth = `calc((100% - ${(visible - 1) * GAP_PX}px) / ${visible})`

  return (
    <div>
      {/* Header */}
      <div className="mb-4 flex items-center justify-between">
        <div className="flex items-center gap-2 text-lg font-semibold text-[#e8edf7]">
          <span className="text-xl">🏆</span>
          <span>Top 10 merchants</span>
          <span className="rounded-full border border-[#22c98a]/30 bg-[#22c98a]/15 px-2 py-0.5 font-mono text-xs text-[#22c98a]">
            LIVE
          </span>
        </div>
        {merchants.length > visible && (
          <div className="flex items-center gap-2">
            <button
              onClick={() => go(-1)}
              className="flex size-9 items-center justify-center rounded-full border border-[#263350] bg-[#161c2d] text-[#8b96b0] text-lg leading-none hover:bg-[#1a2135] hover:text-[#e8edf7] transition-colors"
              aria-label="Previous"
            >
              ‹
            </button>
            <span className="min-w-12 text-center font-mono text-sm text-[#4a5568]">
              {current + 1} / {slideCount}
            </span>
            <button
              onClick={() => go(1)}
              className="flex size-9 items-center justify-center rounded-full border border-[#263350] bg-[#161c2d] text-[#8b96b0] text-lg leading-none hover:bg-[#1a2135] hover:text-[#e8edf7] transition-colors"
              aria-label="Next"
            >
              ›
            </button>
          </div>
        )}
      </div>

      {/* Track */}
      <div
        className="overflow-hidden"
        onMouseEnter={() => setPaused(true)}
        onMouseLeave={() => setPaused(false)}
      >
        <div
          className="flex transition-transform duration-500 ease-in-out"
          style={{
            gap: `${GAP_PX}px`,
            transform: `translateX(calc(-${current} * ${stepCalc}))`,
          }}
        >
          {merchants.map((m, i) => (
            <Link
              key={m.username}
              href={`/merchant/${m.username}`}
              style={{ minWidth: slotWidth, width: slotWidth }}
              className="flex-shrink-0"
            >
              <div
                className={`relative h-full rounded-xl border bg-[#161c2d] p-4 transition-colors ${
                  i >= current && i < current + visible
                    ? "border-[#4f6ef7] shadow-[0_0_0_1px_rgba(79,110,247,0.15)]"
                    : "border-[#1e2a42] hover:border-[#263350]"
                }`}
              >
                {/* Head */}
                <div className="relative mb-3 flex items-center gap-3">
                  <div className="relative">
                    <div
                      className="flex size-11 items-center justify-center overflow-hidden rounded-full text-sm font-semibold text-white"
                      style={{ background: avatarGradient(m.username) }}
                    >
                      {m.avatar
                        ? <img src={m.avatar} alt={m.username} className="size-full object-cover" />
                        : getInitials(m.username)
                      }
                    </div>
                    {m.is_online && (
                      <span className="absolute -bottom-0.5 -right-0.5 size-3 rounded-full border-2 border-[#161c2d] bg-[#22c98a]" />
                    )}
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="truncate text-sm font-semibold text-[#e8edf7]">{m.username}</div>
                    {m.rank && (
                      <span className={`mt-0.5 inline-block rounded-full border px-2 py-0.5 text-xs ${rankStyle[m.rank] || "bg-[#8b96b0]/10 text-[#8b96b0] border-[#8b96b0]/20"}`}>
                        {m.rank}
                      </span>
                    )}
                  </div>
                  <div className="absolute -top-1 right-0 select-none font-mono text-3xl font-semibold text-white/[0.05]">
                    #{i + 1}
                  </div>
                </div>

                <div className="my-3 h-px bg-[#1e2a42]" />

                {/* Stats */}
                <div className="grid grid-cols-3 gap-2 text-center">
                  <div>
                    <div className="font-mono text-sm font-semibold text-[#e8edf7]">
                      {(m.total_trades || 0).toLocaleString()}
                    </div>
                    <div className="mt-0.5 text-xs text-[#4a5568]">Trades</div>
                  </div>
                  <div>
                    <div className="font-mono text-sm font-semibold text-[#e8edf7]">{m.completion_rate || 0}%</div>
                    <div className="mt-0.5 text-xs text-[#4a5568]">Complete</div>
                  </div>
                  <div>
                    <div className="font-mono text-sm font-semibold text-[#22c98a]">{formatUsdc(m.available_usdc)}</div>
                    <div className="mt-0.5 text-xs text-[#4a5568]">Available</div>
                  </div>
                </div>

                {/* Currencies */}
                {m.currencies?.length > 0 && (
                  <div className="mt-3 flex flex-wrap gap-1.5">
                    {m.currencies.slice(0, 3).map(c => (
                      <span key={c.currency_code} className="rounded bg-white/5 px-2 py-0.5 font-mono text-xs text-[#8b96b0]">
                        {c.currency_code}
                      </span>
                    ))}
                  </div>
                )}

                {/* Tags */}
                <div className="mt-2 flex flex-wrap gap-1.5">
                  {m.is_fast_responder && (
                    <span className="inline-flex items-center gap-1 rounded-full bg-[#fbbf24]/10 px-2 py-0.5 text-xs text-[#fbbf24]">
                      ⚡ Fast
                    </span>
                  )}
                  {m.has_liquidity && (
                    <span className="inline-flex items-center gap-1 rounded-full bg-[#22c98a]/10 px-2 py-0.5 text-xs text-[#22c98a]">
                      $ Liquidity
                    </span>
                  )}
                  {m.kyc_verified && (
                    <span className="inline-flex items-center gap-1 rounded-full bg-[#4dabf7]/10 px-2 py-0.5 text-xs text-[#4dabf7]">
                      ✓ Verified
                    </span>
                  )}
                </div>
              </div>
            </Link>
          ))}
        </div>
      </div>

      {/* Dots */}
      {merchants.length > visible && (
        <div className="mt-4 flex justify-center gap-1.5">
          {Array.from({ length: slideCount }).map((_, i) => (
            <button
              key={i}
              onClick={() => { clearInterval(timerRef.current); setCurrent(i) }}
              className={`h-1.5 rounded-[3px] transition-all duration-300 ${
                i === current ? "w-6 bg-[#4f6ef7]" : "w-1.5 bg-[#263350]"
              }`}
              aria-label={`Go to slide ${i + 1}`}
            />
          ))}
        </div>
      )}
    </div>
  )
}

// ─── Marketplace ──────────────────────────────────────────────────────────────

function MerchantCard({ m }) {
  return (
    <Link href={m.primary_slug ? `/trade/${m.primary_slug}/start` : `/merchant/${m.username}`}>
      <div className="cursor-pointer rounded-xl border border-[#1e2a42] bg-[#161c2d] p-4 transition-colors hover:border-[#263350]">
        <div className="flex items-start gap-3">
          {/* Avatar */}
          <div className="relative shrink-0">
            <div
              className="flex size-12 items-center justify-center overflow-hidden rounded-full text-sm font-semibold text-white"
              style={{ background: avatarGradient(m.username) }}
            >
              {m.avatar
                ? <img src={m.avatar} alt={m.username} className="size-full object-cover" />
                : getInitials(m.username)
              }
            </div>
            {m.is_online && (
              <span className="absolute -bottom-0.5 -right-0.5 size-3 rounded-full border-2 border-[#161c2d] bg-[#22c98a]" />
            )}
          </div>

          {/* Body */}
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <span className="text-sm font-semibold text-[#e8edf7]">{m.username}</span>
              {m.kyc_verified && <span className="text-sm text-[#4dabf7]">✓</span>}
              {m.rank && (
                <span className={`inline-block rounded-full border px-2 py-0.5 text-xs ${rankStyle[m.rank] || "bg-[#8b96b0]/10 text-[#8b96b0] border-[#8b96b0]/20"}`}>
                  {m.rank}
                </span>
              )}
              <span className={`flex items-center gap-1 text-xs ${m.is_online ? "text-[#22c98a]" : "text-[#4a5568]"}`}>
                <span className="leading-none">●</span> {m.is_online ? "Online" : "Offline"}
              </span>
            </div>

            {m.payment_methods?.length > 0 && (
              <div className="mt-2 flex flex-wrap gap-1.5">
                {m.payment_methods.slice(0, 4).map((pm, i) => (
                  <span key={i} className="rounded bg-white/[0.04] px-2 py-0.5 text-xs text-[#4a5568]">
                    {pm.label || pm.provider || pm.type}
                  </span>
                ))}
                {m.payment_methods.length > 4 && (
                  <span className="rounded bg-white/[0.04] px-2 py-0.5 text-xs text-[#4a5568]">
                    +{m.payment_methods.length - 4}
                  </span>
                )}
              </div>
            )}

            {m.currencies?.length > 0 && (
              <div className="mt-1.5 flex flex-wrap gap-1.5">
                {m.currencies.slice(0, 4).map(c => (
                  <span key={c.currency_code} className="rounded bg-[#4f6ef7]/10 px-2 py-0.5 font-mono text-xs text-[#4f6ef7]">
                    {c.currency_code}
                  </span>
                ))}
              </div>
            )}
          </div>

          {/* Right */}
          <div className="shrink-0 text-right">
            <div className="font-mono text-xl font-semibold text-[#22c98a]">{formatUsdc(m.available_usdc)}</div>
            <div className="text-xs text-[#4a5568]">available</div>
            <div className="mt-1.5 font-mono text-sm font-semibold text-[#e8edf7]">{m.completion_rate || 0}%</div>
            <div className="text-xs text-[#4a5568]">{(m.total_trades || 0).toLocaleString()} trades</div>
          </div>
        </div>

        <div className="mt-3 flex items-center justify-between border-t border-[#1e2a42] pt-2.5">
          <div className="flex flex-wrap gap-1.5">
            {m.is_fast_responder && (
              <span className="inline-flex items-center gap-1 rounded-full bg-[#fbbf24]/10 px-2 py-0.5 text-xs text-[#fbbf24]">
                ⚡ Fast
              </span>
            )}
            {m.has_liquidity && (
              <span className="inline-flex items-center gap-1 rounded-full bg-[#22c98a]/10 px-2 py-0.5 text-xs text-[#22c98a]">
                $ Liquidity
              </span>
            )}
            {m.kyc_verified && (
              <span className="inline-flex items-center gap-1 rounded-full bg-[#4dabf7]/10 px-2 py-0.5 text-xs text-[#4dabf7]">
                ✓ Verified
              </span>
            )}
          </div>
          <span className="flex items-center gap-1 text-sm text-[#8b96b0] hover:text-[#4f6ef7] transition-colors">
            Trade now →
          </span>
        </div>
      </div>
    </Link>
  )
}

function Marketplace() {
  const [merchants, setMerchants] = useState([])
  const [loading,   setLoading]   = useState(true)
  const [page,      setPage]      = useState(1)
  const [lastPage,  setLastPage]  = useState(1)
  const [total,     setTotal]     = useState(0)
  const [search,    setSearch]    = useState("")
  const [currency,  setCurrency]  = useState("")
  const [payment,   setPayment]   = useState("")
  const [tab,       setTab]       = useState("buy")

  const load = useCallback(async (reset = false) => {
    setLoading(true)
    try {
      const res  = await fetchMerchants({ currency, payment, page: reset ? 1 : page })
      const d    = res.data || {}
      const list = d.merchants || []
      setMerchants(prev => reset ? list : [...prev, ...list])
      setLastPage(d.pagination?.last_page || 1)
      setTotal(d.pagination?.total || 0)
      if (reset) setPage(1)
    } catch { /* silent */ }
    finally { setLoading(false) }
  }, [currency, payment, page])

  useEffect(() => { setPage(1); load(true) }, [currency, payment, tab])
  useEffect(() => { if (page > 1) load(false) }, [page])

  const visible = merchants.filter(m =>
    !search || m.username.toLowerCase().includes(search.toLowerCase())
  )

  return (
    <div>
      {/* Tabs */}
      <div className="mb-4 inline-flex gap-1 rounded-lg bg-[#141928] p-1">
        <button
          onClick={() => setTab("buy")}
          className={`flex items-center gap-1.5 rounded-md px-5 py-2 text-sm transition-colors ${
            tab === "buy"
              ? "border border-[#263350] bg-[#1a2135] text-[#e8edf7]"
              : "text-[#8b96b0] hover:text-[#e8edf7]"
          }`}
        >
          <span className="text-xs text-[#22c98a]">▲</span> Buy USDC
        </button>
        <button
          onClick={() => setTab("sell")}
          className={`flex items-center gap-1.5 rounded-md px-5 py-2 text-sm transition-colors ${
            tab === "sell"
              ? "border border-[#263350] bg-[#1a2135] text-[#e8edf7]"
              : "text-[#8b96b0] hover:text-[#e8edf7]"
          }`}
        >
          <span className="text-xs text-[#f87171]">▼</span> Sell USDC
        </button>
      </div>

      {/* Info banner */}
      {tab === "buy" ? (
        <div className="mb-4 flex items-start gap-2 rounded-lg border border-[#22c98a]/20 bg-[#22c98a]/[0.06] px-4 py-3 text-sm leading-relaxed text-[#22c98a]/85">
          <span className="shrink-0">🛡</span>
          You send fiat → merchant releases USDC from escrow to your wallet.
        </div>
      ) : (
        <div className="mb-4 flex items-start gap-2 rounded-lg border border-[#4dabf7]/20 bg-[#4dabf7]/[0.06] px-4 py-3 text-sm leading-relaxed text-[#4dabf7]/90">
          <span className="shrink-0">🔵</span>
          You send USDC → merchant pays you in fiat via your chosen method.
        </div>
      )}

      {/* Filters */}
      <div className="mb-3 flex flex-wrap gap-2">
        <div className="relative min-w-[140px] flex-1">
          <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-[#4a5568]">🔍</span>
          <input
            placeholder="Search merchants…"
            value={search}
            onChange={e => setSearch(e.target.value)}
            className="w-full rounded-lg border border-[#1e2a42] bg-[#161c2d] py-2 pl-9 pr-3 text-sm text-[#e8edf7] placeholder:text-[#4a5568] focus:border-[#4f6ef7] focus:outline-none"
          />
        </div>
        <select
          value={currency}
          onChange={e => setCurrency(e.target.value)}
          className="min-w-[140px] rounded-lg border border-[#1e2a42] bg-[#161c2d] px-3 py-2 text-sm text-[#e8edf7] focus:border-[#4f6ef7] focus:outline-none"
        >
          <option value="">All currencies</option>
          <option value="DOP">🇩🇴 DOP</option>
          <option value="USD">🇺🇸 USD</option>
          <option value="EUR">🇪🇺 EUR</option>
          <option value="HTG">🇭🇹 HTG</option>
          <option value="COP">🇨🇴 COP</option>
        </select>
        <select
          value={payment}
          onChange={e => setPayment(e.target.value)}
          className="min-w-[160px] rounded-lg border border-[#1e2a42] bg-[#161c2d] px-3 py-2 text-sm text-[#e8edf7] focus:border-[#4f6ef7] focus:outline-none"
        >
          <option value="">All methods</option>
          <option value="bank">Bank Transfer</option>
          <option value="wise">Wise</option>
          <option value="paypal">PayPal</option>
          <option value="zelle">Zelle</option>
          <option value="cash">Cash Meeting</option>
        </select>
      </div>

      {/* Count */}
      {!loading && (
        <p className="mb-3 text-sm text-[#4a5568]">
          {total > 0
            ? `${total} merchant${total !== 1 ? "s" : ""} available`
            : "No merchants found for this filter"}
        </p>
      )}

      {/* Cards */}
      {loading && merchants.length === 0 ? (
        <div className="flex flex-col gap-2.5">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="h-32 animate-pulse rounded-xl border border-[#1e2a42] bg-[#161c2d]" />
          ))}
        </div>
      ) : visible.length > 0 ? (
        <>
          <div className="flex flex-col gap-2.5">
            {visible.map(m => <MerchantCard key={m.username} m={m} />)}
          </div>

          {page < lastPage && (
            <div className="mt-4 flex justify-center">
              <button
                onClick={() => setPage(p => p + 1)}
                disabled={loading}
                className="flex items-center gap-2 rounded-lg border border-[#263350] bg-transparent px-6 py-2.5 text-sm text-[#8b96b0] hover:text-[#e8edf7] disabled:opacity-50"
              >
                {loading && <span className="animate-spin">⟳</span>}
                Load more merchants
              </button>
            </div>
          )}
        </>
      ) : (
        <div className="py-16 text-center text-[#4a5568]">
          <div className="mx-auto mb-2 text-3xl opacity-40">👥</div>
          <p className="text-sm">No merchants match your filters</p>
        </div>
      )}
    </div>
  )
}

// ─── Static content ───────────────────────────────────────────────────────────

const STEPS = [
  { num: "1", emoji: "👛", title: "Connect your wallet", desc: "MetaMask, Trust Wallet, or any Web3 wallet. Your wallet is your identity.", bg: "bg-[#4f6ef7]/12" },
  { num: "2", emoji: "🔍", title: "Find a merchant",     desc: "Browse verified merchants, compare rates and payment methods.",             bg: "bg-[#4dabf7]/12" },
  { num: "3", emoji: "🛡", title: "Trade securely",      desc: "USDC locked in smart contract escrow. 0.2% fee. Trades complete on-chain.", bg: "bg-[#22c98a]/12" },
]

const FEATURES = [
  { emoji: "🔒", title: "On-chain escrow",     desc: "Audited smart contracts on Base L2" },
  { emoji: "⚡", title: "Instant settlements", desc: "USDC released the moment payment confirmed" },
  { emoji: "🌐", title: "Multi-currency",      desc: "DOP, EUR, HTG, COP and more" },
  { emoji: "🤝", title: "Cash meetings",       desc: "In-person trades with NFT verification" },
  { emoji: "⏱", title: "Trade protection",    desc: "Auto-cancel timers protect both parties" },
  { emoji: "✅", title: "Verified merchants",  desc: "KYC-verified with rank badges & reviews" },
]

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function Landing() {
  return (
    <div className="min-h-screen overflow-x-hidden bg-[#0a0d14] text-[#e8edf7]">
      <PublicHeader showMarketplaceAnchor />

      {/* HERO */}
      <section
        className="px-5 py-14 text-center lg:py-20"
        style={{
          background:
            "radial-gradient(ellipse at 70% 0%, rgba(79,110,247,0.06) 0%, transparent 60%),radial-gradient(ellipse at 30% 100%, rgba(34,201,138,0.04) 0%, transparent 60%),#0a0d14",
        }}
      >
        <span className="mb-5 inline-flex items-center gap-1.5 rounded-full border border-[#4f6ef7]/30 bg-[#4f6ef7]/12 px-3.5 py-1.5 text-sm text-[#4dabf7]">
          🔵 Powered by Base (Coinbase L2)
        </span>

        <h1 className="mx-auto max-w-3xl text-4xl font-semibold leading-tight text-[#e8edf7] lg:text-5xl">
          P2P USDC Trading
          <span className="block bg-gradient-to-r from-[#4f6ef7] via-[#4dabf7] to-[#22c98a] bg-clip-text text-transparent">
            Without Middlemen
          </span>
        </h1>

        <p className="mx-auto mt-4 max-w-xl text-base leading-relaxed text-[#8b96b0]">
          Buy and sell USDC directly with merchants using bank transfers, online payments, or in-person cash meetings. All trades secured by on-chain escrow.
        </p>

        <div className="mt-8 flex flex-wrap justify-center gap-3">
          <ConnectWallet size="lg" className="h-auto rounded-lg bg-[#4f6ef7] px-7 py-3 text-base font-semibold text-white hover:bg-[#3a56d4]" />
          <a
            href="#marketplace"
            className="inline-flex items-center rounded-lg border border-[#263350] bg-transparent px-7 py-3 text-base text-[#e8edf7] hover:bg-white/5 transition-colors"
          >
            Marketplace →
          </a>
        </div>
      </section>

      {/* STATS */}
      <div className="bg-[#0a0d14] pt-5">
        <StatsGrid />
      </div>

      {/* TOP 10 CAROUSEL */}
      <section className="border-t border-[#1e2a42] bg-[#0f1320]">
        <div className="mx-auto max-w-5xl px-5 py-8">
          <TopMerchantsCarousel />
        </div>
      </section>

      {/* HOW IT WORKS */}
      <section className="border-t border-[#1e2a42] bg-[#0a0d14]">
        <div className="mx-auto max-w-5xl px-5 py-8">
          <div className="mb-5 text-center">
            <div className="text-xl font-semibold text-[#e8edf7]">How it works</div>
            <div className="mt-1 text-sm text-[#8b96b0]">Three simple steps to start trading</div>
          </div>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            {STEPS.map(s => (
              <div key={s.num} className="relative overflow-hidden rounded-xl border border-[#1e2a42] bg-[#161c2d] p-5">
                <div className="pointer-events-none absolute right-3 top-2 font-mono text-5xl font-semibold leading-none text-white/[0.04]">
                  {s.num}
                </div>
                <div className={`mb-3 flex size-11 items-center justify-center rounded-[10px] text-xl ${s.bg}`}>
                  {s.emoji}
                </div>
                <div className="mb-1 text-base font-semibold text-[#e8edf7]">{s.title}</div>
                <div className="text-sm leading-relaxed text-[#8b96b0]">{s.desc}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* FEATURES */}
      <section className="border-t border-[#1e2a42] bg-[#0f1320]">
        <div className="mx-auto max-w-5xl px-5 py-8">
          <div className="mb-5 text-center">
            <div className="text-xl font-semibold text-[#e8edf7]">Built for security</div>
            <div className="mt-1 text-sm text-[#8b96b0]">Every feature designed to protect your trades</div>
          </div>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {FEATURES.map(f => (
              <div key={f.title} className="flex items-start gap-3 rounded-xl border border-[#1e2a42] bg-[#161c2d] p-4">
                <div className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-[#4f6ef7]/10 text-lg">
                  {f.emoji}
                </div>
                <div>
                  <div className="text-sm font-semibold text-[#e8edf7]">{f.title}</div>
                  <div className="mt-1 text-sm leading-snug text-[#8b96b0]">{f.desc}</div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* MARKETPLACE */}
      <section id="marketplace" className="border-t border-[#1e2a42] bg-[#0a0d14]">
        <div className="mx-auto max-w-5xl px-5 py-8">
          <div className="mb-5 text-center">
            <div className="text-xl font-semibold text-[#e8edf7]">Marketplace</div>
            <div className="mt-1 text-sm text-[#8b96b0]">Live merchants ready to trade USDC with you</div>
          </div>
          <Marketplace />
        </div>
      </section>

      {/* CTA */}
      <section className="border-t border-[#1e2a42] bg-[#0f1320]">
        <div className="mx-auto max-w-5xl px-5 py-10 text-center">
          <h2 className="text-2xl font-semibold text-[#e8edf7]">Ready to trade?</h2>
          <p className="mt-2 text-base text-[#8b96b0]">Connect your wallet and start trading USDC in minutes</p>
          <div className="mt-6 flex justify-center">
            <ConnectWallet size="lg" className="h-auto rounded-lg bg-[#4f6ef7] px-8 py-3 text-base font-semibold text-white hover:bg-[#3a56d4]" />
          </div>
        </div>
      </section>

      <PublicFooter />
    </div>
  )
}
