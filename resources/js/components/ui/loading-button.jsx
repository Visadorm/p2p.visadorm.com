import { Button } from "@/components/ui/button"
import { SpinnerIcon } from "@phosphor-icons/react"

export function LoadingButton({
  loading = false,
  loadingText,
  disabled,
  children,
  className = "",
  ...props
}) {
  const isDisabled = disabled || loading

  return (
    <Button {...props} disabled={isDisabled} className={className}>
      {loading && (
        <SpinnerIcon
          className="mr-2 size-4 animate-spin"
          weight="bold"
          aria-hidden="true"
        />
      )}
      {loading ? (loadingText ?? children) : children}
    </Button>
  )
}
