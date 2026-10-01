import { useState } from 'react'
import toast from 'react-hot-toast'
import { Badge, Button, Card, Skeleton } from '@/components'
import { ApiError } from '@/lib/api'
import { formatMoney } from '@/lib/format/money'
import { MilestoneEscrowPanel } from '@/features/payments'
import { useMilestoneDeliverables, useRejectMilestone } from '../hooks/useContracts'
import type { Milestone, MilestoneStatus } from '../types'

/*
 * The client's counterpart to MilestonePanel. Kept as a separate component
 * rather than branching inside that one: MilestonePanel is the freelancer's
 * Trade Docket surface (ticket card, perforation, hand-stamped status) and
 * its actions are freelancer-only — upload a deliverable, submit for
 * approval. The client needs the opposite half of the same milestone: read
 * the deliverables, fund escrow, approve or send back.
 */
const STATUS_TONE: Record<MilestoneStatus, 'neutral' | 'success' | 'warning' | 'danger' | 'info'> =
  {
    pending: 'neutral',
    submitted: 'info',
    approved: 'warning',
    paid: 'success',
    disputed: 'danger',
  }

const STATUS_LABEL: Record<MilestoneStatus, string> = {
  pending: 'Not started',
  submitted: 'Awaiting your review',
  approved: 'Releasing payment',
  paid: 'Paid',
  disputed: 'Changes requested',
}

interface ClientMilestoneCardProps {
  milestone: Milestone
  contractId: string
}

export function ClientMilestoneCard({ milestone, contractId }: ClientMilestoneCardProps) {
  const [isRejecting, setIsRejecting] = useState(false)
  const [reason, setReason] = useState('')
  const [reasonError, setReasonError] = useState<string | null>(null)
  const { data: deliverables, isLoading } = useMilestoneDeliverables(milestone.id)
  const reject = useRejectMilestone(contractId)

  const isUnderReview = milestone.status === 'submitted'

  const handleReject = async () => {
    if (!reason.trim()) {
      setReasonError('Tell the freelancer what needs changing.')
      return
    }

    try {
      await reject.mutateAsync({ milestoneId: milestone.id, reason: reason.trim() })
      toast.success('Sent back to the freelancer with your notes.')
      setIsRejecting(false)
      setReason('')
      setReasonError(null)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not send this back.')
    }
  }

  return (
    <Card>
      <div className="flex flex-col gap-3">
        <div className="flex flex-wrap items-start justify-between gap-2">
          <div>
            <p className="font-medium text-gray-900">{milestone.title}</p>
            <p className="text-sm tabular-nums text-gray-500">
              {formatMoney(milestone.amount)} · due {milestone.due_date}
            </p>
          </div>
          <Badge tone={STATUS_TONE[milestone.status]}>{STATUS_LABEL[milestone.status]}</Badge>
        </div>

        {milestone.status === 'disputed' && milestone.rejection_reason && (
          <div className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
            <p className="font-medium">You asked for changes:</p>
            <p>{milestone.rejection_reason}</p>
            <p className="mt-1 text-xs">Waiting for the freelancer to resubmit.</p>
          </div>
        )}

        {/* Deliverables are read-only here — uploading is the freelancer's side. */}
        {isUnderReview && (
          <div className="flex flex-col gap-2">
            <h3 className="text-xs font-medium uppercase tracking-wide text-gray-500">
              Submitted work
            </h3>
            {isLoading && <Skeleton className="h-10 rounded-md" />}
            {!isLoading && (deliverables ?? []).length === 0 && (
              <p className="text-sm text-gray-500">
                The freelancer submitted this milestone without attaching files.
              </p>
            )}
            <ul className="flex flex-col gap-1">
              {(deliverables ?? []).map((deliverable) => (
                <li
                  key={deliverable.id}
                  className="flex flex-wrap items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm"
                >
                  <span className="flex-1 text-gray-800">
                    {deliverable.description ?? deliverable.mime_type ?? 'File'}
                  </span>
                  {deliverable.download_url ? (
                    <a
                      href={deliverable.download_url}
                      target="_blank"
                      rel="noreferrer"
                      className="font-medium text-blue-600 hover:underline"
                    >
                      Download
                    </a>
                  ) : (
                    // The API withholds a URL until the virus scan passes.
                    <span className="text-xs text-gray-400">Scanning…</span>
                  )}
                </li>
              ))}
            </ul>
          </div>
        )}

        {/*
          Funding and release live in MilestoneEscrowPanel, which is already
          client-gated and renders only for a submitted milestone.
        */}
        <MilestoneEscrowPanel milestone={milestone} contractId={contractId} />

        {isUnderReview && !isRejecting && (
          <Button
            type="button"
            size="sm"
            variant="secondary"
            onClick={() => setIsRejecting(true)}
            className="self-start"
          >
            Request changes
          </Button>
        )}

        {isUnderReview && isRejecting && (
          <div className="flex flex-col gap-2 rounded-md border border-gray-200 p-3">
            <label
              htmlFor={`reject-reason-${milestone.id}`}
              className="text-sm font-medium text-gray-700"
            >
              What needs changing?
            </label>
            <textarea
              id={`reject-reason-${milestone.id}`}
              rows={3}
              value={reason}
              onChange={(event) => {
                setReason(event.target.value)
                if (reasonError) setReasonError(null)
              }}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            {reasonError && <p className="text-sm text-red-600">{reasonError}</p>}
            <p className="text-xs text-gray-500">
              The freelancer sees this and can resubmit. Your escrow stays put — nothing is paid
              out.
            </p>
            <div className="flex flex-wrap gap-2">
              <Button type="button" size="sm" isLoading={reject.isPending} onClick={handleReject}>
                Send back
              </Button>
              <Button
                type="button"
                size="sm"
                variant="secondary"
                disabled={reject.isPending}
                onClick={() => {
                  setIsRejecting(false)
                  setReasonError(null)
                }}
              >
                Cancel
              </Button>
            </div>
          </div>
        )}
      </div>
    </Card>
  )
}
