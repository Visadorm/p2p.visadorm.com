import { useRef, useEffect } from "react"
import { CheckCircle } from "@phosphor-icons/react"

export default function RecentTradesCarousel({ trades }) {
  const scrollRef = useRef(null)

  // Auto-scroll animation
  useEffect(() => {
    const el = scrollRef.current
    if (!el || trades.length <= 2) return

    let animId
    let paused = false

    const scroll = () => {
      if (!paused && el.scrollLeft < el.scrollWidth - el.clientWidth) {
        el.scrollLeft += 0.5
      } else if (!paused) {
        el.scrollLeft = 0
      }
      animId = requestAnimationFrame(scroll)
    }

    animId = requestAnimationFrame(scroll)

    const pause = () => { paused = true }
    const resume = () => { paused = false }
    el.addEventListener("mouseenter", pause)
    el.addEventListener("mouseleave", resume)
    el.addEventListener("touchstart", pause)
    el.addEventListener("touchend", resume)

    return () => {
      cancelAnimationFrame(animId)
      el.removeEventListener("mouseenter", pause)
      el.removeEventListener("mouseleave", resume)
      el.removeEventListener("touchstart", pause)
      el.removeEventListener("touchend", resume)
    }
  }, [trades])

  if (!trades || trades.length === 0) return null

  return (
    <div>
      <h3 className="mb-4 text-lg font-semibold">Recent Trades</h3>
      <div
        ref={scrollRef}
        className="flex gap-3 overflow-x-auto pb-2 max-w-full"
        style={{ scrollbarWidth: "none", scrollBehavior: "auto" }}
      >
        {trades.map((trade, i) => {
          const isCash = ["cash_meeting", "cash meeting"].includes((trade.payment_method || "").toLowerCase())
          return (
            <div key={i} className="flex min-w-[200px] flex-col gap-2 rounded-xl border border-border/50 bg-card p-4 shrink-0">
              <div className="flex items-center justify-between">
                <span className="text-sm font-semibold">{trade.counterparty || trade.buyer}</span>
                <span className={`inline-flex items-center rounded-full px-1.5 py-0.5 text-xs font-medium ${
                  trade.role === "buy" ? "bg-emerald-500/15 text-emerald-400" : "bg-blue-500/15 text-blue-400"
                }`}>
                  {trade.role === "buy" ? "Buy" : "Sell"}
                </span>
              </div>
              <span className="font-mono text-base font-bold">${Number(trade.amount).toLocaleString()} USDC</span>
              <div className="flex items-center justify-between">
                <span className="text-xs text-muted-foreground">{trade.payment_method || "Transfer"}</span>
                <span className="text-xs text-muted-foreground">{trade.time}</span>
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}
