import { useState, useEffect } from "react"
import DashboardLayout from "@/layouts/DashboardLayout"
import { api } from "@/lib/api"
import { useWallet } from "@/hooks/useWallet"
import { toast } from "sonner"
import { ShieldCheck, Bell } from "@phosphor-icons/react"
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Switch } from "@/components/ui/switch"
import { Label } from "@/components/ui/label"
import { Separator } from "@/components/ui/separator"
import { Skeleton } from "@/components/ui/skeleton"

const verificationLevels = [
  { id: "disabled", label: "Disabled", description: "No verification required. Any buyer can trade with you." },
  { id: "optional", label: "Optional", description: "Buyers are encouraged to verify but it is not required." },
  { id: "required", label: "Required", description: "Buyers must verify their identity before initiating a trade." },
]

export default function BuyerVerification() {
  const { isAuthenticated, merchant, refreshMerchant } = useWallet()
  const [verifyLevel, setVerifyLevel] = useState("optional")
  const [notifyBankProof, setNotifyBankProof] = useState(true)
  const [notifyBuyerId, setNotifyBuyerId] = useState(true)
  const [saving, setSaving] = useState(false)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (merchant) {
      setVerifyLevel(merchant.buyer_verification || "optional")
      setNotifyBankProof(merchant.notify_bank_proof !== false)
      setNotifyBuyerId(merchant.notify_buyer_id !== false)
      setLoading(false)
    }
  }, [merchant])

  const handleSave = async () => {
    setSaving(true)
    try {
      await api.updateProfile({
        buyer_verification: verifyLevel,
        notify_bank_proof: notifyBankProof,
        notify_buyer_id: notifyBuyerId,
      })
      await refreshMerchant()
      toast.success("Verification settings saved")
    } catch (err) {
      toast.error(err.message || "Failed to save settings")
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-8 w-60" />
        <Skeleton className="h-64 w-full rounded-xl" />
        <Skeleton className="h-48 w-full rounded-xl" />
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-xl font-bold tracking-tight">Buyer Verification</h2>
        <p className="mt-1 text-sm text-muted-foreground">
          Set verification requirements for buyers who trade with you
        </p>
      </div>

      {/* Verification Level */}
      <Card className="border-border/50">
        <CardHeader>
          <div className="flex items-center gap-3">
            <div className="flex size-10 items-center justify-center rounded-xl bg-blue-500/10">
              <ShieldCheck className="size-5 text-blue-500" weight="duotone" />
            </div>
            <div>
              <CardTitle>Verification Requirement</CardTitle>
              <CardDescription>Choose how strictly buyers must verify their identity</CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            {verificationLevels.map((option) => (
              <div
                key={option.id}
                className={`cursor-pointer rounded-xl border p-4 transition-colors ${
                  verifyLevel === option.id
                    ? "border-primary bg-primary/5"
                    : "border-border/50 hover:border-muted-foreground/30"
                }`}
                onClick={() => setVerifyLevel(option.id)}
              >
                <div className="flex items-center gap-3">
                  <div
                    className={`flex size-5 items-center justify-center rounded-full border-2 ${
                      verifyLevel === option.id ? "border-primary" : "border-muted-foreground/40"
                    }`}
                  >
                    {verifyLevel === option.id && <div className="size-2.5 rounded-full bg-primary" />}
                  </div>
                  <div>
                    <span className="text-sm font-semibold">{option.label}</span>
                    <p className="text-sm text-muted-foreground">{option.description}</p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Notification Preferences */}
      <Card className="border-border/50">
        <CardHeader>
          <div className="flex items-center gap-3">
            <div className="flex size-10 items-center justify-center rounded-xl bg-amber-500/10">
              <Bell className="size-5 text-amber-500" weight="duotone" />
            </div>
            <div>
              <CardTitle>Verification Notifications</CardTitle>
              <CardDescription>Get notified when buyers submit verification documents</CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <Label className="flex-1">
                <span>Bank proof uploaded</span>
                <p className="text-sm text-muted-foreground font-normal">Alert when buyer submits payment proof</p>
              </Label>
              <Switch checked={notifyBankProof} onCheckedChange={setNotifyBankProof} />
            </div>
            <Separator className="opacity-30" />
            <div className="flex items-center justify-between">
              <Label className="flex-1">
                <span>Buyer ID uploaded</span>
                <p className="text-sm text-muted-foreground font-normal">Get notified when a buyer uploads their ID</p>
              </Label>
              <Switch checked={notifyBuyerId} onCheckedChange={setNotifyBuyerId} />
            </div>
          </div>
        </CardContent>
      </Card>

      <div className="flex justify-end">
        <Button onClick={handleSave} disabled={saving} className="gap-2">
          <ShieldCheck className="size-4" weight="bold" />
          {saving ? "Saving..." : "Save Settings"}
        </Button>
      </div>
    </div>
  )
}

BuyerVerification.layout = (page) => <DashboardLayout>{page}</DashboardLayout>
