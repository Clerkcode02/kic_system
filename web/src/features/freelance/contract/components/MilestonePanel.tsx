import { useRef, useState, type ChangeEvent } from 'react'
import toast from 'react-hot-toast'
import { Badge, Button, Skeleton } from '@/components'
import { ApiError } from '@/lib/api'
import { useDeliverableUpload } from '../hooks/useDeliverableUpload'
import { useMilestoneDeliverables, useSubmitMilestone } from '../hooks/useContracts'
import { SUBMITTABLE_STATUSES, type Milestone } from '../types'
import { MilestoneStatusBadge } from './MilestoneStatusBadge'

interface MilestonePanelProps {
  milestone: Milestone
  contractId: string
}

export function MilestonePanel({ milestone, contractId }: MilestonePanelProps) {
  const { data: deliverables, isLoading } = useMilestoneDeliverables(milestone.id)
  const { uploads, upload } = useDeliverableUpload(milestone.id)
  const { mutateAsync: submit, isPending: isSubmitting } = useSubmitMilestone(contractId)
  const [selectedIds, setSelectedIds] = useState<string[]>([])
  const fileInputRef = useRef<HTMLInputElement | null>(null)

  const canManage = SUBMITTABLE_STATUSES.includes(milestone.status)

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
    <div className="flex flex-col gap-3 border-t border-gray-100 pt-3">
      <div className="flex items-center justify-between">
        <div>
          <p className="font-medium text-gray-900">{milestone.title}</p>
          <p className="text-sm text-gray-500">
            ${milestone.amount} {milestone.currency} · Due {milestone.due_date}
          </p>
        </div>
        <MilestoneStatusBadge status={milestone.status} />
      </div>

      {milestone.status === 'disputed' && milestone.rejection_reason && (
        <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          <p className="font-medium">Client requested changes:</p>
          <p>{milestone.rejection_reason}</p>
          <p className="mt-1 text-xs text-red-600">
            Upload new deliverables below and resubmit for approval.
          </p>
        </div>
      )}

      <div className="flex flex-col gap-2">
        <p className="text-sm font-medium text-gray-700">Deliverables</p>
        {isLoading && <Skeleton className="h-10 rounded-md" />}
        {!isLoading && (deliverables ?? []).length === 0 && (
          <p className="text-sm text-gray-400">No deliverables uploaded yet.</p>
        )}
        <div className="flex flex-col gap-1">
          {(deliverables ?? []).map((deliverable) => (
            <label
              key={deliverable.id}
              className="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm"
            >
              {canManage && (
                <input
                  type="checkbox"
                  checked={selectedIds.includes(deliverable.id)}
                  onChange={() => toggleSelected(deliverable.id)}
                />
              )}
              <span className="flex-1 text-gray-700">
                {deliverable.description ?? deliverable.mime_type ?? 'File'}
              </span>
              {deliverable.scanned && <Badge tone="success">Scanned</Badge>}
            </label>
          ))}
        </div>
      </div>

      {canManage && (
        <div className="flex flex-col gap-2">
          <input ref={fileInputRef} type="file" onChange={handleFileChange} />
          {uploads.map((state) => (
            <p key={state.fileName + state.progress} className="text-xs text-gray-500">
              {state.fileName} — {state.status} {state.status === 'uploading' && `${state.progress}%`}
              {state.status === 'error' && `: ${state.error}`}
            </p>
          ))}
          <Button
            type="button"
            size="sm"
            isLoading={isSubmitting}
            onClick={handleSubmitForApproval}
            className="self-start"
          >
            Submit for approval
          </Button>
        </div>
      )}
    </div>
  )
}
