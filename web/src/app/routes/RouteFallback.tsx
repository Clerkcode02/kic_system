import { Skeleton } from '@/components'

/**
 * Shown while a code-split route chunk loads. Deliberately a skeleton
 * rather than a spinner or an empty screen: it reserves roughly the shape
 * of the incoming page, so the layout doesn't jump when the chunk lands.
 */
export function RouteFallback() {
  return (
    <div className="mx-auto w-full max-w-4xl p-4 sm:p-6" role="status" aria-live="polite">
      <span className="sr-only">Loading…</span>
      <Skeleton className="h-8 w-56" />
      <Skeleton className="mt-4 h-40 w-full" />
      <Skeleton className="mt-3 h-40 w-full" />
    </div>
  )
}
