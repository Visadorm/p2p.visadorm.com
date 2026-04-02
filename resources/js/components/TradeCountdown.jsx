import { Clock } from "@phosphor-icons/react"
import { Card, CardContent } from "@/components/ui/card"

export default function TradeCountdown({ timeLeft, label = "Time Remaining", description = "Complete payment before the timer expires" }) {
  const minutes = Math.floor(timeLeft / 60)
  const seconds = timeLeft % 60
  const timeDisplay = `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`

  const timerColor =
    minutes >= 10 ? "text-emerald-400" : minutes >= 5 ? "text-amber-400" : "text-red-400"
  const timerBorder =
    minutes >= 10
      ? "border-emerald-500/20"
      : minutes >= 5
        ? "border-amber-500/20"
        : "border-red-500/20"
  const timerBg =
    minutes >= 10 ? "bg-emerald-500/5" : minutes >= 5 ? "bg-amber-500/5" : "bg-red-500/5"

  return (
    <Card className={`border ${timerBorder} ${timerBg}`}>
      <CardContent className="flex flex-col items-center gap-2 py-6">
        <div className="flex items-center gap-2">
          <Clock weight="duotone" size={20} className={timerColor} />
          <span className="text-sm font-medium text-muted-foreground">{label}</span>
        </div>
        <span className={`font-mono text-5xl font-bold tracking-wider ${timerColor}`}>
          {timeDisplay}
        </span>
        <p className="text-sm text-muted-foreground">{description}</p>
      </CardContent>
    </Card>
  )
}
