import { Button } from './Button'

interface PaginationProps {
  hasNextPage: boolean
  hasPreviousPage: boolean
  onNext: () => void
  onPrevious: () => void
  isLoading?: boolean
}

/** Cursor-based pager — CLAUDE.md §7 mandates cursor pagination, never offset/page numbers. */
export function Pagination({
  hasNextPage,
  hasPreviousPage,
  onNext,
  onPrevious,
  isLoading,
}: PaginationProps) {
  return (
    <div className="flex items-center justify-between gap-4 py-3">
      <Button
        variant="secondary"
        size="sm"
        onClick={onPrevious}
        disabled={!hasPreviousPage || isLoading}
      >
        Previous
      </Button>
      <Button variant="secondary" size="sm" onClick={onNext} disabled={!hasNextPage || isLoading}>
        Next
      </Button>
    </div>
  )
}
