import { useState, useEffect, useRef } from "react"
import { toast } from "sonner"
import DashboardLayout from "@/layouts/DashboardLayout"
import { api } from "@/lib/api"
import { useWallet } from "@/hooks/useWallet"
import {
  GearSix,
  Bell,
  Camera,
} from "@phosphor-icons/react"
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { Separator } from "@/components/ui/separator"
import { Skeleton } from "@/components/ui/skeleton"
import { Avatar, AvatarImage, AvatarFallback } from "@/components/ui/avatar"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"


function SettingsSkeleton() {
  return (
    <div className="space-y-6">
      {Array.from({ length: 3 }).map((_, i) => (
        <Card key={i} className="border-border/50">
          <CardHeader>
            <div className="flex items-center gap-3">
              <Skeleton className="size-10 rounded-xl" />
              <div className="space-y-2">
                <Skeleton className="h-5 w-[120px]" />
                <Skeleton className="h-3 w-[200px]" />
              </div>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            <Skeleton className="h-10 w-full rounded-md" />
            <Skeleton className="h-20 w-full rounded-md" />
            <Skeleton className="h-10 w-full rounded-md" />
          </CardContent>
        </Card>
      ))}
    </div>
  )
}

function truncateAddress(addr) {
  if (!addr) return "—"
  return `${addr.slice(0, 6)}...${addr.slice(-4)}`
}

