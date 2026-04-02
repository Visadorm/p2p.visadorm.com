import { useState, useEffect } from "react"
import DashboardLayout from "@/layouts/DashboardLayout"
import { api } from "@/lib/api"
import { useWallet } from "@/hooks/useWallet"
import { toast } from "sonner"
import { NotePencil } from "@phosphor-icons/react"
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Textarea } from "@/components/ui/textarea"
import { Label } from "@/components/ui/label"
import { Skeleton } from "@/components/ui/skeleton"

export default function Instructions() {
  const { isAuthenticated, merchant, refreshMerchant } = useWallet()
  const [instructions, setInstructions] = useState("")
  const [saving, setSaving] = useState(false)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (merchant) {
      setInstructions(merchant.trade_instructions || "")
      setLoading(false)
    }
  }, [merchant])

  const handleSave = async () => {
    setSaving(true)
    try {
      await api.updateProfile({ trade_instructions: instructions })
      await refreshMerchant()
      toast.success("Trade instructions saved")
    } catch (err) {
      toast.error(err.message || "Failed to save instructions")
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-8 w-60" />
        <Skeleton className="h-64 w-full rounded-xl" />
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-xl font-bold tracking-tight">Trade Instructions</h2>
        <p className="mt-1 text-sm text-muted-foreground">
          Set instructions that buyers see before and during a trade
        </p>
      </div>

      <Card className="border-border/50">
        <CardHeader>
          <div className="flex items-center gap-3">
            <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10">
              <NotePencil className="size-5 text-primary" weight="duotone" />
            </div>
            <div>
              <CardTitle>Instructions</CardTitle>
              <CardDescription>These appear on your trading link page and during active trades</CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>Trade Instructions</Label>
              <Textarea
                value={instructions}
                onChange={(e) => setInstructions(e.target.value)}
                placeholder="e.g., Please include your trade reference in the bank transfer description. Payment must be sent within 30 minutes. Contact me on Telegram @username if you have questions."
                rows={8}
                maxLength={2000}
              />
              <div className="flex items-center justify-between">
                <p className="text-sm text-muted-foreground">Shown to buyers on your trade page</p>
                <span className="text-sm text-muted-foreground">{instructions.length}/2000</span>
              </div>
            </div>

            <div className="flex justify-end">
              <Button onClick={handleSave} disabled={saving} className="gap-2">
                <NotePencil className="size-4" weight="bold" />
                {saving ? "Saving..." : "Save Instructions"}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

Instructions.layout = (page) => <DashboardLayout>{page}</DashboardLayout>
