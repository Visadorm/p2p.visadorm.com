import { Head, usePage } from "@inertiajs/react"
import PublicHeader from "@/components/PublicHeader"
import PublicFooter from "@/components/PublicFooter"

export default function Show() {
  const { page } = usePage().props

  return (
    <div className="min-h-screen bg-[#0a0d14] text-[#e8edf7]">
      <Head>
        <title>{page.meta_title}</title>
        {page.meta_description && <meta name="description" content={page.meta_description} />}
      </Head>

      <PublicHeader />

      <main className="mx-auto max-w-3xl px-5 py-8 lg:py-12">
        {page.cover_image && (
          <img
            src={page.cover_image}
            alt={page.title}
            className="mb-6 aspect-[16/7] w-full rounded-xl object-cover"
          />
        )}

        <article className="prose prose-invert max-w-none prose-headings:font-medium prose-headings:text-[#e8edf7] prose-p:text-[#8b96b0] prose-a:text-[#4f6ef7] hover:prose-a:text-[#3a56d4] prose-strong:text-[#e8edf7] prose-li:text-[#8b96b0]">
          <div dangerouslySetInnerHTML={{ __html: page.html }} />
        </article>
      </main>

      <PublicFooter />
    </div>
  )
}
