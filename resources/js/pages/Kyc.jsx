import { useState, useEffect, useRef } from "react"
import { createPortal } from "react-dom"
import DashboardLayout from "@/layouts/DashboardLayout"
import { api } from "@/lib/api"
import { useWallet } from "@/hooks/useWallet"
import { toast } from "sonner"
import {
  IdentificationCard,
  FileText,
  HouseLine,
  Briefcase,
  UploadSimple,
  ArrowCounterClockwise,
  CheckCircle,
  Clock,
  XCircle,
  ShieldCheck,
  SealCheck,
  Lightning,
  Drop,
  Buildings,
  Lock,
  LockOpen,
  SpinnerIcon,
} from "@phosphor-icons/react"
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Separator } from "@/components/ui/separator"
import { Skeleton } from "@/components/ui/skeleton"

// Default document types and their visual config
const DOC_TYPE_CONFIG = {
  id_document: {
    title: "Government ID",
    description: "Passport, national ID, or driver's license",
    badge: "Verified Badge",
    icon: IdentificationCard,
    iconColor: "text-blue-400",
    iconBg: "bg-blue-500/15",
  },
  bank_statement: {
    title: "Bank Statement",
    description: "Recent bank statement (last 3 months)",
    badge: "Bank Badge",
    icon: FileText,
    iconColor: "text-emerald-400",
    iconBg: "bg-emerald-500/15",
  },
  proof_of_residency: {
    title: "Proof of Residency",
    description: "Utility bill or lease agreement",
    badge: "Residency Badge",
    icon: HouseLine,
    iconColor: "text-amber-400",
    iconBg: "bg-amber-500/15",
  },
  business_document: {
    title: "Business Document",
    description: "Business license or registration certificate",
    badge: "Business Badge",
    icon: Briefcase,
    iconColor: "text-purple-400",
    iconBg: "bg-purple-500/15",
  },
}

const DOCUMENT_TYPES = ["id_document", "bank_statement", "proof_of_residency", "business_document"]

const BADGES = [
  { key: "verified", label: "Verified", icon: SealCheck, color: "blue", active: false },
  { key: "fast", label: "Fast", icon: Lightning, color: "emerald", active: false },
  { key: "liquidity", label: "Liquidity", icon: Drop, color: "purple", active: false },
  { key: "business", label: "Business", icon: Buildings, color: "indigo", active: false },
]

const badgeColorMap = {
  blue: { active: "bg-blue-500/15 text-blue-400 border-blue-500/20", inactive: "bg-muted/20 text-muted-foreground/40 border-border/30" },
  emerald: { active: "bg-emerald-500/15 text-emerald-400 border-emerald-500/20", inactive: "bg-muted/20 text-muted-foreground/40 border-border/30" },
  purple: { active: "bg-purple-500/15 text-purple-400 border-purple-500/20", inactive: "bg-muted/20 text-muted-foreground/40 border-border/30" },
  indigo: { active: "bg-indigo-500/15 text-indigo-400 border-indigo-500/20", inactive: "bg-muted/20 text-muted-foreground/40 border-border/30" },
}

function StatusBadge({ status }) {
  switch (status) {
    case "approved":
      return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-sm font-medium text-emerald-400">
          <CheckCircle weight="fill" size={14} />
          Approved
        </span>
      )
    case "pending":
      return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-500/15 px-2.5 py-0.5 text-sm font-medium text-amber-400">
          <Clock weight="fill" size={14} />
          Pending
        </span>
      )
    case "not_submitted":
      return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-0.5 text-sm font-medium text-muted-foreground">
          Not Submitted
        </span>
      )
    case "rejected":
      return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-red-500/15 px-2.5 py-0.5 text-sm font-medium text-red-400">
          <XCircle weight="fill" size={14} />
          Rejected
        </span>
      )
    default:
      return null
  }
}

