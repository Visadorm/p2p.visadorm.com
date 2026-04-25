import { Link, usePage } from "@inertiajs/react"
import SiteLogo from "@/components/SiteLogo"
import ConnectWallet from "@/components/ConnectWallet"

export default function PublicHeader({ showMarketplaceAnchor = false }) {
  const { pages } = usePage().props
  const headerPages = pages?.header || []

  return (
    <header className="sticky top-0 z-50 border-b border-[#1e2a42]/80 bg-[#0a0d14]/90 backdrop-blur-xl">
      <div className="mx-auto flex max-w-5xl items-center justify-between px-5 py-3">
        <Link href="/" className="flex items-center gap-2">
          <SiteLogo />
        </Link>
        <div className="flex items-center gap-2">
          {showMarketplaceAnchor && (
            <a
              href="#marketplace"
              className="hidden sm:inline-flex rounded-md px-2.5 py-1.5 text-xs text-[#8b96b0] hover:text-[#e8edf7] hover:bg-white/5 transition-colors"
            >
              Marketplace
            </a>
          )}
          {headerPages.map(p => (
            <Link
              key={p.slug}
              href={`/p/${p.slug}`}
              className="hidden sm:inline-flex rounded-md px-2.5 py-1.5 text-xs text-[#8b96b0] hover:text-[#e8edf7] hover:bg-white/5 transition-colors"
            >
              {p.title}
            </Link>
          ))}
          <ConnectWallet size="sm" />
        </div>
      </div>
    </header>
  )
}
