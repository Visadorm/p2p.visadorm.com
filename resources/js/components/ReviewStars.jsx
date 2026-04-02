import { Star, StarHalf } from "@phosphor-icons/react"

export default function ReviewStars({ rating, size = 16 }) {
  const full = Math.floor(rating)
  const hasHalf = rating - full >= 0.3
  return (
    <div className="flex items-center gap-0.5">
      {[...Array(5)].map((_, i) => {
        if (i < full) return <Star key={i} weight="fill" size={size} className="text-amber-400" />
        if (i === full && hasHalf)
          return <StarHalf key={i} weight="fill" size={size} className="text-amber-400" />
        return <Star key={i} weight="regular" size={size} className="text-muted-foreground/30" />
      })}
    </div>
  )
}
