import { useState, useEffect, useRef } from "react"
import DashboardLayout from "@/layouts/DashboardLayout"
import { api } from "@/lib/api"
import { useWallet } from "@/hooks/useWallet"
import { toast } from "sonner"
import {
  Plus,
  CreditCard,
} from "@phosphor-icons/react"
import PaymentMethodCard from "@/components/PaymentMethodCard"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Skeleton } from "@/components/ui/skeleton"
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from "@/components/ui/dialog"
import { Textarea } from "@/components/ui/textarea"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"



function PaymentMethodsSkeleton() {
  return (
    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
      {Array.from({ length: 4 }).map((_, i) => (
        <Card key={i} className="border-border/50">
          <CardContent className="pt-6">
            <div className="space-y-4">
              <div className="flex items-start justify-between">
                <div className="flex items-center gap-3">
                  <Skeleton className="size-11 rounded-xl" />
                  <div className="space-y-2">
                    <Skeleton className="h-4 w-[120px]" />
                    <Skeleton className="h-3 w-[80px]" />
                  </div>
                </div>
                <div className="flex gap-2">
                  <Skeleton className="h-5 w-[60px] rounded-full" />
                  <Skeleton className="h-5 w-[50px] rounded-full" />
                </div>
              </div>
              <Skeleton className="h-20 w-full rounded-lg" />
              <div className="flex gap-2">
                <Skeleton className="h-8 flex-1 rounded-md" />
                <Skeleton className="h-8 w-[70px] rounded-md" />
              </div>
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  )
}

export default function PaymentMethods() {
  const { isAuthenticated } = useWallet()
  const [methods, setMethods] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(false)
  const [showDialog, setShowDialog] = useState(false)
  const [editingMethod, setEditingMethod] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [deletingId, setDeletingId] = useState(null)

  // Form state
  const [formData, setFormData] = useState({
    type: "bank_transfer",
    provider: "",
    label: "",
    // Bank Transfer fields
    account_name: "",
    account_number: "",
    routing: "",
    currency: "",
    // Online / Mobile fields
    email_or_username: "",
    phone_or_username: "",
    // Cash Meeting fields
    location: "",
    meeting_point: "",
    safety_note: "",
  })


  const fetchMethods = async () => {
    if (!isAuthenticated) return
    setLoading(true)
    setError(false)
    try {
      const res = await api.getPaymentMethods()
      setMethods(res.data || [])
    } catch (err) {
      toast.error("Failed to load payment methods")
      setError(true)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchMethods()
  }, [isAuthenticated])

  const resetForm = () => {
    setFormData({ type: "bank_transfer", provider: "", label: "", account_name: "", account_number: "", routing: "", currency: "", email_or_username: "", phone_or_username: "", location: "", meeting_point: "", safety_note: "" })
    setEditingMethod(null)
  }

  const openAddDialog = () => {
    resetForm()
    setShowDialog(true)
  }

  const openEditDialog = (method) => {
    setEditingMethod(method)
    const d = method.details || {}
    setFormData({
      type: method.type || "bank_transfer",
      provider: method.provider || "",
      label: method.label || "",
      account_name: d.account_name || "",
      account_number: d.account_number || "",
      routing: d.routing || "",
      currency: d.currency || "",
      email_or_username: d.email_or_username || "",
      phone_or_username: d.phone_or_username || "",
      location: d.location || method.location || "",
      meeting_point: d.meeting_point || "",
      safety_note: d.safety_note || method.safety_note || "",
    })
    setShowDialog(true)
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSubmitting(true)
    try {
      let details = {}
      if (formData.type === "bank_transfer") {
        details = {
          account_name: formData.account_name,
          account_number: formData.account_number,
          routing: formData.routing || undefined,
          currency: formData.currency,
        }
      } else if (formData.type === "online_payment") {
        details = {
          email_or_username: formData.email_or_username,
          currency: formData.currency || undefined,
        }
      } else if (formData.type === "mobile_payment") {
        details = {
          phone_or_username: formData.phone_or_username,
          currency: formData.currency || undefined,
        }
      } else if (formData.type === "cash_meeting") {
        details = {}
        if (formData.meeting_point) details.meeting_point = formData.meeting_point
      }
      // Remove undefined values
      details = Object.fromEntries(Object.entries(details).filter(([, v]) => v))

      const providerName = formData.type === "cash_meeting" ? "Cash Meeting" : formData.provider
      const payload = {
        type: formData.type,
        provider: providerName,
        label: providerName,
        ...(Object.keys(details).length > 0 ? { details } : {}),
        ...(formData.type === "cash_meeting" ? {
          location: formData.location || null,
          safety_note: formData.safety_note || null,
        } : {}),
      }
      if (editingMethod) {
        await api.updatePaymentMethod(editingMethod.id, payload)
        toast.success("Payment method updated")
      } else {
        await api.createPaymentMethod(payload)
        toast.success("Payment method added")
      }
      setShowDialog(false)
      resetForm()
      fetchMethods()
    } catch (err) {
      toast.error(err.message || "Failed to save payment method")
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async (id) => {
    if (!confirm("Are you sure you want to delete this payment method?")) return
    setDeletingId(id)
    try {
      await api.deletePaymentMethod(id)
      toast.success("Payment method deleted")
      fetchMethods()
    } catch (err) {
      toast.error(err.message || "Failed to delete payment method")
    } finally {
      setDeletingId(null)
    }
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">Payment Methods</h2>
          <p className="text-sm text-muted-foreground">Manage your accepted payment methods for P2P trades</p>
        </div>
        <div className="flex flex-col items-center justify-center py-20 text-center">
          <CreditCard className="size-16 text-muted-foreground/20 mb-4" weight="duotone" />
          <p className="text-lg font-semibold mb-2">Failed to load payment methods</p>
          <p className="text-sm text-muted-foreground mb-6">Something went wrong. Please try again.</p>
          <Button onClick={() => fetchMethods()}>Retry</Button>
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">Payment Methods</h2>
          <p className="text-sm text-muted-foreground">Manage your accepted payment methods for P2P trades</p>
        </div>
        <Button className="gap-2 w-full sm:w-auto" onClick={openAddDialog}>
          <Plus className="size-5" weight="bold" />
          Add Method
        </Button>
      </div>

      {/* Cards grid */}
      {loading ? (
        <PaymentMethodsSkeleton />
      ) : methods.length === 0 ? (
        <Card className="border-border/50">
          <CardContent className="py-16">
            <div className="flex flex-col items-center justify-center text-center">
              <CreditCard className="size-12 text-muted-foreground/30 mb-3" weight="duotone" />
              <p className="text-muted-foreground font-medium">No payment methods yet</p>
              <p className="text-sm text-muted-foreground/60 mb-4">Add your first payment method to start receiving trades</p>
              <Button className="gap-2" onClick={openAddDialog}>
                <Plus className="size-4" weight="bold" />
                Add Payment Method
              </Button>
            </div>
          </CardContent>
        </Card>
      ) : (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          {methods.map((method) => (
            <PaymentMethodCard
              key={method.id}
              method={method}
              onEdit={openEditDialog}
              onDelete={handleDelete}
              deletingId={deletingId}
            />
          ))}
        </div>
      )}

      {/* Add/Edit Dialog */}
      <Dialog open={showDialog} onOpenChange={(open) => { if (!open) { setShowDialog(false); resetForm() } else setShowDialog(true) }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editingMethod ? "Edit Payment Method" : "Add Payment Method"}</DialogTitle>
            <DialogDescription>
              {editingMethod ? "Update your payment method details" : "Add a new payment method for receiving trade payments"}
            </DialogDescription>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-2">
              <Label>Type</Label>
              <Select value={formData.type} onValueChange={(val) => setFormData((prev) => ({ ...prev, type: val }))}>
                <SelectTrigger>
                  <SelectValue placeholder="Select type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="bank_transfer">Bank Transfer</SelectItem>
                  <SelectItem value="online_payment">Online Payment</SelectItem>
                  <SelectItem value="mobile_payment">Mobile Payment</SelectItem>
                  <SelectItem value="cash_meeting">Cash Meeting</SelectItem>
                </SelectContent>
              </Select>
            </div>
            {formData.type !== "cash_meeting" && (
              <div className="space-y-2">
                <Label>Provider Name</Label>
                <Input
                  value={formData.provider}
                  onChange={(e) => setFormData((prev) => ({ ...prev, provider: e.target.value }))}
                  placeholder={
                    formData.type === "bank_transfer" ? "e.g., Banco Popular, Chase, BHD León"
                    : formData.type === "online_payment" ? "e.g., Zelle, PayPal, Wise"
                    : "e.g., M-Pesa, Cash App, Opay"
                  }
                  required
                />
              </div>
            )}

            {/* ── Bank Transfer fields ── */}
            {formData.type === "bank_transfer" && (
              <>
                <div className="space-y-2">
                  <Label>Account Holder Name</Label>
                  <Input
                    value={formData.account_name}
                    onChange={(e) => setFormData((prev) => ({ ...prev, account_name: e.target.value }))}
                    placeholder="Full name on the account"
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label>Account Number / IBAN</Label>
                  <Input
                    value={formData.account_number}
                    onChange={(e) => setFormData((prev) => ({ ...prev, account_number: e.target.value }))}
                    placeholder="Account number or IBAN"
                    required
                  />
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div className="space-y-2">
                    <Label>Routing / Branch <span className="text-muted-foreground font-normal">(optional)</span></Label>
                    <Input
                      value={formData.routing}
                      onChange={(e) => setFormData((prev) => ({ ...prev, routing: e.target.value }))}
                      placeholder="Routing or branch code"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label>Currency</Label>
                    <Input
                      value={formData.currency}
                      onChange={(e) => setFormData((prev) => ({ ...prev, currency: e.target.value }))}
                      placeholder="e.g., DOP, USD"
                      required
                    />
                  </div>
                </div>
              </>
            )}

            {/* ── Online Payment fields ── */}
            {formData.type === "online_payment" && (
              <>
                <div className="space-y-2">
                  <Label>Email / Username</Label>
                  <Input
                    value={formData.email_or_username}
                    onChange={(e) => setFormData((prev) => ({ ...prev, email_or_username: e.target.value }))}
                    placeholder="e.g., user@email.com or @username"
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label>Currency <span className="text-muted-foreground font-normal">(optional)</span></Label>
                  <Input
                    value={formData.currency}
                    onChange={(e) => setFormData((prev) => ({ ...prev, currency: e.target.value }))}
                    placeholder="e.g., USD, EUR"
                  />
                </div>
              </>
            )}

            {/* ── Mobile Payment fields ── */}
            {formData.type === "mobile_payment" && (
              <>
                <div className="space-y-2">
                  <Label>Phone Number / Username</Label>
                  <Input
                    value={formData.phone_or_username}
                    onChange={(e) => setFormData((prev) => ({ ...prev, phone_or_username: e.target.value }))}
                    placeholder="e.g., +1 809 555 0100 or $cashtag"
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label>Currency <span className="text-muted-foreground font-normal">(optional)</span></Label>
                  <Input
                    value={formData.currency}
                    onChange={(e) => setFormData((prev) => ({ ...prev, currency: e.target.value }))}
                    placeholder="e.g., USD, NGN"
                  />
                </div>
              </>
            )}

            {/* ── Cash Meeting fields ── */}
            {formData.type === "cash_meeting" && (
              <>
                <div className="space-y-2">
                  <Label>Location / City</Label>
                  <Input
                    value={formData.location}
                    onChange={(e) => setFormData((prev) => ({ ...prev, location: e.target.value }))}
                    placeholder="e.g., Santo Domingo"
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label>Meeting Point <span className="text-muted-foreground font-normal">(optional)</span></Label>
                  <Input
                    value={formData.meeting_point}
                    onChange={(e) => setFormData((prev) => ({ ...prev, meeting_point: e.target.value }))}
                    placeholder="e.g., Agora Mall food court, 2nd floor"
                  />
                </div>
                <div className="space-y-2">
                  <Label>Safety Note <span className="text-muted-foreground font-normal">(optional)</span></Label>
                  <Textarea
                    value={formData.safety_note}
                    onChange={(e) => setFormData((prev) => ({ ...prev, safety_note: e.target.value }))}
                    placeholder="e.g., Meet in public area only. Bring valid ID."
                    rows={2}
                  />
                </div>
              </>
            )}
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => { setShowDialog(false); resetForm() }}>
                Cancel
              </Button>
              <Button type="submit" disabled={submitting}>
                {submitting ? "Saving..." : editingMethod ? "Update" : "Add Method"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}

PaymentMethods.layout = (page) => <DashboardLayout>{page}</DashboardLayout>
