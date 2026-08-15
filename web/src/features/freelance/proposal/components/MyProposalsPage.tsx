import { useEffect, useRef } from 'react'
import toast from 'react-hot-toast'
import { Link } from 'react-router-dom'
import { Button, Card, EmptyState, Skeleton } from '@/components'
import { ApiError } from '@/lib/api'
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
      <h1 className="text-lg font-semibold text-gray-900">Your proposals</h1>

      {isLoading && (
        <div className="flex flex-col gap-3">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} className="h-20 rounded-lg" />
          ))}
        </div>
      )}

      {isError && <EmptyState title="Couldn't load proposals" description="Please try again." />}

      {!isLoading && !isError && proposals.length === 0 && (
        <EmptyState
          title="No proposals yet"
          description="Browse projects and submit your first proposal."
          action={
            <Link to="/freelancer/projects" className="text-sm font-medium text-blue-600 underline">
              Browse projects
            </Link>
          }
        />
      )}

      <div className="flex flex-col gap-3">
        {proposals.map((proposal) => (
          <Card key={proposal.id} className="flex items-center justify-between gap-4">
            <div>
              <Link
                to={`/freelancer/projects/${proposal.project_id}`}
                className="font-medium text-gray-900 hover:underline"
              >
                {proposal.project?.title ?? 'Project'}
              </Link>
              <p className="text-sm text-gray-500">
                ${proposal.proposed_amount} {proposal.currency} · {proposal.delivery_days} days
              </p>
            </div>
            <div className="flex items-center gap-3">
              <ProposalStatusBadge status={proposal.status} />
              {WITHDRAWABLE_STATUSES.includes(proposal.status) && (
                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  isLoading={isWithdrawing && withdrawingId === proposal.id}
                  onClick={() => handleWithdraw(proposal.id)}
                >
                  Withdraw
                </Button>
              )}
            </div>
          </Card>
        ))}
      </div>
      <div ref={sentinelRef} className="h-4" />
      {isFetchingNextPage && (
        <p className="py-2 text-center text-sm text-gray-500">Loading more…</p>
      )}
    </div>
  )
}
