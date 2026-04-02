import { CheckCircle } from "@phosphor-icons/react"

export default function TradeStatusTimeline({ steps }) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-y-3">
      {steps.map((step, i) => {
        const isCompleted = step.completed
        const isCurrent = step.current
        return (
          <div key={i} className="flex flex-1 items-center min-w-0">
            <div className="flex flex-col items-center gap-2">
              <div
                className={`flex size-9 items-center justify-center rounded-full border-2 text-sm font-bold transition-colors ${
                  isCompleted
                    ? "border-emerald-500 bg-emerald-500 text-white"
                    : isCurrent
                      ? "border-primary bg-primary text-primary-foreground"
                      : "border-muted-foreground/30 bg-muted/20 text-muted-foreground"
                }`}
              >
                {isCompleted ? <CheckCircle weight="fill" size={18} /> : i + 1}
              </div>
              <span
                className={`text-sm font-medium whitespace-nowrap ${
                  isCompleted
                    ? "text-emerald-400"
                    : isCurrent
                      ? "text-primary"
                      : "text-muted-foreground"
                }`}
              >
                {step.label}
              </span>
            </div>
            {i < steps.length - 1 && (
              <div
                className={`mx-2 mb-6 h-0.5 flex-1 hidden sm:block ${
                  isCompleted ? "bg-emerald-500" : "bg-muted-foreground/20"
                }`}
              />
            )}
          </div>
        )
      })}
    </div>
  )
}
