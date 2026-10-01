import { useEffect, useRef, useState } from 'react'
import toast from 'react-hot-toast'
import { useNavigate } from 'react-router-dom'
import { Badge, Button, Card, EmptyState, Modal, Select, Skeleton } from '@/components'
import { ApiError } from '@/lib/api'
import {
  useHireProposal,
  useInfiniteProjectProposals,
  useShortlistProposal,
} from '../hooks/useProposals'
import { HIREABLE_STATUSES } from '../types'
import type { Proposal, ProposalSort, ProposalStatus } from '../types'

/*
 * Flat Badge rather than the feature's ProposalStatusBadge: that one renders
 * a StatusStamp, which is the freelancer area's rotated hand-stamped mark.
 * This list renders inside the customer dashboard, which uses flat badges —
 * mixing the two visual languages on one screen reads as a bug.
 */
const STATUS_TONE: Record<ProposalStatus, 'neutral' | 'success' | 'warning' | 'danger' | 'info'> = {
  submitted: 'info',
  shortlisted: 'warning',
  accepted: 'success',
  rejected: 'danger',
  withdrawn: 'neutral',
}

const STATUS_LABEL: Record<ProposalStatus, string> = {
  submitted: 'Submitted',
  shortlisted: 'Shortlisted',
  accepted: 'Hired',
  rejected: 'Declined',
  withdrawn: 'Withdrawn',
}

const SORT_OPTIONS: { value: ProposalSort; label: string }[] = [
  { value: 'newest', label: 'Newest first' },
  { value: 'amount_asc', label: 'Lowest price' },
  { value: 'amount_desc', label: 'Highest price' },
  { value: 'delivery_asc', label: 'Fastest delivery' },
  { value: 'rating_desc', label: 'Highest rated' },
]

interface ProposalReviewListProps {
  projectId: string
  /** Hiring is only possible while the project is still open. */
  canHire: boolean
}

