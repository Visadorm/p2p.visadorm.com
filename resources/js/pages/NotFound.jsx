import { Link } from "@inertiajs/react"
import { House, Columns } from "@phosphor-icons/react"
import { Button } from "@/components/ui/button"

export default function NotFound() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-background px-4">
      <div className="flex flex-col items-center gap-6 text-center">
        <h1 className="font-mono text-6xl sm:text-8xl font-bold text-muted-foreground/30">404</h1>
        <div className="space-y-2">
          <h2 className="text-2xl font-bold tracking-tight">Page not found</h2>
          <p className="text-sm text-muted-foreground">
            The page you are looking for does not exist or has been moved.
          </p>
        </div>
        <div className="flex items-center gap-3">
          <Button asChild size="lg" className="gap-2">
            <Link href="/">
              <House weight="duotone" size={18} />
              Go Home
            </Link>
          </Button>
          <Button asChild variant="outline" size="lg" className="gap-2">
            <Link href="/dashboard">
              <Columns weight="duotone" size={18} />
              Go to Dashboard
            </Link>
          </Button>
        </div>
      </div>
    </div>
  )
}
