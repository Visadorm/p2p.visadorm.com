import {
  Bank,
  CurrencyDollar,
  HandCoins,
  PencilSimple,
  Trash,
  Lightning,
  CreditCard,
} from "@phosphor-icons/react"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"

const iconMap = {
  bank_transfer: Bank,
  online_payment: CurrencyDollar,
  mobile_payment: Lightning,
  cash_meeting: HandCoins,
  default: CreditCard,
}

const colorMap = {
  blue: "bg-blue-500/10 text-blue-500",
  emerald: "bg-emerald-500/10 text-emerald-500",
  purple: "bg-purple-500/10 text-purple-500",
  amber: "bg-amber-500/10 text-amber-500",
  red: "bg-red-500/10 text-red-500",
}

const categoryBadgeMap = {
  bank_transfer: "bg-blue-500/15 text-blue-500",
  online_payment: "bg-purple-500/15 text-purple-500",
  mobile_payment: "bg-amber-500/15 text-amber-500",
  cash_meeting: "bg-emerald-500/15 text-emerald-500",
}

const categoryColors = {
  bank_transfer: "blue",
  online_payment: "purple",
  mobile_payment: "amber",
  cash_meeting: "emerald",
}

const categoryLabels = {
  bank_transfer: "Bank Transfer",
  online_payment: "Online Payment",
  mobile_payment: "Mobile Payment",
  cash_meeting: "Cash Meeting",
}

function getIcon(method) {
  const category = (method.type || method.category || "default").toLowerCase()
  return iconMap[category] || iconMap.default
}

function getColor(method) {
  const category = (method.type || method.category || "").toLowerCase()
  return categoryColors[category] || "blue"
}

function getDetails(method) {
  const info = method.details || {}
  const type = (method.type || "").toLowerCase()
  const details = []

  if (type === "bank_transfer") {
    if (info.account_name) details.push({ label: "Holder", value: info.account_name })
    if (info.account_number) details.push({ label: "Account", value: info.account_number })
    if (info.routing) details.push({ label: "Routing", value: info.routing })
    if (info.currency) details.push({ label: "Currency", value: info.currency })
  } else if (type === "online_payment") {
    if (info.email_or_username) details.push({ label: "Email / Username", value: info.email_or_username })
    if (info.currency) details.push({ label: "Currency", value: info.currency })
  } else if (type === "mobile_payment") {
    if (info.phone_or_username) details.push({ label: "Phone / Username", value: info.phone_or_username })
    if (info.currency) details.push({ label: "Currency", value: info.currency })
  } else if (type === "cash_meeting") {
    if (method.location) details.push({ label: "Location", value: method.location })
    if (info.meeting_point) details.push({ label: "Meeting Point", value: info.meeting_point })
    if (method.safety_note) details.push({ label: "Safety Note", value: method.safety_note })
  }

  // Fallback for legacy data with old field names
  if (details.length === 0) {
    if (info.account_number) details.push({ label: "Account", value: info.account_number })
    if (info.account_name) details.push({ label: "Holder", value: info.account_name })
    if (info.currency) details.push({ label: "Currency", value: info.currency })
  }

  return details
}

export default function PaymentMethodCard({ method, onEdit, onDelete, deletingId }) {
  const Icon = getIcon(method)
  const color = getColor(method)
  const details = getDetails(method)
  const category = (method.type || method.category || "").toLowerCase()

  return (
    <Card className="border-border/50">
      <CardContent className="pt-6">
        <div className="space-y-4">
          {/* Top: Icon + Name + Category */}
          <div className="flex items-start justify-between">
            <div className="flex items-center gap-3">
              <div className={`flex size-11 items-center justify-center rounded-xl ${colorMap[color]}`}>
                <Icon className="size-6" weight="duotone" />
              </div>
              <div>
                <p className="text-sm font-semibold">{method.label || method.provider}</p>
                <p className="text-sm text-muted-foreground">
                  {categoryLabels[category] || method.type || "Payment"}
                </p>
              </div>
            </div>
            <div className="flex items-center gap-2">
              <span
                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-sm font-medium ${categoryBadgeMap[category] || "bg-muted text-muted-foreground"}`}
              >
                {categoryLabels[category] || method.type || "Other"}
              </span>
              {method.is_active !== false ? (
                <span className="inline-flex items-center rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-sm font-medium text-emerald-500">
                  Active
                </span>
              ) : (
                <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-sm font-medium text-muted-foreground">
                  Inactive
                </span>
              )}
            </div>
          </div>

          {/* Middle: Details */}
          {details.length > 0 && (
            <div className="rounded-lg bg-muted/30 px-4 py-3">
              {details.map((detail, i) => (
                <div
                  key={detail.label}
                  className={`flex items-center justify-between py-1.5 ${i < details.length - 1 ? "border-b border-border/30" : ""}`}
                >
                  <span className="text-sm text-muted-foreground">{detail.label}</span>
                  <span className="font-mono text-sm font-medium">{detail.value}</span>
                </div>
              ))}
            </div>
          )}

          {/* Bottom: Actions */}
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              className="flex-1 gap-1.5"
              onClick={() => onEdit(method)}
            >
              <PencilSimple className="size-4" />
              Edit
            </Button>
            <Button
              variant="ghost"
              size="sm"
              className="gap-1.5 text-red-500 hover:text-red-500"
              disabled={deletingId === method.id}
              onClick={() => onDelete(method.id)}
            >
              <Trash className="size-4" />
              {deletingId === method.id ? "Deleting..." : "Delete"}
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
