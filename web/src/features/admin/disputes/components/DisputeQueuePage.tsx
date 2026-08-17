import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { Badge, Pagination, Select, Table, type TableColumn } from '@/components'
import { useDisputes } from '../hooks/useDisputes'
import type { Dispute, DisputeStatus } from '../types'

const STATUS_TONE: Record<DisputeStatus, 'neutral' | 'success' | 'warning' | 'danger' | 'info'> = {
  open: 'warning',
  under_review: 'info',
  resolved: 'success',
  closed: 'neutral',
}

const STATUS_OPTIONS = [
  { value: 'all', label: 'All statuses' },
  { value: 'open', label: 'Open' },
  { value: 'under_review', label: 'Under review' },
  { value: 'resolved', label: 'Resolved' },
  { value: 'closed', label: 'Closed' },
]

function truncateId(id: string | null) {
  if (!id) return 'Unassigned'
  return `${id.slice(0, 8)}…`
}

const columns: TableColumn<Dispute>[] = [
  {
    key: 'disputable',
    header: 'Related to',
    render: (dispute) => `${dispute.disputable_type} · ${truncateId(dispute.disputable_id)}`,
  },
  {
    key: 'status',
    header: 'Status',
    render: (dispute) => <Badge tone={STATUS_TONE[dispute.status]}>{dispute.status}</Badge>,
  },
  {
    key: 'assigned_admin_id',
    header: 'Assigned admin',
    render: (dispute) => truncateId(dispute.assigned_admin_id),
  },
  {
    key: 'created_at',
    header: 'Raised',
    render: (dispute) => (dispute.created_at ? new Date(dispute.created_at).toLocaleDateString() : '—'),
  },
  {
    key: 'view',
    header: '',
    render: (dispute) => (
      <Link to={`/admin/disputes/${dispute.id}`} className="text-sm font-medium text-blue-600 hover:underline">
        View
      </Link>
    ),
  },
]

export function DisputeQueuePage() {
  const [cursorStack, setCursorStack] = useState<(string | undefined)[]>([undefined])
  const [statusFilter, setStatusFilter] = useState<'all' | DisputeStatus>('all')
  const cursor = cursorStack[cursorStack.length - 1]
  const { data, isLoading } = useDisputes(cursor)

  const rows = useMemo(() => {
    const disputes = data?.data ?? []
    if (statusFilter === 'all') return disputes
    return disputes.filter((dispute) => dispute.status === statusFilter)
  }, [data, statusFilter])

  const handleNext = () => {
    if (data?.meta.next_cursor) setCursorStack((stack) => [...stack, data.meta.next_cursor as string])
  }
  const handlePrevious = () => {
    setCursorStack((stack) => (stack.length > 1 ? stack.slice(0, -1) : stack))
  }

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <div className="flex items-center justify-between gap-4">
        <h1 className="text-lg font-semibold text-gray-900">Disputes</h1>
        <div className="w-48">
          <Select
            aria-label="Filter by status"
            value={statusFilter}
            onChange={(event) => setStatusFilter(event.target.value as 'all' | DisputeStatus)}
            options={STATUS_OPTIONS}
          />
        </div>
      </div>

      <Table
        columns={columns}
        rows={rows}
        getRowKey={(dispute) => dispute.id}
        emptyMessage={isLoading ? 'Loading…' : 'No disputes found.'}
      />

      <Pagination
        hasNextPage={Boolean(data?.meta.next_cursor)}
        hasPreviousPage={cursorStack.length > 1}
        onNext={handleNext}
        onPrevious={handlePrevious}
        isLoading={isLoading}
      />
    </div>
  )
}