export default function Settings() {
  const { isAuthenticated, merchant, refreshMerchant } = useWallet()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)

  // Avatar state
  const [avatarPreview, setAvatarPreview] = useState(null)
  const [avatarFile, setAvatarFile] = useState(null)
  const [uploadingAvatar, setUploadingAvatar] = useState(false)
  const avatarInputRef = useRef(null)

  // Form state
  const [username, setUsername] = useState("")
  const [bio, setBio] = useState("")
  const [email, setEmail] = useState("")
  const [phone, setPhone] = useState("")
  const [notifyEmail, setNotifyEmail] = useState(true)
  const [notifySms, setNotifySms] = useState(false)
  const [smsEnabled, setSmsEnabled] = useState(false)
  const [tradeTimerMinutes, setTradeTimerMinutes] = useState("30")


  useEffect(() => {
    if (!isAuthenticated) return
    api.getDashboard().then(res => {
      setSmsEnabled(res.data?.sms_enabled === true)
    }).catch(() => {})
    setLoading(false)
  }, [isAuthenticated])

  // Populate form when merchant data loads
  useEffect(() => {
    if (merchant) {
      setUsername(merchant.username || "")
      setBio(merchant.bio || "")
      setEmail(merchant.email || "")
      setPhone(merchant.phone || "")
      setTradeTimerMinutes(String(merchant.trade_timer_minutes || "30"))
      setNotifyEmail(merchant.notify_email !== false)
      setNotifySms(merchant.notify_sms === true)
    }
  }, [merchant])

  const handleSaveAll = async () => {
    setSaving(true)
    try {
      await api.updateProfile({
        bio,
        email,
        phone: phone || undefined,
        trade_timer_minutes: parseInt(tradeTimerMinutes) || 30,
        notify_email: notifyEmail,
        notify_sms: notifySms,
      })
      toast.success("Settings saved successfully")
      refreshMerchant()
    } catch (err) {
      toast.error(err.message || "Failed to save settings")
    } finally {
      setSaving(false)
    }
  }

  const handleAvatarChange = (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    setAvatarFile(file)
    setAvatarPreview(URL.createObjectURL(file))
  }

  const handleAvatarUpload = async () => {
    if (!avatarFile) return
    setUploadingAvatar(true)
    try {
      await api.uploadAvatar(avatarFile)
      toast.success("Profile picture updated")
      setAvatarFile(null)
      refreshMerchant()
    } catch (err) {
      toast.error(err.message || "Failed to upload profile picture")
    } finally {
      setUploadingAvatar(false)
    }
  }

  if (loading) {
    return <SettingsSkeleton />
  }

  return (
    <div className="space-y-6">
      {/* Profile */}
      <Card className="border-border/50">
        <CardHeader>
          <div className="flex items-center gap-3">
            <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10">
              <GearSix className="size-5 text-primary" weight="duotone" />
            </div>
            <div>
              <CardTitle>Profile</CardTitle>
              <CardDescription>Your merchant profile information</CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {/* Avatar */}
            <div className="flex items-center gap-4">
              <div className="relative">
                <Avatar className="size-20 border-2 border-border">
                  <AvatarImage src={avatarPreview || merchant?.avatar} alt={merchant?.username} />
                  <AvatarFallback className="bg-gradient-to-br from-primary to-blue-600 text-2xl font-bold text-white">
                    {merchant?.username?.charAt(0)?.toUpperCase()}
                  </AvatarFallback>
                </Avatar>
                <button
                  type="button"
                  onClick={() => avatarInputRef.current?.click()}
                  className="absolute -bottom-1 -right-1 flex size-7 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-md hover:bg-primary/90 transition-colors"
                >
                  <Camera weight="bold" size={13} />
                </button>
                <input
                  ref={avatarInputRef}
                  type="file"
                  accept="image/*"
                  className="hidden"
                  onChange={handleAvatarChange}
                />
              </div>
              <div className="flex-1">
                <p className="text-sm font-medium">Profile Picture</p>
                <p className="text-sm text-muted-foreground">JPG, PNG or GIF · Max 2MB</p>
                {avatarFile && (
                  <Button
                    size="sm"
                    className="mt-2 gap-1.5"
                    onClick={handleAvatarUpload}
                    disabled={uploadingAvatar}
                  >
                    <Camera weight="bold" size={14} />
                    {uploadingAvatar ? "Uploading..." : "Save Picture"}
                  </Button>
                )}
              </div>
            </div>

            <Separator className="opacity-30" />

            <div className="space-y-2">
              <Label>Username</Label>
              <Input value={username} disabled />
              <p className="text-sm text-muted-foreground">Username cannot be changed after first trade</p>
            </div>
            <div className="space-y-2">
              <Label>Bio</Label>
              <Textarea
                value={bio}
                onChange={(e) => setBio(e.target.value)}
                rows={3}
                maxLength={500}
              />
            </div>
            <div className="space-y-2">
              <Label>Email <span className="text-muted-foreground">(optional)</span></Label>
              <Input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
              />
              <p className="text-sm text-muted-foreground">Used for trade notifications only, not for login</p>
            </div>
            {smsEnabled && (
              <div className="space-y-2">
                <Label>Phone <span className="text-muted-foreground">(optional — for SMS alerts)</span></Label>
                <Input
                  type="tel"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="+1 809 555 0100"
                />
                <p className="text-sm text-muted-foreground">Used for SMS trade alerts when enabled</p>
              </div>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Notifications */}
      <Card className="border-border/50">
        <CardHeader>
          <div className="flex items-center gap-3">
            <div className="flex size-10 items-center justify-center rounded-xl bg-amber-500/10">
              <Bell className="size-5 text-amber-500" weight="duotone" />
            </div>
            <div>
              <CardTitle>Notifications</CardTitle>
              <CardDescription>Control how you receive trade alerts</CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <Label className="flex-1">
                <span>Email notifications</span>
                <p className="text-sm text-muted-foreground font-normal">Receive trade updates via email</p>
              </Label>
              <Switch checked={notifyEmail} onCheckedChange={setNotifyEmail} />
            </div>
            {smsEnabled && (
              <>
                <Separator className="opacity-30" />
                <div className="flex items-center justify-between">
                  <Label className="flex-1">
                    <span>SMS notifications</span>
                    <p className="text-sm text-muted-foreground font-normal">Receive trade alerts via SMS (requires phone number)</p>
                  </Label>
                  <Switch checked={notifySms} onCheckedChange={setNotifySms} />
                </div>
              </>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Trade Timer */}
      <Card className="border-border/50">
        <CardHeader>
          <CardTitle>Trade Timer</CardTitle>
          <CardDescription>How long buyers have to complete payment</CardDescription>
        </CardHeader>
        <CardContent>
          <Select value={tradeTimerMinutes} onValueChange={setTradeTimerMinutes}>
            <SelectTrigger className="w-full max-w-xs">
              <SelectValue placeholder="Select duration" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="15">15 minutes</SelectItem>
              <SelectItem value="30">30 minutes</SelectItem>
              <SelectItem value="45">45 minutes</SelectItem>
              <SelectItem value="60">60 minutes</SelectItem>
            </SelectContent>
          </Select>
        </CardContent>
      </Card>

      <div className="flex justify-end">
        <Button size="lg" className="gap-2" onClick={handleSaveAll} disabled={saving}>
          <GearSix className="size-5" weight="bold" />
          {saving ? "Saving..." : "Save All Settings"}
        </Button>
      </div>
    </div>
  )
}

Settings.layout = (page) => <DashboardLayout>{page}</DashboardLayout>
