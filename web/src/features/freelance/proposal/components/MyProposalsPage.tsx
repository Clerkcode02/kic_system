import { useEffect, useRef } from 'react'
import toast from 'react-hot-toast'
import { Link } from 'react-router-dom'
import { Button, EmptyState, Skeleton } from '@/components'
import { ApiError } from '@/lib/api'
import { MoneyFigure } from '../../components'
import { useInfiniteMyProposals, useWithdrawProposal } from '../hooks/useProposals'
import { WITHDRAWABLE_STATUSES } from '../types'
import { ProposalStatusBadge } from './ProposalStatusBadge'

export function MyProposalsPage() {
  const { data, isLoading, isError, fetchNextPage, hasNextPage, isFetchingNextPage } =
    useInfiniteMyProposals()
  const { mutateAsync: withdraw, isPending: isWithdrawing, variables: withdrawingId } =
    useWithdrawProposal()
  const sentinelRef = useRef<HTMLDivElement | null>(null)

  const proposals = data?.pages.flatMap((page) => page.data) ?? []

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

  const handleWithdraw = async (proposalId: string) => {
    try {
      await withdraw(proposalId)
      toast.success('Proposal withdrawn.')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not withdraw this proposal.')
    }
  }

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <h1 className="font-mono text-xs font-bold uppercase tracking-widest text-docket-soft">
        Your proposals
      </h1>

      {isLoading && (
        <div className="flex flex-col gap-1">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} className="h-14 rounded-sm" />
          ))}
        </div>
      )}

      {isError && <EmptyState title="Couldn't load proposals" description="Please try again." />}

      {!isLoading && !isError && proposals.length === 0 && (
        <EmptyState
          title="No proposals yet"
          description="Browse projects and submit your first proposal."
          action={
            <Link to="/projects" className="text-sm font-medium text-action underline">
              Browse projects
            </Link>
          }
        />
      )}

      {proposals.length > 0 && (
        <div className="flex flex-col divide-y divide-dashed divide-docket-line overflow-hidden rounded-sm border border-docket-line bg-docket-paper shadow-ticket">
          {proposals.map((proposal) => (
            <div
              key={proposal.id}
              className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-3 sm:px-6"
            >
              <div className="min-w-0">
                <Link
                  to={`/projects/${proposal.project_id}`}
                  className="font-semibold text-docket-ink hover:underline"
                >
                  {proposal.project?.title ?? 'Project'}
                </Link>
                <p className="font-mono text-sm tabular-nums text-docket-soft">
                  <MoneyFigure amount={proposal.proposed_amount} /> · {proposal.delivery_days} days
                </p>
              </div>
              <div className="flex items-center gap-3">
                <ProposalStatusBadge status={proposal.status} />
                {WITHDRAWABLE_STATUSES.includes(proposal.status) && (
                  <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    className="border-docket-line bg-docket-paper text-docket-ink hover:bg-docket-well focus-visible:outline-action"
                    isLoading={isWithdrawing && withdrawingId === proposal.id}
                    onClick={() => handleWithdraw(proposal.id)}
                  >
                    Withdraw
                  </Button>
                )}
              </div>
            </div>
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
