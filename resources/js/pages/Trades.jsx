import { useState, useEffect } from "react"
import { router } from "@inertiajs/react"
import DashboardLayout from "@/layouts/DashboardLayout"
import { api } from "@/lib/api"
import { useWallet } from "@/hooks/useWallet"
import { toast } from "sonner"
import { MagnifyingGlass, Eye, CaretLeft, CaretRight, ArrowsLeftRight } from "@phosphor-icons/react"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Skeleton } from "@/components/ui/skeleton"
import {
  Table,
  TableHeader,
  TableBody,
  TableHead,
  TableRow,
  TableCell,
} from "@/components/ui/table"

const filters = ["All", "pending", "escrow_locked", "payment_sent", "completed", "disputed", "cancelled/expired"]

const filterLabels = {
  All: "All",
  pending: "Pending",
  escrow_locked: "Escrow Locked",
  payment_sent: "Payment Sent",
  completed: "Completed",
  disputed: "Disputed",
  "cancelled/expired": "Cancelled / Expired",
}

const STATUS_STYLES = {
  completed: { label: "Completed", className: "bg-emerald-500/15 text-emerald-500" },
  payment_sent: { label: "Payment Sent", className: "bg-blue-500/15 text-blue-500" },
  pending: { label: "Pending", className: "bg-amber-500/15 text-amber-500" },
  disputed: { label: "Disputed", className: "bg-red-500/15 text-red-500" },
  escrow_locked: { label: "Escrow Locked", className: "bg-purple-500/15 text-purple-500" },
  cancelled: { label: "Cancelled", className: "bg-muted-foreground/15 text-muted-foreground" },
  expired: { label: "Expired", className: "bg-muted-foreground/15 text-muted-foreground" },
}

function statusBadge(status) {
  const style = STATUS_STYLES[status] || { label: status, className: "bg-muted text-muted-foreground" }
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-sm font-medium ${style.className}`}>
      {style.label}
    </span>
  )
}

function proofBadge(proof) {
  const styles = {
    approved: "bg-emerald-500/15 text-emerald-500",
    uploaded: "bg-blue-500/15 text-blue-500",
    pending: "bg-amber-500/15 text-amber-500",
  }
  const label = proof ? proof.charAt(0).toUpperCase() + proof.slice(1) : "Pending"
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-sm font-medium ${styles[proof] || styles.pending}`}>
      {label}
    </span>
  )
}

function truncateAddress(addr) {
  if (!addr) return "—"
  return `${addr.slice(0, 6)}...${addr.slice(-4)}`
}

function TradesTableSkeleton() {
  return (
    <Card className="border-border/50">
      <CardContent className="p-0 overflow-x-auto">
        <div className="p-4 space-y-4">
          <Skeleton className="h-10 w-full rounded-lg" />
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="flex items-center gap-4 py-2">
              <Skeleton className="h-4 w-[80px]" />
              <Skeleton className="h-4 w-[100px]" />
              <Skeleton className="ml-auto h-4 w-[70px]" />
              <Skeleton className="h-4 w-[50px]" />
              <Skeleton className="h-5 w-[80px] rounded-full" />
              <Skeleton className="h-5 w-[80px] rounded-full" />
              <Skeleton className="h-8 w-[70px] rounded-md" />
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  )
}

