import {
  SealCheck,
  Lightning,
  Drop,
  Buildings,
  EnvelopeSimple,
  Bank,
} from "@phosphor-icons/react"

const BADGE_CONFIG = [
  { key: "verified", label: "Verified", icon: SealCheck, color: "blue" },
  { key: "fast", label: "Fast", icon: Lightning, color: "emerald" },
  { key: "liquidity", label: "Liquidity", icon: Drop, color: "purple" },
  { key: "business", label: "Business", icon: Buildings, color: "indigo" },
  { key: "email", label: "Email", icon: EnvelopeSimple, color: "teal" },
  { key: "bank", label: "Bank", icon: Bank, color: "red" },
]

const badgeColors = {
  blue: "bg-blue-500/15 text-blue-400 border-blue-500/20",
  emerald: "bg-emerald-500/15 text-emerald-400 border-emerald-500/20",
  purple: "bg-purple-500/15 text-purple-400 border-purple-500/20",
  indigo: "bg-indigo-500/15 text-indigo-400 border-indigo-500/20",
  teal: "bg-teal-500/15 text-teal-400 border-teal-500/20",
  red: "bg-red-500/15 text-red-400 border-red-500/20",
}

export default function VerificationBadges({ badges }) {
  return (
    <div className="flex flex-wrap items-center gap-2">
      {BADGE_CONFIG.filter((b) => badges[b.key]).map((badge) => (
        <span
          key={badge.key}
          className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-sm font-medium ${badgeColors[badge.color]}`}
        >
          <badge.icon weight="fill" size={14} />
          {badge.label}
        </span>
      ))}
    </div>
  )
}
