import { useEffect } from "react"
import { Link, router, usePage } from "@inertiajs/react"
import {
  House,
  Vault,
  ArrowsLeftRight,
  CreditCard,
  Link as LinkIcon,
  CurrencyDollar,
  GearSix,
  IdentificationCard,
  Star,
  Wallet,
  PlugsConnected,
  NotePencil,
  ShieldCheck,
  ShieldWarning,
  Lock,
} from "@phosphor-icons/react"
import {
  SidebarProvider,
  Sidebar,
  SidebarContent,
  SidebarHeader,
  SidebarFooter,
  SidebarMenu,
  SidebarMenuItem,
  SidebarMenuButton,
  SidebarGroup,
  SidebarGroupLabel,
  SidebarGroupContent,
  SidebarTrigger,
  SidebarInset,
} from "@/components/ui/sidebar"
import { Avatar, AvatarFallback } from "@/components/ui/avatar"
import { Separator } from "@/components/ui/separator"
import ConnectWallet from "@/components/ConnectWallet"
import NotificationBell from "@/components/NotificationBell"
import SiteLogo from "@/components/SiteLogo"
import { useWallet } from "@/hooks/useWallet"

const navItems = [
  { label: "Dashboard", icon: House, path: "/dashboard" },
  { label: "Liquidity", icon: Vault, path: "/liquidity" },
  { label: "Trades", icon: ArrowsLeftRight, path: "/trades" },
  { label: "Payment Methods", icon: CreditCard, path: "/payments" },
  { label: "Trading Links", icon: LinkIcon, path: "/links" },
  { label: "Instructions", icon: NotePencil, path: "/instructions" },
  { label: "Currency & Markup", icon: CurrencyDollar, path: "/currency" },
  { label: "Buyer Verification", icon: ShieldCheck, path: "/verification" },
  { label: "Security", icon: ShieldWarning, path: "/security" },
  { label: "KYC Documents", icon: IdentificationCard, path: "/kyc" },
  { label: "Reviews", icon: Star, path: "/reviews" },
  { label: "Settings", icon: GearSix, path: "/settings" },
]

const pageTitles = {
  "/dashboard": "Dashboard",
  "/liquidity": "Liquidity",
  "/trades": "Trades",
  "/payments": "Payment Methods",
  "/links": "Trading Links",
  "/instructions": "Instructions",
  "/currency": "Currency & Markup",
  "/verification": "Buyer Verification",
  "/security": "Security",
  "/kyc": "KYC Documents",
  "/reviews": "Reviews",
  "/settings": "Settings",
}

function truncateAddress(addr) {
  if (!addr) return ""
  return `${addr.slice(0, 6)}...${addr.slice(-4)}`
}

export default function DashboardLayout({ children }) {
  const { url, props } = usePage()
  const site = props.site || {}
  const pageTitle = pageTitles[url] || "Dashboard"
  const { address, isConnected, isAuthenticated, isInitialized } = useWallet()

  useEffect(() => {
    if (isInitialized && !isAuthenticated) {
      router.visit("/connect")
    }
  }, [isInitialized, isAuthenticated])

  if (!isInitialized || !isAuthenticated) {
    return (
      <div className="flex h-screen items-center justify-center bg-background">
        <div className="size-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
      </div>
    )
  }

  return (
    <SidebarProvider>
      <Sidebar variant="sidebar" collapsible="icon">
        <SidebarHeader>
          <div className="px-3 py-1">
            <SiteLogo size="lg" />
          </div>
        </SidebarHeader>
        <Separator />
        <SidebarContent>
          <SidebarGroup>
            <SidebarGroupLabel className="text-xs uppercase tracking-wider">Navigation</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {navItems.map((item) => (
                  <SidebarMenuItem key={item.path}>
                    <SidebarMenuButton
                      asChild
                      isActive={url === item.path}
                      tooltip={item.label}
                      className="h-10 text-sm"
                    >
                      <Link href={item.path}>
                        <item.icon className="size-5" weight="duotone" />
                        <span>{item.label}</span>
                      </Link>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                ))}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        </SidebarContent>
        <SidebarFooter>
          <Separator />
          {isConnected ? (
            <div className="flex items-center gap-3 px-3 py-3">
              <Avatar className="size-9">
                <AvatarFallback className="bg-primary/20 text-primary text-sm font-bold">
                  {address ? address.slice(2, 4).toUpperCase() : "??"}
                </AvatarFallback>
              </Avatar>
              <div className="flex flex-col group-data-[collapsible=icon]:hidden">
                <span className="text-sm font-semibold">Connected</span>
                <span className="text-xs text-muted-foreground font-mono">{truncateAddress(address)}</span>
              </div>
            </div>
          ) : (
            <div className="px-3 py-3 group-data-[collapsible=icon]:px-1">
              <ConnectWallet size="sm" className="w-full group-data-[collapsible=icon]:hidden" />
              <div className="hidden group-data-[collapsible=icon]:flex items-center justify-center">
                <div className="flex size-9 items-center justify-center rounded-lg bg-primary/20">
                  <PlugsConnected weight="duotone" className="size-4 text-primary" />
                </div>
              </div>
            </div>
          )}
        </SidebarFooter>
      </Sidebar>
      <SidebarInset>
        <header className="flex h-16 items-center justify-between border-b px-4 md:px-6">
          <div className="flex items-center gap-3 min-w-0">
            <SidebarTrigger className="size-8 shrink-0" />
            <Separator orientation="vertical" className="h-6" />
            <h1 className="text-lg font-semibold truncate">{pageTitle}</h1>
          </div>
          <div className="flex items-center gap-2">
            <NotificationBell />
            <ConnectWallet variant="outline" size="sm" />
          </div>
        </header>
        <main className="flex-1 overflow-auto p-4 md:p-6 animate-fade-in">
          {children}
        </main>
      </SidebarInset>
    </SidebarProvider>
  )
}
