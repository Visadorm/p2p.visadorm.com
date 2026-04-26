import { useState, useEffect } from "react"
import { Link } from "@inertiajs/react"
import DashboardLayout from "@/layouts/DashboardLayout"
import { api } from "@/lib/api"
import { useWallet } from "@/hooks/useWallet"
import { toast } from "sonner"
import { Star as StarIcon, User, ChatCircleText, ArrowRight } from "@phosphor-icons/react"
import ReviewStars from "@/components/ReviewStars"
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Avatar, AvatarFallback } from "@/components/ui/avatar"
import { Separator } from "@/components/ui/separator"
import { Skeleton } from "@/components/ui/skeleton"

const filters = ["All", "5", "4", "3", "2", "1"]


function ReviewsSkeleton() {
  return (
    <div className="space-y-6">
      <Card className="border-border/50">
        <CardContent className="pt-6">
          <div className="grid grid-cols-1 gap-8 md:grid-cols-2">
            <div className="flex flex-col items-center justify-center gap-3">
              <Skeleton className="h-16 w-[100px]" />
              <Skeleton className="h-6 w-[140px]" />
              <Skeleton className="h-4 w-[120px]" />
            </div>
            <div className="space-y-3">
              {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="flex items-center gap-3">
                  <Skeleton className="h-4 w-10" />
                  <Skeleton className="h-2.5 flex-1 rounded-full" />
                  <Skeleton className="h-4 w-10" />
                </div>
              ))}
            </div>
          </div>
        </CardContent>
      </Card>
      <Card className="border-border/50">
        <CardContent className="p-0 divide-y divide-border/50">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="px-6 py-5">
              <div className="flex items-start gap-4">
                <Skeleton className="size-10 rounded-full" />
                <div className="flex-1 space-y-2">
                  <div className="flex justify-between">
                    <Skeleton className="h-4 w-[120px]" />
                    <Skeleton className="h-4 w-[80px]" />
                  </div>
                  <Skeleton className="h-4 w-full" />
                  <Skeleton className="h-4 w-3/4" />
                  <Skeleton className="h-5 w-[100px] rounded-full" />
                </div>
              </div>
            </div>
          ))}
        </CardContent>
      </Card>
    </div>
  )
}

function getInitials(name) {
  if (!name) return "??"
  const parts = name.trim().split(/\s+/)
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase()
  return name.slice(0, 2).toUpperCase()
}

function truncateAddress(addr) {
  if (!addr) return "Anonymous"
  return `${addr.slice(0, 6)}...${addr.slice(-4)}`
}