export function ProposalReviewList({ projectId, canHire }: ProposalReviewListProps) {
  const [sort, setSort] = useState<ProposalSort>('newest')
  const [pendingHire, setPendingHire] = useState<Proposal | null>(null)
  const sentinelRef = useRef<HTMLDivElement | null>(null)
  const navigate = useNavigate()

  const { data, isLoading, isError, fetchNextPage, hasNextPage, isFetchingNextPage } =
    useInfiniteProjectProposals(projectId, sort)
  const shortlist = useShortlistProposal(projectId)
  const hire = useHireProposal(projectId)

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

  const handleShortlist = async (proposal: Proposal) => {
    try {
      await shortlist.mutateAsync(proposal.id)
      toast.success('Proposal shortlisted.')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not shortlist that proposal.')
    }
  }

  const handleConfirmHire = async () => {
    if (!pendingHire) return

    try {
      const contract = await hire.mutateAsync(pendingHire.id)
      setPendingHire(null)
      toast.success('Freelancer hired. Next, break the contract into milestones.')
      navigate(`/customer/contracts/${contract.id}`)
    } catch (error) {
      setPendingHire(null)

      if (!(error instanceof ApiError)) {
        toast.error('Could not hire that freelancer.')
        return
      }

      // Two expected outcomes, each needing its own explanation. Rendering
      // either as a generic failure would leave the client with no idea
      // what to do next.
      if (error.code === 'project_not_open') {
        toast.error(
          'This project is no longer open — someone has already been hired. Refreshing the list.',
        )
        return
      }

      if (error.code === 'freelancer_payouts_not_enabled') {
        toast.error(
          "This freelancer hasn't finished setting up payouts yet, so they can't be paid. Ask them to complete their payment setup, then try again.",
        )
        return
      }

      toast.error(error.message)
    }
  }

  return (
    <Card>
      <div className="flex flex-col gap-4">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <h2 className="text-sm font-semibold text-gray-900">
            Proposals{proposals.length > 0 && ` (${proposals.length})`}
          </h2>
          {proposals.length > 1 && (
            <div className="sm:min-w-48">
              <Select
                label="Sort by"
                name="sort"
                options={SORT_OPTIONS}
                value={sort}
                onChange={(event) => setSort(event.target.value as ProposalSort)}
              />
            </div>
          )}
        </div>

        {isLoading && (
          <div className="flex flex-col gap-3">
            {Array.from({ length: 2 }).map((_, index) => (
              <Skeleton key={index} className="h-28 rounded-lg" />
            ))}
          </div>
        )}

        {isError && <EmptyState title="Couldn't load proposals" description="Please try again." />}

        {!isLoading && !isError && proposals.length === 0 && (
          <EmptyState
            title="No proposals yet"
            description="Freelancers who match your project will send offers here. This usually takes a day or two."
          />
        )}

        <ul className="flex flex-col gap-3">
          {proposals.map((proposal) => (
            <li
              key={proposal.id}
              className="flex flex-col gap-3 rounded-lg border border-gray-200 p-4"
            >
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <p className="font-medium text-gray-900">
                    {proposal.freelancer.name ?? 'Freelancer'}
                  </p>
                  {proposal.freelancer.headline && (
                    <p className="text-sm text-gray-500">{proposal.freelancer.headline}</p>
                  )}
                </div>
                <Badge tone={STATUS_TONE[proposal.status]}>{STATUS_LABEL[proposal.status]}</Badge>
              </div>

              <div className="flex flex-wrap gap-x-5 gap-y-1 text-sm text-gray-700">
                <span className="font-medium tabular-nums">
                  {proposal.currency} {proposal.proposed_amount}
                </span>
                <span>{proposal.delivery_days} days to deliver</span>
                {proposal.freelancer.rating_avg > 0 ? (
                  <span>{proposal.freelancer.rating_avg.toFixed(1)} ★ rating</span>
                ) : (
                  <span className="text-gray-400">No ratings yet</span>
                )}
              </div>

              <p className="whitespace-pre-wrap text-sm text-gray-600">{proposal.cover_letter}</p>

              {canHire && HIREABLE_STATUSES.includes(proposal.status) && (
                <div className="flex flex-wrap gap-2">
                  <Button
                    type="button"
                    size="sm"
                    onClick={() => setPendingHire(proposal)}
                    disabled={hire.isPending}
                  >
                    Hire
                  </Button>
                  {proposal.status === 'submitted' && (
                    <Button
                      type="button"
                      size="sm"
                      variant="secondary"
                      isLoading={shortlist.isPending}
                      onClick={() => handleShortlist(proposal)}
                    >
                      Shortlist
                    </Button>
                  )}
                </div>
              )}
            </li>
          ))}
        </ul>

        <div ref={sentinelRef} className="h-2" />
        {isFetchingNextPage && (
          <p className="py-1 text-center text-sm text-gray-500">Loading more…</p>
        )}
      </div>

      {/*
        Hiring is exclusive and irreversible — it rejects every other
        proposal and creates the contract — so it gets a confirmation step
        rather than firing straight from the list.
      */}
      <Modal
        isOpen={pendingHire !== null}
        onClose={() => setPendingHire(null)}
        title="Hire this freelancer?"
      >
        {pendingHire && (
          <div className="flex flex-col gap-4">
            <p className="text-sm text-gray-700">
              You're hiring <strong>{pendingHire.freelancer.name ?? 'this freelancer'}</strong> for{' '}
              <strong>
                {pendingHire.currency} {pendingHire.proposed_amount}
              </strong>
              , delivered in {pendingHire.delivery_days} days.
            </p>
            <p className="text-sm text-gray-600">
              This closes the project to new proposals and declines everyone else who applied. You
              can't undo it. Next you'll split the work into milestones and fund them one at a time
              — your money stays in escrow until you approve each milestone.
            </p>
            <div className="flex flex-wrap gap-2">
              <Button type="button" isLoading={hire.isPending} onClick={handleConfirmHire}>
                Confirm and hire
              </Button>
              <Button
                type="button"
                variant="secondary"
                disabled={hire.isPending}
                onClick={() => setPendingHire(null)}
              >
                Keep comparing
              </Button>
            </div>
          </div>
        )}
      </Modal>
    </Card>
  )
}
