import { usePage } from "@inertiajs/react"
import { Wallet } from "@phosphor-icons/react"

export default function SiteLogo({ size = "sm", showName = true, className = "" }) {
  const { props } = usePage()
  const site = props.site || {}
  const name = site.name || "Visadorm P2P"
  const logo = site.logo

  const logoClass = { sm: "h-8", lg: "h-10", xl: "size-32" }[size] || "h-8"
  const iconClass = { sm: "size-9", lg: "size-10", xl: "size-32" }[size] || "size-9"

  return (
    <span className={`flex gap-3 items-center ${className}`}>
      {logo ? (
        <img src={logo} alt={name} className={logoClass} />
      ) : (
        <span className={`flex ${iconClass} items-center justify-center rounded-lg bg-primary`}>
          <Wallet className="size-5 text-primary-foreground" weight="fill" />
        </span>
      )}
      {showName && !logo && (
        <span className="text-base font-bold tracking-tight">{name}</span>
      )}
    </span>
  )
}