export default function Reviews() {
  const { isAuthenticated, merchant } = useWallet()
  const [activeFilter, setActiveFilter] = useState("All")
  const [reviews, setReviews] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(false)
  const [stats, setStats] = useState({ average: 0, total: 0, breakdown: [] })


  const fetchReviews = async () => {
    if (!isAuthenticated) return
    setLoading(true)
    setError(false)
    try {
      // Fetch ALL completed trades (both as merchant and buyer)
      const res = await api.getMerchantTrades("status=completed&per_page=50&role=all")
      const tradesData = res.data?.data || res.data || []

      // Each trade can produce up to 2 review entries:
      //   - trade.review = buyer's review of seller
      //   - trade.merchant_review = seller's review of buyer
      // Build a flat list, tagging each with whether current user RECEIVED or GAVE it.
      const myWallet = merchant?.wallet_address?.toLowerCase()
      const merchantName = (t) => t.merchant?.username || "Merchant"
      const fmtDate = (iso) => iso ? new Date(iso).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" }) : "—"

      const entries = []
      tradesData.forEach((trade) => {
        const tradeMerchantWallet = trade.merchant?.wallet_address?.toLowerCase()
        const tradeBuyerWallet = trade.buyer_wallet?.toLowerCase()
        const userIsTradeMerchant = myWallet && tradeMerchantWallet === myWallet
        const userIsTradeBuyer = myWallet && tradeBuyerWallet === myWallet

        if (trade.review) {
          // Buyer (reviewer) reviewed the seller (subject = trade.merchant)
          const userReceived = userIsTradeMerchant
          const userGave = userIsTradeBuyer
          if (userReceived || userGave) {
            entries.push({
              id: `r-${trade.review.id || trade.id}`,
              role: userReceived ? "received" : "given",
              person: userReceived ? truncateAddress(trade.buyer_wallet) : merchantName(trade),
              personLink: userReceived ? null : `/merchant/${merchantName(trade)}`,
              initials: userReceived ? getInitials(truncateAddress(trade.buyer_wallet)) : getInitials(merchantName(trade)),
              rating: trade.review.rating || 0,
              comment: trade.review.comment || trade.review.text || "",
              amount: `$${Number(trade.amount_usdc || 0).toLocaleString()} USDC`,
              date: fmtDate(trade.review.created_at || trade.created_at),
              tradeType: trade.type || "buy",
            })
          }
        }

        if (trade.merchant_review) {
          // Seller (reviewer) reviewed the buyer (subject = trade.buyer_wallet)
          const userReceived = userIsTradeBuyer
          const userGave = userIsTradeMerchant
          if (userReceived || userGave) {
            entries.push({
              id: `m-${trade.merchant_review.id || trade.id}`,
              role: userReceived ? "received" : "given",
              person: userReceived ? merchantName(trade) : truncateAddress(trade.buyer_wallet),
              personLink: userReceived ? `/merchant/${merchantName(trade)}` : null,
              initials: userReceived ? getInitials(merchantName(trade)) : getInitials(truncateAddress(trade.buyer_wallet)),
              rating: trade.merchant_review.rating || 0,
              comment: trade.merchant_review.comment || trade.merchant_review.text || "",
              amount: `$${Number(trade.amount_usdc || 0).toLocaleString()} USDC`,
              date: fmtDate(trade.merchant_review.created_at || trade.created_at),
              tradeType: trade.type || "buy",
            })
          }
        }
      })

      const extractedReviews = entries

      setReviews(extractedReviews)

      // Calculate stats from merchant data or reviews
      const totalReviews = merchant?.total_reviews || extractedReviews.length
      const avgRating = merchant?.rating || (extractedReviews.length > 0
        ? (extractedReviews.reduce((sum, r) => sum + r.rating, 0) / extractedReviews.length).toFixed(1)
        : 0)

      // Build breakdown
      const breakdown = [5, 4, 3, 2, 1].map((stars) => {
        const count = extractedReviews.filter((r) => r.rating === stars).length
        const percent = totalReviews > 0 ? Math.round((count / totalReviews) * 100) : 0
        return { stars, count, percent }
      })

      setStats({
        average: parseFloat(avgRating) || 0,
        total: totalReviews,
        breakdown,
      })
    } catch (err) {
      toast.error("Failed to load reviews")
      setError(true)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchReviews()
  }, [isAuthenticated])

  const filtered = reviews.filter((review) => {
    if (activeFilter === "All") return true
    return review.rating === parseInt(activeFilter)
  })

  if (error) {
    return (
      <div className="space-y-6">
        <div className="flex flex-col items-center justify-center py-20 text-center">
          <ChatCircleText className="size-16 text-muted-foreground/20 mb-4" weight="duotone" />
          <p className="text-lg font-semibold mb-2">Failed to load reviews</p>
          <p className="text-sm text-muted-foreground mb-6">Something went wrong. Please try again.</p>
          <Button onClick={() => fetchReviews()}>Retry</Button>
        </div>
      </div>
    )
  }

  if (loading) {
    return <ReviewsSkeleton />
  }

  const fullStars = Math.floor(stats.average)

  return (
    <div className="space-y-6">
      {/* Summary Card */}
      <Card className="border-border/50">
        <CardContent className="pt-6">
          <div className="grid grid-cols-1 gap-8 md:grid-cols-2">
            {/* Left: Big rating */}
            <div className="flex flex-col items-center justify-center gap-3">
              <span className="font-mono text-4xl sm:text-6xl font-bold tracking-tight">{stats.average.toFixed(1)}</span>
              <ReviewStars rating={fullStars} size={24} />
              <span className="text-sm text-muted-foreground">Based on {stats.total} reviews</span>
            </div>
            {/* Right: Rating breakdown */}
            <div className="space-y-3">
              {stats.breakdown.map((item) => (
                <div key={item.stars} className="flex items-center gap-3">
                  <div className="flex w-10 items-center gap-1">
                    <span className="text-sm font-medium">{item.stars}</span>
                    <StarIcon className="size-3.5 text-amber-400" weight="fill" />
                  </div>
                  <div className="h-2.5 flex-1 overflow-hidden rounded-full bg-muted">
                    <div
                      className="h-full rounded-full bg-primary transition-all"
                      style={{ width: `${item.percent}%` }}
                    />
                  </div>
                  <span className="w-10 text-right font-mono text-sm text-muted-foreground">{item.count}</span>
                </div>
              ))}
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Filter row */}
      <div className="flex flex-wrap items-center gap-2">
        {filters.map((filter) => (
          <Button
            key={filter}
            variant="ghost"
            size="sm"
            className={activeFilter === filter ? "bg-primary/10 text-primary" : ""}
            onClick={() => setActiveFilter(filter)}
          >
            {filter === "All" ? "All Reviews" : `${filter} Stars`}
          </Button>
        ))}
      </div>

      {/* Review cards */}
      <Card className="border-border/50">
        <CardContent className="divide-y divide-border/50 p-0">
          {filtered.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-16 text-center px-6">
              <ChatCircleText className="size-12 text-muted-foreground/30 mb-3" weight="duotone" />
              <p className="text-muted-foreground font-medium">No reviews yet</p>
              <p className="text-sm text-muted-foreground/60">
                {activeFilter !== "All" ? "No reviews with this rating" : "Reviews from your completed trades will appear here"}
              </p>
            </div>
          ) : (
            filtered.map((review) => (
              <div key={review.id} className="px-6 py-5">
                <div className="flex items-start gap-4">
                  <Avatar>
                    <AvatarFallback className="text-sm font-semibold">
                      {review.initials}
                    </AvatarFallback>
                  </Avatar>
                  <div className="min-w-0 flex-1 space-y-2">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                      <div className="flex items-center gap-3">
                        {review.personLink ? (
                          <Link href={review.personLink} className="text-sm font-semibold hover:text-primary transition-colors">
                            {review.person}
                          </Link>
                        ) : (
                          <span className="text-sm font-semibold">{review.person}</span>
                        )}
                        <ReviewStars rating={review.rating} />
                      </div>
                      <div className="flex items-center gap-2">
                        <Badge variant="outline" className={review.role === "given"
                          ? "text-purple-400 border-purple-500/30"
                          : "text-emerald-400 border-emerald-500/30"
                        }>
                          {review.role === "given" ? "You gave" : "You received"}
                        </Badge>
                        <span className="text-sm text-muted-foreground">{review.date}</span>
                      </div>
                    </div>
                    <p className="text-sm leading-relaxed text-muted-foreground">{review.comment}</p>
                    <div className="flex items-center gap-2">
                      <span className="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-sm font-medium text-primary">
                        {review.amount}
                      </span>
                      {review.personLink && (
                        <Link href={review.personLink} className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-primary transition-colors">
                          View merchant <ArrowRight size={14} />
                        </Link>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            ))
          )}
        </CardContent>
      </Card>
    </div>
  )
}

Reviews.layout = (page) => <DashboardLayout>{page}</DashboardLayout>
