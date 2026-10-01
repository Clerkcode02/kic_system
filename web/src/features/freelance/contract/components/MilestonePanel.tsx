import { useRef, useState, type ChangeEvent } from 'react'
import toast from 'react-hot-toast'
import { Button, Card, Skeleton } from '@/components'
import { ApiError } from '@/lib/api'
import { useAuth } from '@/app/providers/useAuth'
import { MilestoneEscrowPanel } from '@/features/payments'
import { MoneyFigure, Perforation, StatusStamp } from '../../components'
import { useDeliverableUpload } from '../hooks/useDeliverableUpload'
import { useMilestoneDeliverables, useSubmitMilestone } from '../hooks/useContracts'
import { SUBMITTABLE_STATUSES, type Milestone } from '../types'
import { MilestoneStatusBadge } from './MilestoneStatusBadge'
import { MilestoneTimeline } from './MilestoneTimeline'

interface MilestonePanelProps {
  milestone: Milestone
  contractId: string
}

export function MilestonePanel({ milestone, contractId }: MilestonePanelProps) {
  const { user } = useAuth()
  const { data: deliverables, isLoading } = useMilestoneDeliverables(milestone.id)
  const { uploads, upload } = useDeliverableUpload(milestone.id)
  const { mutateAsync: submit, isPending: isSubmitting } = useSubmitMilestone(contractId)
  const [selectedIds, setSelectedIds] = useState<string[]>([])
  const fileInputRef = useRef<HTMLInputElement | null>(null)

  // Status alone is not enough: uploading a deliverable and submitting for
  // approval are the freelancer's actions (MilestonePolicy::submit requires
  // the hired freelancer), so without the role check a client viewing this
  // panel was offered controls the API would reject.
  const canManage = user?.role === 'freelancer' && SUBMITTABLE_STATUSES.includes(milestone.status)

  const handleFileChange = async (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    if (!file) return
    try {
      const item = await upload({ file })
      setSelectedIds((prev) => [...prev, item.id])
      toast.success('Deliverable uploaded.')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not upload the file.')
    } finally {
      if (fileInputRef.current) fileInputRef.current.value = ''
    }
  }

  const toggleSelected = (id: string) => {
    setSelectedIds((prev) => (prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]))
  }

  const handleSubmitForApproval = async () => {
    if (selectedIds.length === 0) {
      toast.error('Select at least one deliverable to submit.')
      return
    }
    try {
      await submit({ milestoneId: milestone.id, deliverableIds: selectedIds })
      toast.success('Milestone submitted for client approval.')
      setSelectedIds([])
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not submit this milestone.')
    }
  }

  return (
    <Card variant="ticket" className="!p-0 overflow-hidden">
      <div className="flex flex-col gap-3 px-4 py-4 sm:px-6">
        <div className="flex items-start justify-between gap-3">
          <div>
            <p className="font-semibold text-docket-ink">{milestone.title}</p>
            <p className="font-mono text-sm tabular-nums text-docket-soft">
              <MoneyFigure amount={milestone.amount} /> · due {milestone.due_date}
            </p>
          </div>
          <MilestoneStatusBadge status={milestone.status} />
        </div>

        <MilestoneTimeline status={milestone.status} />

        {milestone.status === 'disputed' && milestone.rejection_reason && (
          <div className="rounded-sm border-2 border-dashed border-stamp-disputed bg-stamp-disputed-tint p-3 text-sm text-stamp-disputed">
            <p className="font-semibold">Client requested changes:</p>
            <p>{milestone.rejection_reason}</p>
            <p className="mt-1 text-xs opacity-90">
              Upload new deliverables below and resubmit for approval.
            </p>
          </div>
        )}
      </div>

      <Perforation />

      <div className="flex flex-col gap-3 px-4 py-4 sm:px-6">
        <div className="flex flex-col gap-2">
          <p className="font-mono text-xs font-bold uppercase tracking-widest text-docket-soft">
            Deliverables
          </p>
          {isLoading && <Skeleton className="h-10 rounded-sm" />}
          {!isLoading && (deliverables ?? []).length === 0 && (
            <p className="text-sm text-docket-soft">No deliverables uploaded yet.</p>
          )}
          <div className="flex flex-col gap-1">
            {(deliverables ?? []).map((deliverable) => (
              <label
                key={deliverable.id}
                className="flex items-center gap-2 rounded-sm border border-dashed border-docket-line px-3 py-2 text-sm"
              >
                {canManage && (
                  <input
                    type="checkbox"
                    checked={selectedIds.includes(deliverable.id)}
                    onChange={() => toggleSelected(deliverable.id)}
                    className="accent-action"
                  />
                )}
                <span className="flex-1 text-docket-ink">
                  {deliverable.description ?? deliverable.mime_type ?? 'File'}
                </span>
                {deliverable.scanned && (
                  <StatusStamp role="paid" animate={false}>
                    Scanned
                  </StatusStamp>
                )}
              </label>
            ))}
          </div>
        </div>

        {canManage && (
          <div className="flex flex-col gap-2">
            <input
              ref={fileInputRef}
              type="file"
              onChange={handleFileChange}
              className="text-sm text-docket-soft"
            />
            {uploads.map((state) => (
              <p
                key={state.fileName + state.progress}
                className="font-mono text-xs text-docket-soft"
              >
                {state.fileName} — {state.status}{' '}
                {state.status === 'uploading' && `${state.progress}%`}
                {state.status === 'error' && `: ${state.error}`}
              </p>
            ))}
            <Button
              type="button"
              variant="ink"
              size="sm"
              isLoading={isSubmitting}
              onClick={handleSubmitForApproval}
              className="self-start"
            >
              Submit for approval
            </Button>
          </div>
        )}

        <MilestoneEscrowPanel milestone={milestone} contractId={contractId} />
      </div>
    </Card>
  )
}