export default function Trades() {
  const { isAuthenticated, merchant } = useWallet()
  const [activeFilter, setActiveFilter] = useState("All")
  const [search, setSearch] = useState("")
  const [trades, setTrades] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(false)
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0, per_page: 15 })


  const fetchTrades = async (page = 1) => {
    if (!isAuthenticated) return
    setLoading(true)
    setError(false)
    try {
      const params = new URLSearchParams()
      params.set("page", page)
      params.set("per_page", "15")
      params.set("role", "all")
      if (activeFilter === "cancelled/expired") {
        params.set("status", "cancelled,expired")
      } else if (activeFilter !== "All") {
        params.set("status", activeFilter)
      }
      if (search.trim()) {
        params.set("search", search.trim())
      }
      const res = await api.getMerchantTrades(params.toString())
      const data = res.data
      if (data?.data) {
        setTrades(data.data)
        setPagination({
          current_page: data.current_page || 1,
          last_page: data.last_page || 1,
          total: data.total || data.data.length,
          per_page: data.per_page || 15,
        })
      } else if (Array.isArray(data)) {
        setTrades(data)
        setPagination({ current_page: 1, last_page: 1, total: data.length, per_page: 15 })
      } else {
        setTrades([])
      }
    } catch (err) {
      toast.error("Failed to load trades")
      setError(true)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchTrades(1)
  }, [isAuthenticated, activeFilter])

  // Debounced search
  useEffect(() => {
    const timeout = setTimeout(() => {
      if (isAuthenticated) fetchTrades(1)
    }, 500)
    return () => clearTimeout(timeout)
  }, [search])

  if (error) {
    return (
      <div className="flex flex-col items-center justify-center py-20 text-center">
        <ArrowsLeftRight className="size-16 text-muted-foreground/20 mb-4" weight="duotone" />
        <p className="text-lg font-semibold mb-2">Failed to load trades</p>
        <p className="text-sm text-muted-foreground mb-6">Something went wrong. Please try again.</p>
        <Button onClick={() => { setError(false); fetchTrades(1) }}>
          Retry
        </Button>
      </div>
    )
  }

  const from = trades.length > 0 ? (pagination.current_page - 1) * pagination.per_page + 1 : 0
  const to = from + trades.length - 1

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h2 className="text-2xl font-bold tracking-tight">Trade History</h2>
        <p className="text-sm text-muted-foreground">View and manage all your P2P transactions</p>
      </div>

      {/* Filter bar */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-2 overflow-x-auto pb-2">
          {filters.map((filter) => (
            <Button
              key={filter}
              variant="ghost"
              size="sm"
              className={activeFilter === filter ? "bg-primary/10 text-primary" : ""}
              onClick={() => setActiveFilter(filter)}
            >
              {filterLabels[filter]}
            </Button>
          ))}
        </div>
        <div className="relative">
          <MagnifyingGlass className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            placeholder="Search by ID or wallet..."
            className="w-full sm:w-64 pl-9"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
      </div>

      {/* Table */}
      {loading ? (
        <TradesTableSkeleton />
      ) : trades.length === 0 ? (
        <Card className="border-border/50">
          <CardContent className="py-16">
            <div className="flex flex-col items-center justify-center text-center">
              <ArrowsLeftRight className="size-12 text-muted-foreground/30 mb-3" weight="duotone" />
              <p className="text-muted-foreground font-medium">No trades found</p>
              <p className="text-sm text-muted-foreground/60">
                {activeFilter !== "All" ? "Try changing the filter or search criteria" : "Your trade history will appear here"}
              </p>
            </div>
          </CardContent>
        </Card>
      ) : (
        <Card className="border-border/50">
          <CardContent className="p-0 overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow className="border-b border-border/50 bg-muted/30 hover:bg-muted/30">
                  <TableHead className="text-sm font-medium uppercase tracking-wider text-muted-foreground">Trade ID</TableHead>
                  <TableHead className="text-sm font-medium uppercase tracking-wider text-muted-foreground">Role</TableHead>
                  <TableHead className="text-sm font-medium uppercase tracking-wider text-muted-foreground">Counterparty</TableHead>
                  <TableHead className="text-right text-sm font-medium uppercase tracking-wider text-muted-foreground">Amount</TableHead>
                  <TableHead className="text-sm font-medium uppercase tracking-wider text-muted-foreground">Currency</TableHead>
                  <TableHead className="text-sm font-medium uppercase tracking-wider text-muted-foreground">Proof</TableHead>
                  <TableHead className="text-sm font-medium uppercase tracking-wider text-muted-foreground">Status</TableHead>
                  <TableHead className="text-right text-sm font-medium uppercase tracking-wider text-muted-foreground">Action</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {trades.map((trade) => {
                  const isBuyer = trade.buyer_wallet?.toLowerCase() === merchant?.wallet_address?.toLowerCase()
                  const isMerchant = trade.merchant_id === merchant?.id
                  const role = isMerchant ? "Seller" : "Buyer"
                  const counterparty = isMerchant ? trade.buyer_wallet : (trade.merchant?.username || trade.merchant?.wallet_address || "—")
                  const isCashMeeting = ["cash_meeting", "cash meeting"].includes((trade.payment_method || "").toLowerCase())
                  const buyerPage = isCashMeeting ? "meeting" : "confirm"
                  const viewUrl = isMerchant ? `/trade/${trade.trade_hash}/release` : `/trade/${trade.trade_hash}/${buyerPage}`
                  return (
                  <TableRow key={trade.id || trade.trade_hash} className="border-b border-border/30 transition-colors hover:bg-muted/20">
                    <TableCell className="font-mono text-sm font-semibold">{truncateAddress(trade.trade_hash)}</TableCell>
                    <TableCell>
                      <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                        isMerchant ? "bg-blue-500/15 text-blue-400" : "bg-purple-500/15 text-purple-400"
                      }`}>{role}</span>
                    </TableCell>
                    <TableCell className="font-mono text-sm text-muted-foreground">{typeof counterparty === "string" && counterparty.startsWith("0x") ? truncateAddress(counterparty) : counterparty}</TableCell>
                    <TableCell className="text-right font-mono text-sm font-semibold">${Number(trade.amount_usdc || 0).toLocaleString()}</TableCell>
                    <TableCell className="text-sm">{trade.currency_code || trade.currency || "—"}</TableCell>
                    <TableCell>
                      <div className="flex gap-1.5">
                        {trade.bank_proof_path ? (
                          <span className="inline-flex items-center rounded-full bg-green-500/15 px-2 py-0.5 text-xs font-medium text-green-400">Bank</span>
                        ) : (
                          <span className="inline-flex items-center rounded-full bg-muted/30 px-2 py-0.5 text-xs font-medium text-muted-foreground">Bank</span>
                        )}
                        {trade.buyer_id_path ? (
                          <span className="inline-flex items-center rounded-full bg-blue-500/15 px-2 py-0.5 text-xs font-medium text-blue-400">ID</span>
                        ) : (
                          <span className="inline-flex items-center rounded-full bg-muted/30 px-2 py-0.5 text-xs font-medium text-muted-foreground">ID</span>
                        )}
                      </div>
                    </TableCell>
                    <TableCell>{statusBadge(trade.status)}</TableCell>
                    <TableCell className="text-right">
                      <Button variant="outline" size="sm" className="gap-1.5" onClick={() => router.visit(viewUrl)}>
                        <Eye className="size-4" />
                        View
                      </Button>
                    </TableCell>
                  </TableRow>
                  )
                })}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}

      {/* Pagination */}
      {!loading && trades.length > 0 && (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-sm text-muted-foreground">
            Showing <span className="font-medium text-foreground">{from}-{to}</span> of <span className="font-medium text-foreground">{pagination.total}</span> trades
          </p>
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              className="gap-1.5"
              disabled={pagination.current_page <= 1}
              onClick={() => fetchTrades(pagination.current_page - 1)}
            >
              <CaretLeft className="size-4" weight="bold" />
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              className="gap-1.5"
              disabled={pagination.current_page >= pagination.last_page}
              onClick={() => fetchTrades(pagination.current_page + 1)}
            >
              Next
              <CaretRight className="size-4" weight="bold" />
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}

Trades.layout = (page) => <DashboardLayout>{page}</DashboardLayout>