function KycSkeleton() {
  return (
    <div className="space-y-6">
      <div>
        <Skeleton className="h-6 w-[180px] mb-2" />
        <Skeleton className="h-4 w-[320px]" />
      </div>
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        {Array.from({ length: 4 }).map((_, i) => (
          <Card key={i} className="border-border/50">
            <CardContent className="space-y-4 pt-6">
              <div className="flex items-start gap-3">
                <Skeleton className="size-11 rounded-xl" />
                <div className="space-y-2">
                  <Skeleton className="h-4 w-[120px]" />
                  <Skeleton className="h-3 w-[200px]" />
                  <Skeleton className="h-3 w-[100px]" />
                </div>
              </div>
              <Skeleton className="h-px w-full" />
              <div className="flex justify-between">
                <Skeleton className="h-5 w-[80px] rounded-full" />
                <Skeleton className="h-8 w-[120px] rounded-md" />
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  )
}

export default function Kyc() {
  const { isAuthenticated } = useWallet()
  const [documents, setDocuments] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(false)
  const [uploadingType, setUploadingType] = useState(null)
  const [fullName, setFullName] = useState("")
  const [businessName, setBusinessName] = useState("")
  const fileInputRefs = useRef({})

  const fetchProfile = async () => {
    try {
      const res = await api.getDashboard()
      setFullName(res.data?.merchant?.full_name || "")
      setBusinessName(res.data?.merchant?.business_name || "")
    } catch {}
  }

  const fetchDocuments = async () => {
    if (!isAuthenticated) return
    setLoading(true)
    setError(false)
    try {
      const res = await api.getKycDocuments()
      const data = res.data || []
      setDocuments(Array.isArray(data) ? data : [])
    } catch (err) {
      toast.error("Failed to load KYC documents")
      setError(true)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchDocuments()
    fetchProfile()
  }, [isAuthenticated])

  // Merge API documents with the expected document types
  const getMergedDocuments = () => {
    return DOCUMENT_TYPES.map((type) => {
      const config = DOC_TYPE_CONFIG[type]
      const apiDoc = documents.find((d) => d.type === type || d.document_type === type)
      return {
        type,
        ...config,
        id: apiDoc?.id || null,
        status: apiDoc?.status || "not_submitted",
        submittedAt: apiDoc?.created_at ? new Date(apiDoc.created_at).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" }) : null,
        rejectionReason: apiDoc?.rejection_reason || apiDoc?.reason || null,
      }
    })
  }

  const handleUpload = (type) => {
    if (fileInputRefs.current[type]) {
      fileInputRefs.current[type].click()
    }
  }

  const handleFileSelected = async (type, e) => {
    const file = e.target.files?.[0]
    if (!file) return

    setUploadingType(type)
    try {
      await api.uploadKycDocument(type, file)
      toast.success("Document uploaded successfully")
      fetchDocuments()
    } catch (err) {
      toast.error(err.message || "Failed to upload document")
    } finally {
      setUploadingType(null)
      // Reset input
      if (fileInputRefs.current[type]) {
        fileInputRefs.current[type].value = ""
      }
    }
  }

  // Determine active badges based on document status
  const getActiveBadges = () => {
    const approvedTypes = documents
      .filter((d) => d.status === "approved")
      .map((d) => d.type || d.document_type)

    return BADGES.map((badge) => {
      let active = false
      if (badge.key === "verified" && approvedTypes.includes("id_document")) active = true
      if (badge.key === "fast" && approvedTypes.includes("bank_statement")) active = true
      if (badge.key === "liquidity" && approvedTypes.includes("proof_of_residency")) active = true
      if (badge.key === "business" && approvedTypes.includes("business_document")) active = true
      return { ...badge, active }
    })
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div>
          <h2 className="text-xl font-bold tracking-tight">KYC Verification</h2>
          <p className="mt-1 text-sm text-muted-foreground">Submit your documents to earn verification badges</p>
        </div>
        <div className="flex flex-col items-center justify-center py-20 text-center">
          <ShieldCheck className="size-16 text-muted-foreground/20 mb-4" weight="duotone" />
          <p className="text-lg font-semibold mb-2">Failed to load KYC documents</p>
          <p className="text-sm text-muted-foreground mb-6">Something went wrong. Please try again.</p>
          <Button onClick={() => fetchDocuments()}>Retry</Button>
        </div>
      </div>
    )
  }

  if (loading) {
    return <KycSkeleton />
  }

  const mergedDocs = getMergedDocuments()
  const activeBadges = getActiveBadges()

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h2 className="text-xl font-bold tracking-tight">KYC Verification</h2>
        <p className="mt-1 text-sm text-muted-foreground">
          Submit your documents to earn verification badges and unlock higher trading limits
        </p>
      </div>

      {/* A8: Identity profile — locks once submitted, only admin can amend */}
      <IdentityProfileSection />

      {/* Document Cards */}
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        {mergedDocs.map((doc) => {
          const Icon = doc.icon
          return (
            <Card key={doc.type} className="border-border/50">
              <CardContent className="space-y-4 pt-6">
                {/* Icon + Title + Status */}
                <div className="flex items-start justify-between">
                  <div className="flex items-start gap-3">
                    <div
                      className={`flex size-11 shrink-0 items-center justify-center rounded-xl ${doc.iconBg}`}
                    >
                      <Icon weight="duotone" size={22} className={doc.iconColor} />
                    </div>
                    <div>
                      <p className="text-sm font-semibold">{doc.title}</p>
                      <p className="text-sm text-muted-foreground">{doc.description}</p>
                      <p className="mt-1 text-sm text-muted-foreground/70">
                        Unlocks: <span className="font-medium text-muted-foreground">{doc.badge}</span>
                      </p>
                    </div>
                  </div>
                </div>

                {/* Rejection Reason */}
                {doc.status === "rejected" && doc.rejectionReason && (
                  <div className="rounded-lg bg-red-500/8 px-3 py-2.5">
                    <p className="text-sm text-red-400">{doc.rejectionReason}</p>
                  </div>
                )}

                <Separator />

                {/* Footer: Date + Status + Action */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div className="flex items-center gap-3">
                    <StatusBadge status={doc.status} />
                    {doc.submittedAt && (
                      <span className="text-sm text-muted-foreground">{doc.submittedAt}</span>
                    )}
                  </div>
                  {/* Hidden file input */}
                  <input
                    type="file"
                    ref={(el) => (fileInputRefs.current[doc.type] = el)}
                    className="hidden"
                    accept="image/*,.pdf"
                    onChange={(e) => handleFileSelected(doc.type, e)}
                  />
                  {doc.status === "approved" && (
                    <Button variant="secondary" size="sm" disabled className="gap-1.5">
                      <CheckCircle weight="fill" size={14} className="text-emerald-500" />
                      Uploaded
                    </Button>
                  )}
                  {doc.status === "pending" && (
                    <Button variant="secondary" size="sm" disabled className="gap-1.5">
                      <Clock weight="fill" size={14} className="text-amber-500" />
                      Under Review
                    </Button>
                  )}
                  {doc.status === "not_submitted" && (
                    <Button
                      size="sm"
                      className="gap-1.5"
                      disabled={uploadingType === doc.type}
                      onClick={() => handleUpload(doc.type)}
                    >
                      <UploadSimple weight="bold" size={14} />
                      {uploadingType === doc.type ? "Uploading..." : "Upload Document"}
                    </Button>
                  )}
                  {doc.status === "rejected" && (
                    <Button
                      variant="outline"
                      size="sm"
                      className="gap-1.5 text-red-400 border-red-500/20 hover:bg-red-500/10 hover:text-red-400"
                      disabled={uploadingType === doc.type}
                      onClick={() => handleUpload(doc.type)}
                    >
                      <ArrowCounterClockwise weight="bold" size={14} />
                      {uploadingType === doc.type ? "Uploading..." : "Resubmit"}
                    </Button>
                  )}
                </div>
              </CardContent>
            </Card>
          )
        })}
      </div>

      {/* Badge Preview */}
      <Card className="border-border/50">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <ShieldCheck weight="duotone" size={20} className="text-primary" />
            Badge Preview
          </CardTitle>
        </CardHeader>
        <CardContent>
          <p className="mb-4 text-sm text-muted-foreground">
            Badges are displayed on your merchant profile and increase buyer trust
          </p>
          <div className="flex flex-wrap gap-3">
            {activeBadges.map((badge) => {
              const colors = badgeColorMap[badge.color]
              const style = badge.active ? colors.active : colors.inactive
              return (
                <span
                  key={badge.key}
                  className={`inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm font-medium ${style}`}
                >
                  <badge.icon weight={badge.active ? "fill" : "regular"} size={16} />
                  {badge.label}
                </span>
              )
            })}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

Kyc.layout = (page) => <DashboardLayout>{page}</DashboardLayout>

// A8: identity profile — locks once submitted. Admin override via Filament.
function IdentityProfileSection() {
  const [profile, setProfile] = useState(null)
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [countries, setCountries] = useState([])
  const [form, setForm] = useState({
    full_name: "",
    date_of_birth: "",
    country_of_birth: "",
    country_of_residence: "",
    full_address: "",
    business_name: "",
    country_of_incorporation: "",
  })

  useEffect(() => {
    api.getCountries?.()
      .then((res) => setCountries(res.data || []))
      .catch(() => {})

    api.getKycProfile()
      .then((res) => {
        const p = res.data
        setProfile(p)
        setForm({
          full_name: p.full_name || "",
          date_of_birth: p.date_of_birth || "",
          country_of_birth: p.country_of_birth || "",
          country_of_residence: p.country_of_residence || "",
          full_address: p.full_address || "",
          business_name: p.business_name || "",
          country_of_incorporation: p.country_of_incorporation || "",
        })
      })
      .catch(() => toast.error("Failed to load identity profile"))
      .finally(() => setLoading(false))
  }, [])

  const submit = async () => {
    setSubmitting(true)
    try {
      const res = await api.submitKycProfile(form)
      setProfile(res.data)
      toast.success("Identity profile saved and locked. Contact support if changes are needed.")
    } catch (e) {
      toast.error(e?.message ?? "Failed to save identity profile")
    } finally {
      setSubmitting(false)
    }
  }

  if (loading) {
    return (
      <Card className="border-border/50">
        <CardContent className="flex items-center justify-center gap-2 py-8 text-sm text-muted-foreground">
          <SpinnerIcon size={20} className="animate-spin" /> Loading identity…
        </CardContent>
      </Card>
    )
  }

  const isLocked = !!profile?.is_locked
  const update = (k) => (e) => setForm({ ...form, [k]: e.target.value })
  const updateValue = (k) => (val) => setForm({ ...form, [k]: val })

  return (
    <Card className="border-border/50">
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle className="text-base">Identity profile</CardTitle>
          {isLocked ? (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-2.5 py-1 text-xs font-medium text-emerald-400">
              <Lock weight="fill" size={12} /> Locked
            </span>
          ) : (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-500/15 px-2.5 py-1 text-xs font-medium text-amber-400">
              <LockOpen weight="fill" size={12} /> Editable
            </span>
          )}
        </div>
        <p className="text-sm text-muted-foreground">
          {isLocked
            ? "Your identity has been submitted and is locked. Contact support to amend any field."
            : "Submit accurate identity information. Once saved, fields cannot be changed except by admin."}
        </p>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
          <Field label="Full legal name" value={form.full_name} onChange={update("full_name")} disabled={isLocked || submitting} />
          <Field label="Date of birth" type="date" value={form.date_of_birth} onChange={update("date_of_birth")} disabled={isLocked || submitting} />
          <CountryField label="Country of birth" value={form.country_of_birth} onChange={updateValue("country_of_birth")} countries={countries} disabled={isLocked || submitting} />
          <CountryField label="Country of residence" value={form.country_of_residence} onChange={updateValue("country_of_residence")} countries={countries} disabled={isLocked || submitting} />
        </div>
        <Field label="Full address" value={form.full_address} onChange={update("full_address")} disabled={isLocked || submitting} placeholder="Street, City, State/Region, Postal, Country" />

        <div className="border-t border-border/50 pt-4">
          <p className="mb-3 text-sm font-medium">Business (optional)</p>
          <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
            <Field label="Business name" value={form.business_name} onChange={update("business_name")} disabled={isLocked || submitting} />
            <CountryField label="Country of incorporation" value={form.country_of_incorporation} onChange={updateValue("country_of_incorporation")} countries={countries} disabled={isLocked || submitting} />
          </div>
        </div>

        {!isLocked && (
          <Button className="w-full" disabled={submitting} onClick={submit}>
            {submitting ? "Submitting…" : "Submit Identity"}
          </Button>
        )}
        {isLocked && (
          <p className="text-xs text-muted-foreground">
            Locked at: {profile.kyc_locked_at ? new Date(profile.kyc_locked_at).toLocaleString() : "—"}
          </p>
        )}
      </CardContent>
    </Card>
  )
}

function Field({ label, value, onChange, disabled, type = "text", placeholder = "", maxLength }) {
  return (
    <div className="space-y-1.5">
      <Label className="text-xs">{label}</Label>
      <Input
        type={type}
        value={value}
        onChange={onChange}
        disabled={disabled}
        placeholder={placeholder}
        maxLength={maxLength}
      />
    </div>
  )
}

function CountryField({ label, value, onChange, countries, disabled }) {
  const [search, setSearch] = useState("")
  const [open, setOpen] = useState(false)
  const [coords, setCoords] = useState({ left: 0, top: 0, width: 0 })
  const triggerRef = useRef(null)
  const popoverRef = useRef(null)

  const selected = countries.find((c) => c.iso2 === value)
  const displayText = selected ? selected.name : ""

  const reposition = () => {
    if (!triggerRef.current) return
    const rect = triggerRef.current.getBoundingClientRect()
    setCoords({ left: rect.left, top: rect.bottom + 4, width: rect.width })
  }

  useEffect(() => {
    if (!open) return
    reposition()
    const onScroll = () => reposition()
    const onResize = () => reposition()
    window.addEventListener("scroll", onScroll, true)
    window.addEventListener("resize", onResize)
    const handler = (e) => {
      if (triggerRef.current?.contains(e.target)) return
      if (popoverRef.current?.contains(e.target)) return
      setOpen(false)
      setSearch("")
    }
    document.addEventListener("mousedown", handler)
    return () => {
      window.removeEventListener("scroll", onScroll, true)
      window.removeEventListener("resize", onResize)
      document.removeEventListener("mousedown", handler)
    }
  }, [open])

  const q = search.trim().toLowerCase()
  const filtered = q
    ? countries.filter((c) => c.name.toLowerCase().includes(q) || c.iso2.toLowerCase().startsWith(q)).slice(0, 50)
    : countries.slice(0, 100)

  const pick = (iso2) => {
    onChange(iso2)
    setOpen(false)
    setSearch("")
  }

  return (
    <div className="space-y-1.5">
      <Label className="text-xs">{label}</Label>
      <button
        ref={triggerRef}
        type="button"
        onClick={() => !disabled && setOpen(!open)}
        disabled={disabled}
        className="flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors hover:bg-accent/50 disabled:cursor-not-allowed disabled:opacity-50"
      >
        <span className={selected ? "" : "text-muted-foreground"}>
          {displayText || "Select country"}
        </span>
        <span className="text-xs text-muted-foreground">{selected?.iso2 || "▾"}</span>
      </button>

      {open && !disabled && createPortal(
        <div
          ref={popoverRef}
          style={{ position: "fixed", left: coords.left, top: coords.top, width: coords.width, zIndex: 9999 }}
          className="max-h-72 overflow-hidden rounded-md border border-border bg-popover shadow-lg"
        >
          <div className="border-b border-border p-2">
            <Input
              autoFocus
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search country…"
              className="h-8 text-sm"
            />
          </div>
          <div className="max-h-56 overflow-y-auto">
            {filtered.length === 0 && (
              <p className="px-3 py-2 text-sm text-muted-foreground">No matches</p>
            )}
            {filtered.map((c) => (
              <button
                key={c.iso2}
                type="button"
                onClick={() => pick(c.iso2)}
                className={`flex w-full items-center justify-between px-3 py-1.5 text-left text-sm hover:bg-accent ${
                  c.iso2 === value ? "bg-accent/50 font-medium" : ""
                }`}
              >
                <span>{c.name}</span>
                <span className="text-xs text-muted-foreground">{c.iso2}</span>
              </button>
            ))}
          </div>
        </div>,
        document.body
      )}
    </div>
  )
}
