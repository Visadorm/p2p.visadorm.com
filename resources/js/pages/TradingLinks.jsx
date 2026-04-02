import { useState, useEffect } from "react"
import { router } from "@inertiajs/react"
import { toast } from "sonner"
import { QRCodeSVG } from "qrcode.react"
import DashboardLayout from "@/layouts/DashboardLayout"
import { api } from "@/lib/api"
import { useWallet } from "@/hooks/useWallet"
import { Copy, Trash, Plus, Globe, LockSimple, ListChecks, LinkBreak } from "@phosphor-icons/react"
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Separator } from "@/components/ui/separator"
import { Skeleton } from "@/components/ui/skeleton"
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from "@/components/ui/dialog"

function TradingLinksSkeleton() {
  return (
    <div className="space-y-6">
      <Card className="border-border/50">
        <CardHeader>
          <div className="flex items-center gap-3">
            <Skeleton className="size-10 rounded-xl" />
            <div className="space-y-2">
              <Skeleton className="h-5 w-[160px]" />
              <Skeleton className="h-3 w-[260px]" />
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="flex gap-3">
            <Skeleton className="h-10 flex-1 rounded-md" />
            <Skeleton className="h-10 w-[80px] rounded-md" />
          </div>
        </CardContent>
      </Card>
      <Card className="border-border/50">
        <CardHeader>
          <Skeleton className="h-5 w-[180px]" />
        </CardHeader>
        <CardContent className="space-y-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-16 w-full rounded-xl" />
          ))}
        </CardContent>
      </Card>
    </div>
  )
}

