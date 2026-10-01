import { useEffect, useRef } from 'react'
import { Link } from 'react-router-dom'
import { Badge, EmptyState, Skeleton } from '@/components'
import { formatMoney } from '@/lib/format/money'
import { useInfiniteContractsForMe } from '../hooks/useContracts'
import type { ContractStatus, Milestone } from '../types'

const STATUS_TONE: Record<ContractStatus, 'neutral' | 'success' | 'info' | 'danger'> = {
  active: 'info',
  completed: 'success',
  terminated: 'danger',
}

const STATUS_LABEL: Record<ContractStatus, string> = {
  active: 'Active',
  completed: 'Completed',
  terminated: 'Terminated',
}

/**
 * Surfaces the one thing a client needs to act on next. A contract with no
 * milestones is stalled until the client defines them, so that case is
 * called out rather than shown as "0 of 0 paid".
 */
function progressLabel(milestones: Milestone[]): string {
  if (milestones.length === 0) return 'Needs milestones'

  const awaiting = milestones.filter((m) => m.status === 'submitted').length
  if (awaiting > 0) {
    return `${awaiting} awaiting your review`
  }

  const paid = milestones.filter((m) => m.status === 'paid').length
  return `${paid} of ${milestones.length} milestones paid`
}

export function ClientContractListPage() {
  const sentinelRef = useRef<HTMLDivElement | null>(null)
  const { data, isLoading, isError, fetchNextPage, hasNextPage, isFetchingNextPage } =
    useInfiniteContractsForMe()
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
      <div>
        <h1 className="text-lg font-semibold text-gray-900">My contracts</h1>
        <p className="mt-1 text-sm text-gray-500">
          Freelancers you've hired. Fund and approve milestones from here.
        </p>
      </div>

      {isLoading && (
        <div className="flex flex-col gap-3">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-20 rounded-lg" />
          ))}
        </div>
      )}

      {isError && (
        <EmptyState title="Couldn't load your contracts" description="Please try again." />
      )}

      {!isLoading && !isError && contracts.length === 0 && (
        <EmptyState
          title="No contracts yet"
          description="When you hire a freelancer on one of your projects, the contract shows up here."
        />
      )}

      <ul className="flex flex-col gap-3">
        {contracts.map((contract) => (
          <li key={contract.id}>
            <Link
              to={`/customer/contracts/${contract.id}`}
              className="block rounded-lg border border-gray-200 bg-white p-4 hover:border-gray-300 hover:shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-600"
            >
              <div className="flex flex-wrap items-start justify-between gap-2">
                <h2 className="font-medium text-gray-900">
                  {contract.project?.title ?? 'Contract'}
                </h2>
                <Badge tone={STATUS_TONE[contract.status]}>{STATUS_LABEL[contract.status]}</Badge>
              </div>
              <p className="mt-1 text-sm tabular-nums text-gray-500">
                {formatMoney(contract.total_amount)} · {progressLabel(contract.milestones ?? [])}
              </p>
            </Link>
          </li>
        ))}
      </ul>

      <div ref={sentinelRef} className="h-4" />
      {isFetchingNextPage && (
        <p className="py-2 text-center text-sm text-gray-500">Loading more…</p>
      )}
    </div>
  )
}
