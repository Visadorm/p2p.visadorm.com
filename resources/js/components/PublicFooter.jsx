import { Link, usePage } from "@inertiajs/react"
import SiteLogo from "@/components/SiteLogo"

export default function PublicFooter() {
  const { site, pages, blockchain } = usePage().props
  const footerPages = pages?.footer || []
  const supportUrl = site?.support_url
  const basescanUrl = blockchain?.trade_escrow_address
    ? `https://${blockchain?.network === 'base-mainnet' ? '' : 'sepolia.'}basescan.org/address/${blockchain.trade_escrow_address}`
    : null

  return (
    <footer className="border-t border-[#1e2a42]/80 bg-[#141928]">
      <div className="mx-auto flex max-w-5xl flex-col items-center gap-3 px-5 py-4 sm:flex-row sm:justify-between">
        <div className="flex items-center gap-2">
          <SiteLogo />
        </div>
        <div className="flex flex-wrap items-center justify-center gap-5 text-sm text-[#8b96b0]">
          {footerPages.map(p => (
            <Link key={p.slug} href={`/p/${p.slug}`} className="hover:text-[#e8edf7] transition-colors">
              {p.title}
            </Link>
          ))}
          {supportUrl && (
            <a
              href={supportUrl}
              target={supportUrl.startsWith('http') ? '_blank' : undefined}
              rel={supportUrl.startsWith('http') ? 'noopener noreferrer' : undefined}
              className="hover:text-[#e8edf7] transition-colors"
            >
              Support
            </a>
          )}
          {basescanUrl && (
            <a
              href={basescanUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="text-[#4f6ef7] hover:underline"
            >
              BaseScan
            </a>
          )}
        </div>
      </div>
      <div className="border-t border-[#1e2a42]/80 bg-[#141928] px-5 py-3 text-center text-sm text-[#8b96b0]">
        All trades are fully escrowed on the Base blockchain
      </div>
    </footer>
  )
}