export default function TradingLinks() {
  const { isAuthenticated, merchant } = useWallet()
  const [links, setLinks] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(false)
  const [instructions, setInstructions] = useState("")
  const [savingInstructions, setSavingInstructions] = useState(false)
  const [showCreateDialog, setShowCreateDialog] = useState(false)
  const [creating, setCreating] = useState(false)
  const [deletingId, setDeletingId] = useState(null)
  const [newLinkLabel, setNewLinkLabel] = useState("")
  const maxChars = 1000


  const fetchLinks = async () => {
    setLoading(true)
    setError(false)
    try {
      const res = await api.getTradingLinks()
      const rawData = res.data?.data || res.data || []
      setLinks(Array.isArray(rawData) ? rawData : [])
    } catch (err) {
      toast.error("Failed to load trading links")
      setError(true)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    if (isAuthenticated) fetchLinks()
  }, [isAuthenticated])

  useEffect(() => {
    if (merchant?.trade_instructions) {
      setInstructions(merchant.trade_instructions)
    }
  }, [merchant])

  const publicLink = merchant?.username
    ? `${window.location.origin}/merchant/${merchant.username}`
    : ""

  const handleCopyLink = (url) => {
    navigator.clipboard.writeText(url)
    toast.success("Link copied to clipboard")
  }

  const handleCreateLink = async (e) => {
    e.preventDefault()
    setCreating(true)
    try {
      await api.createTradingLink({ label: newLinkLabel || "Private Link", type: "private" })
      toast.success("Trading link created")
      setShowCreateDialog(false)
      setNewLinkLabel("")
      fetchLinks()
    } catch (err) {
      toast.error(err.message || "Failed to create link")
    } finally {
      setCreating(false)
    }
  }

  const handleDeleteLink = async (id) => {
    if (!confirm("Are you sure you want to delete this trading link?")) return
    setDeletingId(id)
    try {
      await api.deleteTradingLink(id)
      setLinks((prev) => prev.filter((l) => l.id !== id))
      toast.success("Trading link deleted")
    } catch (err) {
      toast.error(err.message || "Failed to delete link")
    } finally {
      setDeletingId(null)
    }
  }

  const handleSaveInstructions = async () => {
    setSavingInstructions(true)
    try {
      await api.updateProfile({ trade_instructions: instructions })
      toast.success("Instructions saved")
    } catch (err) {
      toast.error(err.message || "Failed to save instructions")
    } finally {
      setSavingInstructions(false)
    }
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div className="flex flex-col items-center justify-center py-20 text-center">
          <LinkBreak className="size-16 text-muted-foreground/20 mb-4" weight="duotone" />
          <p className="text-lg font-semibold mb-2">Failed to load trading links</p>
          <p className="text-sm text-muted-foreground mb-6">Something went wrong. Please try again.</p>
          <Button onClick={() => fetchLinks()}>Retry</Button>
        </div>
      </div>
    )
  }

  if (loading) {
    return <TradingLinksSkeleton />
  }

  // Separate public and private links
  const privateLinks = links.filter((l) => l.type === "private" || l.is_private)
  const publicLinks = links.filter((l) => l.type === "public" || !l.is_private)

  return (
    <div className="space-y-6">
      {/* Public Link */}
      <Card className="border-border/50">
        <CardHeader>
          <div className="flex items-center gap-3">
            <div className="flex size-10 items-center justify-center rounded-xl bg-emerald-500/10">
              <Globe className="size-5 text-emerald-500" weight="duotone" />
            </div>
            <div>
              <CardTitle>Public Trading Link</CardTitle>
              <CardDescription>Share this link publicly. Anyone can initiate a trade with you.</CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="flex items-center gap-3">
            <Input
              readOnly
              value={publicLink}
              className="flex-1 font-mono"
            />
            <Button variant="outline" className="gap-2" onClick={() => handleCopyLink(publicLink)} disabled={!publicLink}>
              <Copy className="size-4" />
              Copy
            </Button>
          </div>
          {publicLink && (
            <div className="mt-4 flex justify-center">
              <div className="rounded-xl border border-border/50 bg-white p-4">
                <QRCodeSVG value={publicLink} size={160} level="M" />
              </div>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Private Links */}
      <Card className="border-border/50">
        <CardHeader>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-3">
              <div className="flex size-10 items-center justify-center rounded-xl bg-purple-500/10">
                <LockSimple className="size-5 text-purple-500" weight="duotone" />
              </div>
              <div>
                <CardTitle>Private Trading Links</CardTitle>
                <CardDescription>No stake required for buyers using private links.</CardDescription>
              </div>
            </div>
            <Button className="gap-2 w-full sm:w-auto" onClick={() => setShowCreateDialog(true)}>
              <Plus className="size-4" weight="bold" />
              Generate Link
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          {privateLinks.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-8 text-center">
              <LockSimple className="size-10 text-muted-foreground/30 mb-3" weight="duotone" />
              <p className="text-sm text-muted-foreground">No trading links yet</p>
              <p className="text-sm text-muted-foreground/60">Generate your first private trading link</p>
            </div>
          ) : (
            <div className="space-y-3">
              {privateLinks.map((link) => {
                const linkUrl = `${window.location.origin}/trade/${link.slug}/start`
                return (
                  <div key={link.id} className="flex flex-col gap-3 rounded-xl border border-border/50 p-4 transition-colors hover:bg-muted/20 sm:flex-row sm:items-center sm:gap-4">
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2 mb-1">
                        <span className="text-sm font-semibold">{link.label || link.name || "Trading Link"}</span>
                        <Badge variant="secondary" className="text-sm">{link.trades_count || 0} trades</Badge>
                        {link.is_private && (
                          <Badge variant="secondary" className="text-sm">Private</Badge>
                        )}
                      </div>
                      <span className="block truncate font-mono text-sm text-muted-foreground">{linkUrl}</span>
                    </div>
                    <div className="flex shrink-0 gap-2">
                      <Button variant="outline" size="sm" className="gap-1.5" onClick={() => handleCopyLink(linkUrl)}>
                        <Copy className="size-4" />
                        Copy
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        className="text-red-500 hover:text-red-400 hover:bg-red-500/10"
                        disabled={deletingId === link.id}
                        onClick={() => handleDeleteLink(link.id)}
                      >
                        <Trash className="size-4" />
                      </Button>
                    </div>
                  </div>
                )
              })}
            </div>
          )}
        </CardContent>
      </Card>

      <Separator />

      {/* Trade Instructions */}
      <Card className="border-border/50">
        <CardHeader>
          <div className="flex items-center gap-3">
            <div className="flex size-10 items-center justify-center rounded-xl bg-blue-500/10">
              <ListChecks className="size-5 text-blue-500" weight="duotone" />
            </div>
            <div>
              <CardTitle>Trade Instructions</CardTitle>
              <CardDescription>These instructions will be shown to buyers when they initiate a trade with you.</CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <Textarea
              value={instructions}
              onChange={(e) => setInstructions(e.target.value.slice(0, maxChars))}
              rows={8}
              placeholder="Enter your trade instructions..."
              className="text-sm"
            />
            <div className="flex items-center justify-between">
              <span className="text-sm text-muted-foreground">
                {instructions.length}/{maxChars} characters
              </span>
              <Button className="gap-2" onClick={handleSaveInstructions} disabled={savingInstructions}>
                <ListChecks className="size-4" weight="bold" />
                {savingInstructions ? "Saving..." : "Save Instructions"}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Create Link Dialog */}
      <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Generate Trading Link</DialogTitle>
            <DialogDescription>Create a new trading link for your buyers</DialogDescription>
          </DialogHeader>
          <form onSubmit={handleCreateLink} className="space-y-4">
            <div className="space-y-2">
              <Label>Link Label</Label>
              <Input
                value={newLinkLabel}
                onChange={(e) => setNewLinkLabel(e.target.value)}
                placeholder="e.g., VIP Client Link, Partner Link"
              />
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setShowCreateDialog(false)}>
                Cancel
              </Button>
              <Button type="submit" disabled={creating}>
                {creating ? "Generating..." : "Generate Link"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}

TradingLinks.layout = (page) => <DashboardLayout>{page}</DashboardLayout>
