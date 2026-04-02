import { Skeleton } from "@/components/ui/skeleton"
import { Card, CardContent, CardHeader } from "@/components/ui/card"

export function StatsSkeleton() {
  return (
    <div className="grid grid-cols-2 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      {Array.from({ length: 4 }).map((_, i) => (
        <Card key={i} className="border-border/50">
          <CardContent className="pt-6">
            <div className="flex items-start justify-between">
              <div className="space-y-3">
                <Skeleton className="h-4 w-[80px]" />
                <Skeleton className="h-8 w-[120px]" />
                <div className="flex items-center gap-2">
                  <Skeleton className="h-4 w-4 rounded-full" />
                  <Skeleton className="h-3 w-[60px]" />
                </div>
              </div>
              <Skeleton className="size-11 rounded-xl" />
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  )
}

export function TradesListSkeleton() {
  return (
    <Card className="border-border/50">
      <CardHeader>
        <div className="flex items-center justify-between">
          <div className="space-y-2">
            <Skeleton className="h-5 w-[140px]" />
            <Skeleton className="h-3 w-[200px]" />
          </div>
          <Skeleton className="h-8 w-[80px] rounded-md" />
        </div>
      </CardHeader>
      <CardContent>
        <Skeleton className="mb-3 h-10 w-full rounded-lg" />
        {Array.from({ length: 5 }).map((_, i) => (
          <div key={i} className="flex items-center gap-4 py-3">
            <Skeleton className="h-4 w-[70px]" />
            <Skeleton className="h-4 w-[100px]" />
            <Skeleton className="ml-auto h-4 w-[60px]" />
            <Skeleton className="h-5 w-[80px] rounded-full" />
            <Skeleton className="h-4 w-[70px]" />
          </div>
        ))}
      </CardContent>
    </Card>
  )
}

export function CardSkeleton() {
  return (
    <Card className="border-border/50">
      <CardHeader>
        <Skeleton className="h-5 w-[140px]" />
        <Skeleton className="h-3 w-[180px]" />
      </CardHeader>
      <CardContent>
        <div className="space-y-3">
          <Skeleton className="h-4 w-full" />
          <Skeleton className="h-4 w-[85%]" />
          <Skeleton className="h-4 w-[70%]" />
        </div>
      </CardContent>
    </Card>
  )
}

export function ProfileSkeleton() {
  return (
    <Card className="border-border/50 overflow-hidden pt-0">
      <Skeleton className="h-24 w-full rounded-none" />
      <CardContent className="relative pt-0">
        <div className="-mt-12 flex items-end gap-6">
          <Skeleton className="size-24 rounded-full border-4 border-card" />
          <div className="flex-1 space-y-3 pb-1">
            <div className="flex items-center gap-3">
              <Skeleton className="h-7 w-[160px]" />
              <Skeleton className="h-6 w-[60px] rounded-full" />
            </div>
            <div className="flex gap-2">
              <Skeleton className="h-6 w-[70px] rounded-full" />
              <Skeleton className="h-6 w-[55px] rounded-full" />
              <Skeleton className="h-6 w-[80px] rounded-full" />
            </div>
          </div>
        </div>
        <div className="mt-5 space-y-3">
          <Skeleton className="h-4 w-full" />
          <Skeleton className="h-4 w-[75%]" />
          <Skeleton className="h-10 w-[200px] rounded-lg" />
        </div>
      </CardContent>
    </Card>
  )
}

export function BalanceSkeleton() {
  return (
    <Card className="border-border/50 bg-gradient-to-br from-card via-card to-primary/5">
      <CardContent className="pt-6">
        <div className="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
          <div className="space-y-4">
            <div className="flex items-center gap-3">
              <Skeleton className="size-12 rounded-xl" />
              <div className="space-y-2">
                <Skeleton className="h-4 w-[160px]" />
                <Skeleton className="h-10 w-[220px]" />
              </div>
            </div>
            <div className="flex items-center gap-6">
              <Skeleton className="h-4 w-[120px]" />
              <Skeleton className="h-4 w-[100px]" />
            </div>
            <Skeleton className="h-2 w-full max-w-md rounded-full" />
          </div>
          <div className="flex gap-3">
            <Skeleton className="h-11 w-[110px] rounded-md" />
            <Skeleton className="h-11 w-[110px] rounded-md" />
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
