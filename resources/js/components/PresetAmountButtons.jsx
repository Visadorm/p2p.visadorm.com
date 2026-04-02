export default function PresetAmountButtons({ amounts, selectedAmount, activeTab, onSelect }) {
  return (
    <div className="grid grid-cols-4 gap-2">
      {amounts.map(amt => (
        <button
          key={amt}
          onClick={() => onSelect(amt)}
          className={`rounded-lg border px-3 py-2.5 text-sm font-semibold transition-all ${
            selectedAmount === amt
              ? activeTab === "buy"
                ? "border-emerald-500 bg-emerald-500/20 text-emerald-400 shadow-[0_0_12px_rgba(34,197,94,0.15)]"
                : "border-purple-500 bg-purple-500/20 text-purple-400 shadow-[0_0_12px_rgba(168,85,247,0.15)]"
              : "border-border/50 bg-muted/20 text-foreground hover:border-muted-foreground/30"
          }`}
        >
          ${amt}
        </button>
      ))}
    </div>
  )
}
