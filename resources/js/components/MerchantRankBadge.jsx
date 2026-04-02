const rankStyles = {
  "New Member": "bg-zinc-500/20 text-zinc-400 border-zinc-500/30",
  "Junior Member": "bg-amber-800/20 text-amber-600 border-amber-700/30",
  "Senior Member": "bg-slate-400/20 text-slate-300 border-slate-400/30",
  "Hero Merchant": "bg-yellow-500/20 text-yellow-400 border-yellow-500/30",
  "Elite Merchant": "bg-blue-500/20 text-blue-400 border-blue-500/30",
  "Legendary Merchant": "bg-purple-500/20 text-purple-300 border-purple-500/30",
}

export default function MerchantRankBadge({ rank }) {
  const rankName = rank || "New Member"
  const style = rankStyles[rankName] || rankStyles["New Member"]
  return (
    <span
      className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-sm font-bold ${style}`}
    >
      {rankName}
    </span>
  )
}
