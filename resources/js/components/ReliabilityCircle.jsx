export default function ReliabilityCircle({ rate, max = 10, size = 72, stroke = 5, color = "text-emerald-500" }) {
  const radius = (size - stroke * 2) / 2
  const circumference = 2 * Math.PI * radius
  const percent = (rate / max) * 100
  const offset = circumference - (percent / 100) * circumference
  return (
    <div className="relative" style={{ width: size, height: size }}>
      <svg width={size} height={size} className="-rotate-90">
        <circle
          cx={size / 2}
          cy={size / 2}
          r={radius}
          fill="none"
          className="stroke-muted/30"
          strokeWidth={stroke}
        />
        <circle
          cx={size / 2}
          cy={size / 2}
          r={radius}
          fill="none"
          className={`${color} transition-all duration-1000`}
          strokeWidth={stroke}
          strokeDasharray={circumference}
          strokeDashoffset={offset}
          strokeLinecap="round"
          style={{ stroke: "currentColor" }}
        />
      </svg>
      <div className="absolute inset-0 flex items-center justify-center">
        <span className="font-mono text-lg font-bold">{rate}</span>
      </div>
    </div>
  )
}
