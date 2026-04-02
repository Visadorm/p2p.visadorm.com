import { useState, useEffect, useMemo } from "react"
import { router } from "@inertiajs/react"
import { toast } from "sonner"
import DashboardLayout from "@/layouts/DashboardLayout"
import { api } from "@/lib/api"
import { useWallet } from "@/hooks/useWallet"
import {
  Plus,
  CurrencyDollar,
  Trash,
  TrendUp,
  ChartLineUp,
  MagnifyingGlass,
  Check,
} from "@phosphor-icons/react"
import { Card, CardContent } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Separator } from "@/components/ui/separator"
import { Skeleton } from "@/components/ui/skeleton"
import { ScrollArea } from "@/components/ui/scroll-area"
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from "@/components/ui/dialog"

// Use browser's Intl API for currency names — no hardcoding needed
const currencyNameFormatter = new Intl.DisplayNames(["en"], { type: "currency" })
function getCurrencyName(code) {
  try {
    return currencyNameFormatter.of(code)
  } catch {
    return code
  }
}

function CurrencySkeleton() {
  return (
    <div className="space-y-4">
      {Array.from({ length: 3 }).map((_, i) => (
        <Card key={i} className="border-border/50">
          <CardContent className="space-y-5 pt-6">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <Skeleton className="size-11 rounded-xl" />
                <div className="space-y-2">
                  <Skeleton className="h-5 w-[140px]" />
                  <Skeleton className="h-3 w-[100px]" />
                </div>
              </div>
              <Skeleton className="size-8 rounded-md" />
            </div>
            <Skeleton className="h-px w-full" />
            <div className="grid grid-cols-2 gap-4">
              <Skeleton className="h-10 w-full rounded-md" />
              <Skeleton className="h-10 w-full rounded-md" />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <Skeleton className="h-10 w-full rounded-md" />
              <Skeleton className="h-10 w-full rounded-md" />
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  )
}

export default function CurrencyMarkup() {
  const { isAuthenticated } = useWallet()
  const [currencies, setCurrencies] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(false)
  const [saving, setSaving] = useState(false)
  const [deletingId, setDeletingId] = useState(null)
  const [showAddDialog, setShowAddDialog] = useState(false)
  const [addingCurrency, setAddingCurrency] = useState(false)
  const [newCurrency, setNewCurrency] = useState({
    currency_code: "",
    markup_percent: "2.0",
    min_amount: "10",
    max_amount: "5000",
  })

  // Available currencies from API
  const [availableRates, setAvailableRates] = useState({})
  const [loadingRates, setLoadingRates] = useState(false)
  const [currencySearch, setCurrencySearch] = useState("")

  const fetchCurrencies = async () => {
    if (!isAuthenticated) return
    setLoading(true)
    setError(false)
    try {
      const res = await api.getCurrencies()
      const data = res.data || []
      setCurrencies(Array.isArray(data) ? data : [])
    } catch (err) {
      toast.error("Failed to load currencies")
      setError(true)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchCurrencies()
  }, [isAuthenticated])

  // Fetch available currencies when dialog opens
  useEffect(() => {
    if (!showAddDialog) return
    setLoadingRates(true)
    setCurrencySearch("")
    setNewCurrency({ currency_code: "", markup_percent: "2.0", min_amount: "10", max_amount: "5000" })
    api.getExchangeRates()
      .then((res) => setAvailableRates(res.data || {}))
      .catch(() => toast.error("Failed to load available currencies"))
      .finally(() => setLoadingRates(false))
  }, [showAddDialog])

  // Build filtered + sorted currency list for the picker
  const existingCodes = useMemo(
    () => new Set(currencies.map((c) => c.currency_code.toUpperCase())),
    [currencies]
  )

  const filteredCurrencies = useMemo(() => {
    const entries = Object.entries(availableRates)
      .filter(([code]) => !existingCodes.has(code)) // exclude already added
      .map(([code, rate]) => ({
        code,
        name: getCurrencyName(code),
        rate,
      }))
      .sort((a, b) => a.name.localeCompare(b.name))

    if (!currencySearch.trim()) return entries

    const q = currencySearch.trim().toLowerCase()
    return entries.filter(
      (c) => c.code.toLowerCase().includes(q) || c.name.toLowerCase().includes(q)
    )
  }, [availableRates, existingCodes, currencySearch])

  const updateField = (id, field, value) => {
    setCurrencies((prev) =>
      prev.map((c) => (c.id === id ? { ...c, [field]: value } : c))
    )
  }

  const handleSaveAll = async () => {
    setSaving(true)
    try {
      await Promise.all(
        currencies.map((curr) =>
          api.updateCurrency(curr.id, {
            markup_percent: parseFloat(curr.markup_percent) || 0,
            min_amount: parseFloat(curr.min_amount || curr.min) || 0,
            max_amount: parseFloat(curr.max_amount || curr.max) || 0,
          })
        )
      )
      toast.success("Currency settings saved")
    } catch (err) {
      toast.error(err.message || "Failed to save currency settings")
    } finally {
      setSaving(false)
    }
  }

  const removeCurrency = async (id) => {
    if (!confirm("Are you sure you want to remove this currency?")) return
    setDeletingId(id)
    try {
      await api.deleteCurrency(id)
      setCurrencies((prev) => prev.filter((c) => c.id !== id))
      toast.success("Currency removed")
    } catch (err) {
      toast.error(err.message || "Failed to remove currency")
    } finally {
      setDeletingId(null)
    }
  }

  const handleAddCurrency = async (e) => {
    e.preventDefault()
    if (!newCurrency.currency_code) {
      toast.error("Select a currency first")
      return
    }
    setAddingCurrency(true)
    try {
      await api.createCurrency({
        currency_code: newCurrency.currency_code.toUpperCase(),
        markup_percent: parseFloat(newCurrency.markup_percent) || 0,
        min_amount: parseFloat(newCurrency.min_amount) || 0,
        max_amount: parseFloat(newCurrency.max_amount) || 0,
      })
      toast.success("Currency added")
      setShowAddDialog(false)
      fetchCurrencies()
    } catch (err) {
      toast.error(err.message || "Failed to add currency")
    } finally {
      setAddingCurrency(false)
    }
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div>
          <h2 className="text-xl font-bold tracking-tight">Currency & Markup</h2>
          <p className="mt-1 text-sm text-muted-foreground">Configure your accepted currencies, markup percentages, and trade limits</p>
        </div>
        <div className="flex flex-col items-center justify-center py-20 text-center">
          <CurrencyDollar className="size-16 text-muted-foreground/20 mb-4" weight="duotone" />
          <p className="text-lg font-semibold mb-2">Failed to load currencies</p>
          <p className="text-sm text-muted-foreground mb-6">Something went wrong. Please try again.</p>
          <Button onClick={() => fetchCurrencies()}>Retry</Button>
        </div>
      </div>
    )
  }

  const selectedCurrencyName = newCurrency.currency_code
    ? getCurrencyName(newCurrency.currency_code)
    : null
  const selectedCurrencyRate = newCurrency.currency_code
    ? availableRates[newCurrency.currency_code] || 0
    : 0

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-xl font-bold tracking-tight">Currency & Markup</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Configure your accepted currencies, markup percentages, and trade limits
          </p>
        </div>
        <Button className="gap-2 w-full sm:w-auto" onClick={() => setShowAddDialog(true)}>
          <Plus weight="bold" size={16} />
          Add Currency
        </Button>
      </div>

      {/* Currency Cards */}
      {loading ? (
        <CurrencySkeleton />
      ) : currencies.length === 0 ? (
        <Card className="border-border/50">
          <CardContent className="py-16">
            <div className="flex flex-col items-center justify-center text-center">
              <CurrencyDollar className="size-12 text-muted-foreground/30 mb-3" weight="duotone" />
              <p className="text-muted-foreground font-medium">No currencies configured</p>
              <p className="text-sm text-muted-foreground/60 mb-4">Add your first currency to start trading</p>
              <Button className="gap-2" onClick={() => setShowAddDialog(true)}>
                <Plus weight="bold" size={16} />
                Add Currency
              </Button>
            </div>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-4">
          {currencies.map((curr) => {
            const marketRate = parseFloat(curr.market_rate || curr.marketRate) || 0
            const markup = parseFloat(curr.markup_percent) || 0
            const effectiveRate = marketRate * (1 + markup / 100)
            const min = curr.min_amount || curr.min || 0
            const max = curr.max_amount || curr.max || 0
            return (
              <Card key={curr.id} className="border-border/50">
                <CardContent className="space-y-5 pt-6">
                  {/* Currency Header */}
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <div className="flex size-11 items-center justify-center rounded-xl bg-primary/15">
                        <CurrencyDollar weight="duotone" size={22} className="text-primary" />
                      </div>
                      <div>
                        <div className="flex items-center gap-2">
                          <h3 className="text-base font-bold">{curr.currency_code}</h3>
                          <Badge variant="secondary" className="font-mono">
                            {getCurrencyName(curr.currency_code)}
                          </Badge>
                        </div>
                        <div className="mt-0.5 flex items-center gap-2 text-sm text-muted-foreground">
                          <ChartLineUp weight="duotone" size={14} />
                          Market Rate:{" "}
                          <span className="font-mono font-medium text-foreground">
                            {marketRate.toLocaleString()}
                          </span>
                        </div>
                      </div>
                    </div>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="text-red-400 hover:bg-red-500/10 hover:text-red-400"
                      disabled={deletingId === curr.id}
                      onClick={() => removeCurrency(curr.id)}
                    >
                      <Trash weight="duotone" size={16} />
                    </Button>
                  </div>

                  <Separator />

                  {/* Markup + Effective Rate */}
                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                      <Label>Markup %</Label>
                      <div className="relative">
                        <Input
                          type="number"
                          step="0.1"
                          value={markup}
                          onChange={(e) =>
                            updateField(curr.id, "markup_percent", parseFloat(e.target.value) || 0)
                          }
                          className="pr-8"
                        />
                        <span className="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">
                          %
                        </span>
                      </div>
                    </div>
                    <div className="space-y-2">
                      <Label>Effective Rate</Label>
                      <div className="flex items-center gap-2 rounded-lg border border-emerald-500/20 bg-emerald-500/5 px-4 py-2.5">
                        <TrendUp weight="bold" size={16} className="text-emerald-400" />
                        <span className="font-mono text-base font-bold text-emerald-400">
                          {effectiveRate.toFixed(2)} {curr.currency_code}
                        </span>
                        <span className="text-sm text-muted-foreground">per USDC</span>
                      </div>
                    </div>
                  </div>

                  {/* Min/Max */}
                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                      <Label>Min Amount (USDC)</Label>
                      <div className="relative">
                        <Input
                          type="number"
                          value={min}
                          onChange={(e) =>
                            updateField(curr.id, "min_amount", parseFloat(e.target.value) || 0)
                          }
                          className="pl-8"
                        />
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">
                          $
                        </span>
                      </div>
                    </div>
                    <div className="space-y-2">
                      <Label>Max Amount (USDC)</Label>
                      <div className="relative">
                        <Input
                          type="number"
                          value={max}
                          onChange={(e) =>
                            updateField(curr.id, "max_amount", parseFloat(e.target.value) || 0)
                          }
                          className="pl-8"
                        />
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">
                          $
                        </span>
                      </div>
                    </div>
                  </div>
                </CardContent>
              </Card>
            )
          })}
        </div>
      )}

      {/* Save Button */}
      {currencies.length > 0 && (
        <div className="flex justify-end">
          <Button size="lg" className="gap-2 px-8" onClick={handleSaveAll} disabled={saving}>
            {saving ? "Saving..." : "Save Settings"}
          </Button>
        </div>
      )}

      {/* Add Currency Dialog */}
      <Dialog open={showAddDialog} onOpenChange={setShowAddDialog}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Add Currency</DialogTitle>
            <DialogDescription>Select a currency and configure your trade settings</DialogDescription>
          </DialogHeader>
          <form onSubmit={handleAddCurrency} className="space-y-4">
            {/* Currency Picker */}
            {!newCurrency.currency_code ? (
              <div className="space-y-3">
                <div className="relative">
                  <MagnifyingGlass className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" size={16} />
                  <Input
                    value={currencySearch}
                    onChange={(e) => setCurrencySearch(e.target.value)}
                    placeholder="Search currency..."
                    className="pl-9"
                    autoFocus
                  />
                </div>
                <ScrollArea className="h-[280px] rounded-lg border border-border/50">
                  {loadingRates ? (
                    <div className="space-y-1 p-1">
                      {Array.from({ length: 6 }).map((_, i) => (
                        <Skeleton key={i} className="h-12 w-full rounded-md" />
                      ))}
                    </div>
                  ) : filteredCurrencies.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-10 text-center">
                      <p className="text-sm text-muted-foreground">No currencies found</p>
                    </div>
                  ) : (
                    <div className="p-1">
                      {filteredCurrencies.map((c) => (
                        <button
                          key={c.code}
                          type="button"
                          onClick={() => setNewCurrency((prev) => ({ ...prev, currency_code: c.code }))}
                          className="flex w-full items-center justify-between rounded-md px-3 py-2.5 text-left transition-colors hover:bg-muted/50"
                        >
                          <div className="flex items-center gap-3">
                            <span className="font-mono text-sm font-bold">{c.code}</span>
                            <span className="text-sm text-muted-foreground">{c.name}</span>
                          </div>
                          <span className="font-mono text-sm text-muted-foreground">
                            {c.rate.toLocaleString()}
                          </span>
                        </button>
                      ))}
                    </div>
                  )}
                </ScrollArea>
              </div>
            ) : (
              <>
                {/* Selected currency display */}
                <div
                  className="flex items-center justify-between rounded-lg border border-primary/30 bg-primary/5 px-4 py-3 cursor-pointer"
                  onClick={() => setNewCurrency((prev) => ({ ...prev, currency_code: "" }))}
                >
                  <div className="flex items-center gap-3">
                    <div className="flex size-9 items-center justify-center rounded-lg bg-primary/15">
                      <Check weight="bold" size={16} className="text-primary" />
                    </div>
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="font-mono text-sm font-bold">{newCurrency.currency_code}</span>
                        <span className="text-sm">{selectedCurrencyName}</span>
                      </div>
                      <span className="text-xs text-muted-foreground">
                        1 USDC = {selectedCurrencyRate.toLocaleString()} {newCurrency.currency_code} · Click to change
                      </span>
                    </div>
                  </div>
                </div>

                {/* Settings */}
                <div className="grid grid-cols-3 gap-3">
                  <div className="space-y-2">
                    <Label>Markup %</Label>
                    <Input
                      type="number"
                      step="0.1"
                      value={newCurrency.markup_percent}
                      onChange={(e) => setNewCurrency((prev) => ({ ...prev, markup_percent: e.target.value }))}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label>Min (USDC)</Label>
                    <Input
                      type="number"
                      value={newCurrency.min_amount}
                      onChange={(e) => setNewCurrency((prev) => ({ ...prev, min_amount: e.target.value }))}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label>Max (USDC)</Label>
                    <Input
                      type="number"
                      value={newCurrency.max_amount}
                      onChange={(e) => setNewCurrency((prev) => ({ ...prev, max_amount: e.target.value }))}
                    />
                  </div>
                </div>

                <DialogFooter>
                  <Button type="button" variant="outline" onClick={() => setShowAddDialog(false)}>
                    Cancel
                  </Button>
                  <Button type="submit" disabled={addingCurrency}>
                    {addingCurrency ? "Adding..." : "Add Currency"}
                  </Button>
                </DialogFooter>
              </>
            )}
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}

CurrencyMarkup.layout = (page) => <DashboardLayout>{page}</DashboardLayout>
