import { Link, usePage } from "@inertiajs/react"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import PublicHeader from "@/components/PublicHeader"
import PublicFooter from "@/components/PublicFooter"
import { ArrowRight, Wrench } from "@phosphor-icons/react"

export default function Dynamic() {
  const { site } = usePage().props

  return (
    <div className="min-h-screen bg-background text-foreground">
      <PublicHeader />

      <main className="container mx-auto px-4 py-24">
        <Card className="mx-auto max-w-2xl border-border/50">
          <CardContent className="flex flex-col items-center gap-6 p-10 text-center">
            <Wrench weight="duotone" size={48} className="text-primary" />
            <h1 className="text-3xl font-bold">
              {site?.name ?? "Visadorm P2P"}
            </h1>
            <p className="text-muted-foreground">
              The dynamic homepage is coming soon. Meanwhile you can connect your wallet and start trading.
            </p>
            <Button asChild size="lg">
              <Link href="/connect">
                Connect Wallet
                <ArrowRight className="ml-2 size-4" />
              </Link>
            </Button>
          </CardContent>
        </Card>
      </main>

      <PublicFooter />
    </div>
  )
}
