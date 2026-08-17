import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import toast from 'react-hot-toast'
import { Badge, Button, EmptyState, Modal, Pagination, Table, type TableColumn } from '@/components'
import { cn } from '@/lib/cn'
import { ApiError } from '@/lib/api'
import {
  useBulkApproveBusinessVerifications,
  useBulkApproveFreelancerVerifications,
  useBulkRejectBusinessVerifications,
  useBulkRejectFreelancerVerifications,
  useBusinessVerificationQueue,
  useFreelancerVerificationQueue,
} from '../hooks/useVerificationQueue'
import { VerificationDetailDrawer } from './VerificationDetailDrawer'
import type { BusinessVerification, FreelancerVerification } from '../types'

type Tab = 'business' | 'freelancer'

const rejectReasonSchema = z.object({
  reason: z.string().min(10, 'Reason must be at least 10 characters').max(1000),
})

type RejectReasonValues = z.infer<typeof rejectReasonSchema>

function BulkRejectModal({
  isOpen,
  count,
  isSubmitting,
  onClose,
  onSubmit,
}: {
  isOpen: boolean
  count: number
  isSubmitting: boolean
  onClose: () => void
  onSubmit: (reason: string) => void
}) {
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<RejectReasonValues>({
    resolver: zodResolver(rejectReasonSchema),
    defaultValues: { reason: '' },
  })

  const handleClose = () => {
    reset()
    onClose()
  }

  return (
    <Modal isOpen={isOpen} onClose={handleClose} title={`Reject ${count} application(s)`}>
      <form
        onSubmit={handleSubmit((values) => onSubmit(values.reason))}
        className="flex flex-col gap-3"
      >
        <div className="flex flex-col gap-1">
          <label htmlFor="bulk-reject-reason" className="text-sm font-medium text-gray-700">
            Rejection reason
          </label>
          <textarea
            id="bulk-reject-reason"
            rows={3}
            className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            {...register('reason')}
          />
          {errors.reason && <p className="text-sm text-red-600">{errors.reason.message}</p>}
        </div>
        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" size="sm" onClick={handleClose}>
            Cancel
          </Button>
          <Button type="submit" variant="danger" size="sm" isLoading={isSubmitting}>
            Confirm reject
          </Button>
        </div>
      </form>
    </Modal>
  )
}

