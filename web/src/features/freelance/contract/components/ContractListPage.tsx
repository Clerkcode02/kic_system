import { useEffect, useRef } from 'react'
import { Link } from 'react-router-dom'
import { Badge, Card, EmptyState, Skeleton } from '@/components'
import { useInfiniteMyContracts } from '../hooks/useContracts'
import type { ContractStatus } from '../types'

const STATUS_TONE: Record<ContractStatus, 'neutral' | 'success' | 'warning' | 'danger' | 'info'> = {
  active: 'info',
  completed: 'success',
  terminated: 'danger',
}

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
      <h1 className="text-lg font-semibold text-gray-900">Your contracts</h1>

      {isLoading && (
        <div className="flex flex-col gap-3">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-20 rounded-lg" />
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

      <div className="flex flex-col gap-3">
        {contracts.map((contract) => (
          <Link key={contract.id} to={`/freelancer/contracts/${contract.id}`}>
            <Card className="flex items-center justify-between gap-4 transition hover:shadow-md">
              <div>
                <p className="font-medium text-gray-900">
                  {contract.project?.title ?? 'Contract'}
                </p>
                <p className="text-sm text-gray-500">
                  ${contract.total_amount} {contract.currency}
                </p>
              </div>
              <Badge tone={STATUS_TONE[contract.status]}>{contract.status}</Badge>
            </Card>
          </Link>
        ))}
      </div>
      <div ref={sentinelRef} className="h-4" />
      {isFetchingNextPage && (
        <p className="py-2 text-center text-sm text-gray-500">Loading more…</p>
      )}
    </div>
  )
}
