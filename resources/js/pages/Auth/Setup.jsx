import { useState, useRef, useEffect } from "react"
import { Link, router } from "@inertiajs/react"
import { toast } from "sonner"
import { api } from "@/lib/api"
import {
  Wallet,
  User,
  Camera,
  EnvelopeSimple,
  CheckCircle,
  ArrowRight,
  ArrowLeft,
  ShieldCheck,
  Star,
  Info,
} from "@phosphor-icons/react"
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Label } from "@/components/ui/label"
import { Separator } from "@/components/ui/separator"
import { Badge } from "@/components/ui/badge"
import { Avatar, AvatarImage, AvatarFallback } from "@/components/ui/avatar"
import SiteLogo from "@/components/SiteLogo"
import { useWallet } from "@/hooks/useWallet"

const STEPS = [
  { id: 1, label: "Profile" },
  { id: 2, label: "Details" },
  { id: 3, label: "Complete" },
]

export default function Setup() {
  const { address, merchant, token, isAuthenticated, refreshMerchant } = useWallet()

  // Block access if setup is already complete
  const setupComplete = isAuthenticated && merchant && merchant.username && !merchant.username.startsWith("user_")

  useEffect(() => {
    if (setupComplete) router.visit("/dashboard")
  }, [setupComplete])

  // Still loading merchant data — show blank page, not the form
  const isLoadingAuth = !!token && !merchant

  const [step, setStep] = useState(1)
  const [username, setUsername] = useState("")
  const [bio, setBio] = useState("")
  const [email, setEmail] = useState("")

  // Avatar state
  const [avatarPreview, setAvatarPreview] = useState(null)
  const [uploadingAvatar, setUploadingAvatar] = useState(false)
  const avatarInputRef = useRef(null)

  const truncated = address ? `${address.slice(0, 6)}...${address.slice(-4)}` : "0x84A7...E3AA9"
  const initial = username ? username.charAt(0).toUpperCase() : "?"

  const [saving, setSaving] = useState(false)

  const handleAvatarChange = async (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    setAvatarPreview(URL.createObjectURL(file))
    setUploadingAvatar(true)
    try {
      await api.uploadAvatar(file)
      toast.success("Profile picture uploaded")
    } catch (err) {
      toast.error(err.message || "Failed to upload picture")
      setAvatarPreview(null)
    } finally {
      setUploadingAvatar(false)
    }
  }

  const handleComplete = async () => {
    setSaving(true)
    try {
      await api.updateProfile({ username, bio, email: email || undefined })
      await refreshMerchant()
      toast.success("Profile saved!")
      const returnUrl = sessionStorage.getItem("returnUrl")
      sessionStorage.removeItem("returnUrl")
      router.visit(returnUrl || "/dashboard")
    } catch (err) {
      toast.error(err?.message || "Failed to save profile")
    } finally {
      setSaving(false)
    }
  }

  // Show nothing while auth is loading or redirecting — prevents flash
  if (isLoadingAuth || setupComplete) return null

  return (
    <div className="min-h-screen overflow-x-hidden bg-background">
      {/* Nav */}
      <header className="border-b border-border/50 bg-background/80 backdrop-blur-xl">
        <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 lg:px-6">
          <Link href="/"><SiteLogo /></Link>
          <div className="flex items-center gap-2 rounded-full border border-border/50 bg-card px-3 py-1.5">
            <span className="size-2 rounded-full bg-emerald-500 shadow-[0_0_6px_rgba(34,197,94,0.5)]" />
            <span className="font-mono text-sm">{truncated}</span>
          </div>
        </div>
      </header>

      <div className="mx-auto max-w-2xl px-4 py-8 sm:py-12 lg:px-6">
        {/* Progress Steps */}
        <div className="mb-10 flex items-center justify-center gap-2">
          {STEPS.map((s, i) => (
            <div key={s.id} className="flex items-center gap-2">
              <div className={`flex size-10 items-center justify-center rounded-full text-sm font-bold transition-colors ${
                step > s.id
                  ? "bg-emerald-500 text-white"
                  : step === s.id
                    ? "bg-primary text-primary-foreground"
                    : "bg-muted text-muted-foreground"
              }`}>
                {step > s.id ? <CheckCircle weight="fill" className="size-5" /> : s.id}
              </div>
              <span className={`text-sm font-medium ${step >= s.id ? "text-foreground" : "text-muted-foreground"}`}>
                {s.label}
              </span>
              {i < STEPS.length - 1 && (
                <div className={`mx-1 sm:mx-2 h-px w-6 sm:w-12 ${step > s.id ? "bg-emerald-500" : "bg-border"}`} />
              )}
            </div>
          ))}
        </div>

        {/* Step 1: Username */}
        {step === 1 && (
          <Card className="border-border/50">
            <CardHeader className="text-center">
              <div className="mx-auto mb-4 flex size-16 items-center justify-center rounded-2xl bg-primary/10">
                <User weight="duotone" className="size-8 text-primary" />
              </div>
              <CardTitle className="text-2xl">Set Your Username</CardTitle>
              <CardDescription className="text-base">
                Choose a unique username for your merchant profile. This is how buyers will find you.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-6">
                {/* Avatar Preview */}
                <div className="flex flex-col items-center gap-4">
                  <div className="relative">
                    <Avatar className="size-24 border-4 border-card shadow-xl">
                      <AvatarImage src={avatarPreview} alt={username} />
                      <AvatarFallback className="bg-gradient-to-br from-primary to-blue-600 text-3xl font-bold text-white">
                        {initial}
                      </AvatarFallback>
                    </Avatar>
                    <button
                      type="button"
                      onClick={() => avatarInputRef.current?.click()}
                      disabled={uploadingAvatar}
                      className="absolute -bottom-1 -right-1 flex size-9 items-center justify-center rounded-full border-2 border-card bg-primary text-primary-foreground shadow-md hover:bg-primary/90 transition-colors"
                    >
                      <Camera weight="bold" className="size-4" />
                    </button>
                    <input
                      ref={avatarInputRef}
                      type="file"
                      accept="image/*"
                      className="hidden"
                      onChange={handleAvatarChange}
                    />
                  </div>
                  {uploadingAvatar && (
                    <p className="text-sm text-muted-foreground">Uploading...</p>
                  )}
                  {username && (
                    <div className="text-center">
                      <p className="text-lg font-semibold">{username}</p>
                      <p className="text-sm text-muted-foreground">p2p.visadorm.com/merchant/{username}</p>
                    </div>
                  )}
                </div>

                <Separator className="opacity-30" />

                <div className="space-y-2">
                  <Label className="text-base">Username</Label>
                  <Input
                    value={username}
                    onChange={e => setUsername(e.target.value.replace(/[^a-zA-Z0-9_]/g, ""))}
                    placeholder="e.g. CryptoKing"
                    className="text-base"
                    maxLength={20}
                  />
                  <div className="flex items-center justify-between">
                    <p className="text-sm text-muted-foreground">Letters, numbers, underscores only</p>
                    <span className="text-sm text-muted-foreground">{username.length}/20</span>
                  </div>
                </div>

                {/* Info */}
                <div className="flex items-start gap-3 rounded-xl bg-muted/20 p-4">
                  <Info weight="fill" className="size-5 shrink-0 text-primary mt-0.5" />
                  <div className="text-sm text-muted-foreground leading-relaxed">
                    <p className="font-medium text-foreground mb-1">Username cannot be changed later</p>
                    <p>Choose carefully — this will be your permanent identity on the platform and part of your trading link URL.</p>
                  </div>
                </div>

                <Button
                  size="lg"
                  className="w-full gap-2 text-base"
                  disabled={username.length < 3}
                  onClick={() => setStep(2)}
                >
                  Continue
                  <ArrowRight weight="bold" className="size-4" />
                </Button>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Step 2: Bio & Email */}
        {step === 2 && (
          <Card className="border-border/50">
            <CardHeader className="text-center">
              <div className="mx-auto mb-4 flex size-16 items-center justify-center rounded-2xl bg-blue-500/10">
                <EnvelopeSimple weight="duotone" className="size-8 text-blue-400" />
              </div>
              <CardTitle className="text-2xl">Complete Your Profile</CardTitle>
              <CardDescription className="text-base">
                Add a bio and optional email for trade notifications
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-6">
                <div className="space-y-2">
                  <Label className="text-base">Bio</Label>
                  <Textarea
                    value={bio}
                    onChange={e => setBio(e.target.value)}
                    placeholder="Tell buyers about yourself... e.g. Professional USDC trader. Fast responses, reliable trades."
                    rows={4}
                    maxLength={300}
                  />
                  <div className="flex items-center justify-between">
                    <p className="text-sm text-muted-foreground">Shown on your merchant profile</p>
                    <span className="text-sm text-muted-foreground">{bio.length}/300</span>
                  </div>
                </div>

                <Separator className="opacity-30" />

                <div className="space-y-2">
                  <Label className="text-base">
                    Email <span className="text-muted-foreground font-normal">(optional)</span>
                  </Label>
                  <Input
                    type="email"
                    value={email}
                    onChange={e => setEmail(e.target.value)}
                    placeholder="merchant@example.com"
                    className="text-base"
                  />
                  <p className="text-sm text-muted-foreground">
                    Used for trade notifications only — never shared publicly or used for login
                  </p>
                </div>

                <div className="flex gap-3">
                  <Button variant="outline" size="lg" className="gap-2" onClick={() => setStep(1)}>
                    <ArrowLeft weight="bold" className="size-4" />
                    Back
                  </Button>
                  <Button size="lg" className="flex-1 gap-2 text-base" onClick={() => setStep(3)}>
                    Continue
                    <ArrowRight weight="bold" className="size-4" />
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Step 3: Complete */}
        {step === 3 && (
          <Card className="border-border/50">
            <CardHeader className="text-center">
              <div className="mx-auto mb-4 flex size-20 items-center justify-center rounded-3xl bg-emerald-500/10">
                <CheckCircle weight="fill" className="size-12 text-emerald-500" />
              </div>
              <CardTitle className="text-2xl">You're All Set!</CardTitle>
              <CardDescription className="text-base">
                Your merchant account is ready. Here's a summary.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-6">
                {/* Profile Preview */}
                <div className="rounded-xl border border-border/50 bg-muted/10 p-6">
                  <div className="flex items-center gap-4 mb-4">
                    <Avatar className="size-16 border-2 border-card">
                      <AvatarImage src={avatarPreview} alt={username} />
                      <AvatarFallback className="bg-gradient-to-br from-primary to-blue-600 text-2xl font-bold text-white">
                        {initial}
                      </AvatarFallback>
                    </Avatar>
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="text-xl font-bold">{username}</span>
                        <Badge variant="secondary" className="text-sm">New Member</Badge>
                      </div>
                      <p className="font-mono text-sm text-muted-foreground">{truncated}</p>
                    </div>
                  </div>
                  {bio && <p className="text-sm text-muted-foreground mb-4">{bio}</p>}

                  <Separator className="mb-4 opacity-30" />

                  <div className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <p className="text-muted-foreground">Trading Link</p>
                      <p className="font-mono font-medium">p2p.visadorm.com/m/{username}</p>
                    </div>
                    <div>
                      <p className="text-muted-foreground">Rank</p>
                      <p className="font-medium">New Member</p>
                    </div>
                    {email && (
                      <div>
                        <p className="text-muted-foreground">Email</p>
                        <p className="font-medium">{email}</p>
                      </div>
                    )}
                    <div>
                      <p className="text-muted-foreground">Network</p>
                      <p className="font-medium">Base (Coinbase L2)</p>
                    </div>
                  </div>
                </div>

                {/* Next steps */}
                <div className="space-y-3">
                  <h3 className="text-base font-semibold">Next Steps</h3>
                  {[
                    { icon: ShieldCheck, text: "Upload KYC documents to earn verification badges" },
                    { icon: Wallet, text: "Deposit USDC to your escrow to start trading" },
                    { icon: Star, text: "Complete trades to increase your rank" },
                  ].map((item, i) => (
                    <div key={i} className="flex items-center gap-3 rounded-lg bg-muted/20 px-4 py-3">
                      <item.icon weight="duotone" className="size-5 text-primary shrink-0" />
                      <span className="text-sm text-muted-foreground">{item.text}</span>
                    </div>
                  ))}
                </div>

                <div className="flex gap-3">
                  <Button variant="outline" size="lg" className="gap-2" onClick={() => setStep(2)}>
                    <ArrowLeft weight="bold" className="size-4" />
                    Back
                  </Button>
                  <Button size="lg" className="flex-1 gap-2 text-base" onClick={handleComplete} disabled={saving}>
                    {saving ? "Saving..." : "Go to Dashboard"}
                    <ArrowRight weight="bold" className="size-4" />
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        )}
      </div>
    </div>
  )
}
