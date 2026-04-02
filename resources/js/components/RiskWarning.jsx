import { Warning } from "@phosphor-icons/react"

export default function RiskWarning({ message }) {
  return (
    <div className="flex items-start gap-2 rounded-lg bg-red-500/8 px-3 py-2.5">
      <Warning weight="fill" size={16} className="mt-0.5 shrink-0 text-red-400" />
      <p className="text-sm leading-relaxed text-red-400/80">
        {message ||
          "Only trade with funds you can afford to lose. Verify merchant reputation before trading. Escrow protects your USDC but fiat payments are irreversible."}
      </p>
    </div>
  )
}
