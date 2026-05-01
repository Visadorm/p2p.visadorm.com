import { Head, usePage } from "@inertiajs/react"
import PublicHeader from "@/components/PublicHeader"
import PublicFooter from "@/components/PublicFooter"

export default function Show() {
  const { page } = usePage().props

  return (
    <div className="min-h-screen bg-background text-foreground">
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
            className="mb-6 aspect-[16/7] w-full rounded-xl border border-border object-cover"
          />
        )}

        <header className="mb-8 border-b border-border pb-6">
          <h1 className="text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">{page.title}</h1>
          {page.excerpt && (
            <p className="mt-3 text-base text-muted-foreground">{page.excerpt}</p>
          )}
          {(page.published_at || page.updated_at) && (
            <p className="mt-4 text-xs uppercase tracking-wide text-muted-foreground">
              Last updated {new Date(page.updated_at || page.published_at).toLocaleDateString(undefined, { year: "numeric", month: "long", day: "numeric" })}
            </p>
          )}
        </header>

        <article
          className="prose prose-invert max-w-none
            prose-headings:font-semibold prose-headings:tracking-tight prose-headings:text-foreground
            prose-h2:mt-10 prose-h2:mb-4 prose-h2:text-2xl
            prose-h3:mt-8 prose-h3:mb-3 prose-h3:text-xl
            prose-p:text-foreground/85 prose-p:leading-7
            prose-strong:text-foreground
            prose-a:text-primary prose-a:no-underline hover:prose-a:underline
            prose-li:text-foreground/85 prose-li:marker:text-muted-foreground
            prose-ul:my-4 prose-ol:my-4
            prose-blockquote:border-l-primary prose-blockquote:text-muted-foreground prose-blockquote:not-italic
            prose-code:rounded prose-code:bg-muted prose-code:px-1.5 prose-code:py-0.5 prose-code:text-foreground prose-code:before:content-none prose-code:after:content-none
            prose-pre:rounded-lg prose-pre:border prose-pre:border-border prose-pre:bg-muted
            prose-hr:border-border
            prose-img:rounded-lg prose-img:border prose-img:border-border
            prose-table:border prose-table:border-border
            prose-th:border prose-th:border-border prose-th:bg-muted prose-th:px-3 prose-th:py-2 prose-th:text-foreground
            prose-td:border prose-td:border-border prose-td:px-3 prose-td:py-2"
        >
          <div dangerouslySetInnerHTML={{ __html: page.html }} />
        </article>
      </main>

      <PublicFooter />
    </div>
  )
}
