import { useState, useEffect } from "react"
import { Link } from "@inertiajs/react"
import { Bell, CheckCircle, ArrowsLeftRight, Warning, ShieldCheck } from "@phosphor-icons/react"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { ScrollArea } from "@/components/ui/scroll-area"
import { useWallet } from "@/hooks/useWallet"
import { useNotifications } from "@/hooks/useNotifications"

const TYPE_ICONS = {
  trade_initiated: ArrowsLeftRight,
  payment_marked: ArrowsLeftRight,
  bank_proof_uploaded: ShieldCheck,
  buyer_id_submitted: ShieldCheck,
  trade_completed: CheckCircle,
  trade_cancelled: Warning,
  trade_expired: Warning,
  new_dispute: Warning,
  kyc_approved: CheckCircle,
  kyc_rejected: Warning,
}

function timeAgo(dateStr) {
  if (!dateStr) return ""
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return "now"
  if (mins < 60) return `${mins}m`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours}h`
  return `${Math.floor(hours / 24)}d`
}

export default function NotificationBell() {
  const { isAuthenticated } = useWallet()
  const { notifications, unreadCount, isLoading, fetchNotifications, markRead, markAllRead } = useNotifications()
  const [open, setOpen] = useState(false)

  // Fetch notifications when dropdown opens
  useEffect(() => {
    if (open) fetchNotifications()
  }, [open, fetchNotifications])

  if (!isAuthenticated) return null

  return (
    <DropdownMenu open={open} onOpenChange={setOpen}>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" className="relative size-9">
          <Bell weight="duotone" className="size-5" />
          {unreadCount > 0 && (
            <span className="absolute -right-0.5 -top-0.5 flex size-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
              {unreadCount > 9 ? "9+" : unreadCount}
            </span>
          )}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-80">
        <div className="flex items-center justify-between px-3 py-2">
          <span className="text-sm font-semibold">Notifications</span>
          {unreadCount > 0 && (
            <button
              onClick={markAllRead}
              className="text-xs text-primary hover:underline"
            >
              Mark all read
            </button>
          )}
        </div>
        <DropdownMenuSeparator />
        <ScrollArea className="max-h-72">
          {isLoading ? (
            <div className="flex flex-col items-center justify-center py-8 text-center">
              <div className="size-6 animate-spin rounded-full border-2 border-muted-foreground/20 border-t-primary mb-2" />
              <p className="text-sm text-muted-foreground">Loading...</p>
            </div>
          ) : notifications.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-8 text-center">
              <Bell weight="duotone" className="size-8 text-muted-foreground/30 mb-2" />
              <p className="text-sm text-muted-foreground">No notifications</p>
            </div>
          ) : (
            notifications.map((notif) => {
              const Icon = TYPE_ICONS[notif.type] || Bell
              return (
                <DropdownMenuItem
                  key={notif.id}
                  className={`flex items-start gap-3 px-3 py-3 cursor-pointer ${
                    !notif.is_read ? "bg-primary/5" : ""
                  }`}
                  onClick={() => {
                    if (!notif.is_read) markRead(notif.id)
                  }}
                >
                  <div className={`flex size-8 shrink-0 items-center justify-center rounded-full ${
                    !notif.is_read ? "bg-primary/10" : "bg-muted/30"
                  }`}>
                    <Icon weight="duotone" className={`size-4 ${!notif.is_read ? "text-primary" : "text-muted-foreground"}`} />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className={`text-sm leading-tight ${!notif.is_read ? "font-medium" : "text-muted-foreground"}`}>
                      {notif.title}
                    </p>
                    <p className="text-xs text-muted-foreground mt-0.5 break-words">{notif.body}</p>
                  </div>
                  <span className="text-xs text-muted-foreground shrink-0">{timeAgo(notif.created_at)}</span>
                </DropdownMenuItem>
              )
            })
          )}
        </ScrollArea>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