function BusinessQueueTab({
  onView,
}: {
  onView: (id: string) => void
}) {
  const [cursorStack, setCursorStack] = useState<(string | undefined)[]>([undefined])
  const cursor = cursorStack[cursorStack.length - 1]
  const [selectedIds, setSelectedIds] = useState<string[]>([])
  const [isBulkRejectOpen, setIsBulkRejectOpen] = useState(false)

  const { data, isLoading } = useBusinessVerificationQueue('pending', cursor)
  const bulkApprove = useBulkApproveBusinessVerifications()
  const bulkReject = useBulkRejectBusinessVerifications()

  const rows = data?.data ?? []

  const handleNext = () => {
    if (data?.meta.next_cursor) setCursorStack((stack) => [...stack, data.meta.next_cursor as string])
  }
  const handlePrevious = () => {
    setCursorStack((stack) => (stack.length > 1 ? stack.slice(0, -1) : stack))
  }

  const toggleRow = (id: string) => {
    setSelectedIds((ids) => (ids.includes(id) ? ids.filter((x) => x !== id) : [...ids, id]))
  }
  const toggleAll = () => {
    setSelectedIds((ids) => (ids.length === rows.length ? [] : rows.map((row) => row.id)))
  }

  const reportBulkResult = (
    action: 'approved' | 'rejected',
    result: { succeeded: string[]; failed: { id: string; reason: string }[] },
  ) => {
    if (result.succeeded.length > 0) {
      toast.success(`${result.succeeded.length} application(s) ${action}.`)
    }
    if (result.failed.length > 0) {
      toast.error(`${result.failed.length} application(s) could not be ${action}.`)
    }
  }

  const handleBulkApprove = async () => {
    try {
      const result = await bulkApprove.mutateAsync(selectedIds)
      reportBulkResult('approved', result)
      setSelectedIds([])
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Bulk approve failed.')
    }
  }

  const handleBulkReject = async (reason: string) => {
    try {
      const result = await bulkReject.mutateAsync({ ids: selectedIds, reason })
      reportBulkResult('rejected', result)
      setSelectedIds([])
      setIsBulkRejectOpen(false)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Bulk reject failed.')
    }
  }

  const columns: TableColumn<BusinessVerification>[] = [
    {
      key: 'select',
      header: '',
      render: (row) => (
        <input
          type="checkbox"
          checked={selectedIds.includes(row.id)}
          onChange={() => toggleRow(row.id)}
          aria-label={`Select ${row.legal_name}`}
        />
      ),
    },
    { key: 'legal_name', header: 'Legal name', render: (row) => row.legal_name },
    { key: 'registration_number', header: 'Registration #', render: (row) => row.registration_number },
    { key: 'location', header: 'Location', render: (row) => `${row.city}, ${row.province}` },
    {
      key: 'owner',
      header: 'Owner',
      render: (row) => (row.owner ? `${row.owner.name} (${row.owner.email})` : '—'),
    },
    {
      key: 'created_at',
      header: 'Submitted',
      render: (row) => (row.created_at ? new Date(row.created_at).toLocaleDateString() : '—'),
    },
    {
      key: 'actions',
      header: '',
      render: (row) => (
        <Button variant="secondary" size="sm" onClick={() => onView(row.id)}>
          View
        </Button>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-4">
      {selectedIds.length > 0 && (
        <div className="flex items-center justify-between gap-3 rounded-md border border-blue-200 bg-blue-50 px-4 py-2">
          <span className="text-sm text-blue-800">{selectedIds.length} selected</span>
          <div className="flex gap-2">
            <Button
              variant="danger"
              size="sm"
              onClick={() => setIsBulkRejectOpen(true)}
              disabled={bulkReject.isPending}
            >
              Reject selected
            </Button>
            <Button
              variant="primary"
              size="sm"
              onClick={handleBulkApprove}
              isLoading={bulkApprove.isPending}
            >
              Approve selected
            </Button>
          </div>
        </div>
      )}

      {rows.length === 0 && !isLoading ? (
        <EmptyState title="No pending business applications" description="The queue is empty." />
      ) : (
        <>
          <div className="flex items-center gap-2 text-sm text-gray-500">
            <input
              type="checkbox"
              checked={rows.length > 0 && selectedIds.length === rows.length}
              onChange={toggleAll}
              aria-label="Select all"
            />
            Select all on this page
          </div>
          <Table
            columns={columns}
            rows={rows}
            getRowKey={(row) => row.id}
            emptyMessage={isLoading ? 'Loading…' : 'No pending applications.'}
          />
        </>
      )}

      <Pagination
        hasNextPage={Boolean(data?.meta.next_cursor)}
        hasPreviousPage={cursorStack.length > 1}
        onNext={handleNext}
        onPrevious={handlePrevious}
        isLoading={isLoading}
      />

      <BulkRejectModal
        isOpen={isBulkRejectOpen}
        count={selectedIds.length}
        isSubmitting={bulkReject.isPending}
        onClose={() => setIsBulkRejectOpen(false)}
        onSubmit={handleBulkReject}
      />
    </div>
  )
}

function FreelancerQueueTab({
  onView,
}: {
  onView: (id: string) => void
}) {
  const [cursorStack, setCursorStack] = useState<(string | undefined)[]>([undefined])
  const cursor = cursorStack[cursorStack.length - 1]
  const [selectedIds, setSelectedIds] = useState<string[]>([])
  const [isBulkRejectOpen, setIsBulkRejectOpen] = useState(false)

  const { data, isLoading } = useFreelancerVerificationQueue('pending', cursor)
  const bulkApprove = useBulkApproveFreelancerVerifications()
  const bulkReject = useBulkRejectFreelancerVerifications()

  const rows = data?.data ?? []

  const handleNext = () => {
    if (data?.meta.next_cursor) setCursorStack((stack) => [...stack, data.meta.next_cursor as string])
  }
  const handlePrevious = () => {
    setCursorStack((stack) => (stack.length > 1 ? stack.slice(0, -1) : stack))
  }

  const toggleRow = (id: string) => {
    setSelectedIds((ids) => (ids.includes(id) ? ids.filter((x) => x !== id) : [...ids, id]))
  }
  const toggleAll = () => {
    setSelectedIds((ids) => (ids.length === rows.length ? [] : rows.map((row) => row.id)))
  }

  const reportBulkResult = (
    action: 'approved' | 'rejected',
    result: { succeeded: string[]; failed: { id: string; reason: string }[] },
  ) => {
    if (result.succeeded.length > 0) {
      toast.success(`${result.succeeded.length} application(s) ${action}.`)
    }
    if (result.failed.length > 0) {
      toast.error(`${result.failed.length} application(s) could not be ${action}.`)
    }
  }

  const handleBulkApprove = async () => {
    try {
      const result = await bulkApprove.mutateAsync(selectedIds)
      reportBulkResult('approved', result)
      setSelectedIds([])
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Bulk approve failed.')
    }
  }

  const handleBulkReject = async (reason: string) => {
    try {
      const result = await bulkReject.mutateAsync({ ids: selectedIds, reason })
      reportBulkResult('rejected', result)
      setSelectedIds([])
      setIsBulkRejectOpen(false)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Bulk reject failed.')
    }
  }

  const columns: TableColumn<FreelancerVerification>[] = [
    {
      key: 'select',
      header: '',
      render: (row) => (
        <input
          type="checkbox"
          checked={selectedIds.includes(row.id)}
          onChange={() => toggleRow(row.id)}
          aria-label={`Select ${row.headline}`}
        />
      ),
    },
    { key: 'headline', header: 'Headline', render: (row) => row.headline },
    {
      key: 'user',
      header: 'Freelancer',
      render: (row) => (row.user ? `${row.user.name} (${row.user.email})` : '—'),
    },
    { key: 'years_experience', header: 'Experience', render: (row) => `${row.years_experience} yrs` },
    {
      key: 'created_at',
      header: 'Submitted',
      render: (row) => (row.created_at ? new Date(row.created_at).toLocaleDateString() : '—'),
    },
    {
      key: 'actions',
      header: '',
      render: (row) => (
        <Button variant="secondary" size="sm" onClick={() => onView(row.id)}>
          View
        </Button>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-4">
      {selectedIds.length > 0 && (
        <div className="flex items-center justify-between gap-3 rounded-md border border-blue-200 bg-blue-50 px-4 py-2">
          <span className="text-sm text-blue-800">{selectedIds.length} selected</span>
          <div className="flex gap-2">
            <Button
              variant="danger"
              size="sm"
              onClick={() => setIsBulkRejectOpen(true)}
              disabled={bulkReject.isPending}
            >
              Reject selected
            </Button>
            <Button
              variant="primary"
              size="sm"
              onClick={handleBulkApprove}
              isLoading={bulkApprove.isPending}
            >
              Approve selected
            </Button>
          </div>
        </div>
      )}

      {rows.length === 0 && !isLoading ? (
        <EmptyState title="No pending freelancer applications" description="The queue is empty." />
      ) : (
        <>
          <div className="flex items-center gap-2 text-sm text-gray-500">
            <input
              type="checkbox"
              checked={rows.length > 0 && selectedIds.length === rows.length}
              onChange={toggleAll}
              aria-label="Select all"
            />
            Select all on this page
          </div>
          <Table
            columns={columns}
            rows={rows}
            getRowKey={(row) => row.id}
            emptyMessage={isLoading ? 'Loading…' : 'No pending applications.'}
          />
        </>
      )}

      <Pagination
        hasNextPage={Boolean(data?.meta.next_cursor)}
        hasPreviousPage={cursorStack.length > 1}
        onNext={handleNext}
        onPrevious={handlePrevious}
        isLoading={isLoading}
      />

      <BulkRejectModal
        isOpen={isBulkRejectOpen}
        count={selectedIds.length}
        isSubmitting={bulkReject.isPending}
        onClose={() => setIsBulkRejectOpen(false)}
        onSubmit={handleBulkReject}
      />
    </div>
  )
}

export function VerificationQueuePage() {
  const [tab, setTab] = useState<Tab>('business')
  const [detail, setDetail] = useState<{ kind: Tab; id: string } | null>(null)

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <div className="flex items-center justify-between">
        <h1 className="text-lg font-semibold text-gray-900">Verification queue</h1>
        <Badge tone="info">Pending applications</Badge>
      </div>

      <div className="flex gap-1 border-b border-gray-200">
        <button
          type="button"
          onClick={() => setTab('business')}
          className={cn(
            'border-b-2 px-3 py-2 text-sm font-medium',
            tab === 'business'
              ? 'border-blue-600 text-blue-600'
              : 'border-transparent text-gray-500 hover:text-gray-700',
          )}
        >
          Business
        </button>
        <button
          type="button"
          onClick={() => setTab('freelancer')}
          className={cn(
            'border-b-2 px-3 py-2 text-sm font-medium',
            tab === 'freelancer'
              ? 'border-blue-600 text-blue-600'
              : 'border-transparent text-gray-500 hover:text-gray-700',
          )}
        >
          Freelancer
        </button>
      </div>

      {tab === 'business' ? (
        <BusinessQueueTab onView={(id) => setDetail({ kind: 'business', id })} />
      ) : (
        <FreelancerQueueTab onView={(id) => setDetail({ kind: 'freelancer', id })} />
      )}

      <VerificationDetailDrawer
        kind={detail?.kind ?? 'business'}
        id={detail?.id ?? null}
        onClose={() => setDetail(null)}
      />
    </div>
  )
}
