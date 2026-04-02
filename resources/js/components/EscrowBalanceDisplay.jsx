export default function EscrowBalanceDisplay({ available, locked, totalVolume, activeTradesCount, openDisputesCount }) {
  const total = totalVolume || (available + locked) || 0
  const lockedPercent = total > 0 ? Math.round((locked / total) * 100) : 0
  const availablePercent = 100 - lockedPercent

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-4 sm:gap-6">
        <div className="flex items-center gap-2">
          <span className="size-2 rounded-full bg-emerald-500" />
          <span className="text-sm text-muted-foreground">Available:</span>
          <span className="font-mono text-sm font-semibold text-emerald-500">
            ${available.toLocaleString()}
          </span>
        </div>
        <div className="flex items-center gap-2">
          <span className="size-2 rounded-full bg-amber-500" />
          <span className="text-sm text-muted-foreground">Locked:</span>
          <span className="font-mono text-sm font-semibold text-amber-500">
            ${locked.toLocaleString()}
          </span>
        </div>
        {activeTradesCount != null && (
          <div className="flex items-center gap-2">
            <span className="text-sm text-muted-foreground">Active Trades:</span>
            <span className="font-mono text-sm font-semibold">{activeTradesCount}</span>
          </div>
        )}
        {openDisputesCount > 0 && (
          <div className="flex items-center gap-2">
            <span className="text-sm text-red-400">Disputes:</span>
            <span className="font-mono text-sm font-semibold text-red-400">{openDisputesCount}</span>
          </div>
        )}
      </div>
      {total > 0 && (
        <div className="h-2 w-full max-w-md overflow-hidden rounded-full bg-muted">
          <div className="flex h-full">
            <div
              className="h-full rounded-l-full bg-emerald-500 transition-all"
              style={{ width: `${availablePercent}%` }}
            />
            <div
              className="h-full rounded-r-full bg-amber-500 transition-all"
              style={{ width: `${lockedPercent}%` }}
            />
          </div>
        </div>
      )}
    </div>
  )
}
