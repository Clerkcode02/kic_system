import { useEffect, useRef } from 'react'
import { Link } from 'react-router-dom'
import { EmptyState, Skeleton } from '@/components'
import { MoneyFigure } from '../../components'
import { useInfiniteMyContracts } from '../hooks/useContracts'
import { ContractStatusBadge } from './ContractStatusBadge'

export function ContractListPage() {
  const { data, isLoading, isError, fetchNextPage, hasNextPage, isFetchingNextPage } =
    useInfiniteMyContracts()
  const sentinelRef = useRef<HTMLDivElement | null>(null)
  const contracts = data?.pages.flatMap((page) => page.data) ?? []

  useEffect(() => {
    const sentinel = sentinelRef.current
    if (!sentinel) return
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && hasNextPage && !isFetchingNextPage) fetchNextPage()
      },
      { rootMargin: '200px' },
    )
    observer.observe(sentinel)
    return () => observer.disconnect()
  }, [fetchNextPage, hasNextPage, isFetchingNextPage])

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <h1 className="font-mono text-xs font-bold uppercase tracking-widest text-docket-soft">
        Your contracts
      </h1>

      {isLoading && (
        <div className="flex flex-col gap-1">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-14 rounded-sm" />
          ))}
        </div>
      )}

      {isError && <EmptyState title="Couldn't load contracts" description="Please try again." />}

      {!isLoading && !isError && contracts.length === 0 && (
        <EmptyState
          title="No contracts yet"
          description="Contracts appear here once a client hires you for a project."
        />
      )}

      {contracts.length > 0 && (
        <div className="flex flex-col divide-y divide-dashed divide-docket-line overflow-hidden rounded-sm border border-docket-line bg-docket-paper shadow-ticket">
          {contracts.map((contract) => (
            <Link
              key={contract.id}
              to={`/freelancer/contracts/${contract.id}`}
              className="flex items-center justify-between gap-4 px-4 py-3 transition-colors hover:bg-docket-well sm:px-6"
            >
              <div className="min-w-0">
                <p className="truncate font-semibold text-docket-ink">
                  {contract.project?.title ?? 'Contract'}
                </p>
                <p className="font-mono text-sm tabular-nums text-docket-soft">
                  <MoneyFigure amount={contract.total_amount} />
                </p>
              </div>
              <ContractStatusBadge status={contract.status} />
            </Link>
          ))}
        </div>
      )}
      <div ref={sentinelRef} className="h-4" />
      {isFetchingNextPage && (
        <p className="py-2 text-center text-sm text-docket-soft">Loading more…</p>
      )}
    </div>
  )
}
