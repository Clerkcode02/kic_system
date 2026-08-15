import { useState } from 'react'
import { Badge, Card, Pagination, Table, type TableColumn } from '@/components'
import { useEarningsPage } from '../hooks/useEarnings'
import type { EarningRow } from '../types'

const COLUMNS: TableColumn<EarningRow>[] = [
  { key: 'milestone', header: 'Milestone', render: (row) => row.milestone_title ?? '—' },
  {
    key: 'amount',
    header: 'Amount',
    render: (row) => `$${row.amount} ${row.currency}`,
  },
  {
    key: 'platform_fee',
    header: 'Platform fee',
    render: (row) => `$${row.platform_fee_amount}`,
  },
  {
    key: 'net',
    header: 'Net',
    render: (row) => `$${row.net_amount}`,
  },
  {
    key: 'status',
    header: 'Status',
    render: (row) =>
      row.released ? (
        <Badge tone="success">Released</Badge>
      ) : (
        <Badge tone="warning">In escrow</Badge>
      ),
  },
  {
    key: 'created_at',
    header: 'Date',
    render: (row) => (row.created_at ? new Date(row.created_at).toLocaleDateString() : '—'),
  },
]

export function EarningsPage() {
  const [cursorStack, setCursorStack] = useState<(string | undefined)[]>([undefined])
  const currentCursor = cursorStack[cursorStack.length - 1]
  const { data, isLoading } = useEarningsPage(currentCursor)

  const earnings = data?.data ?? []
  const totalReleased = earnings
    .filter((row) => row.released)
    .reduce((sum, row) => sum + Number(row.net_amount), 0)

  const handleNext = () => {
    if (data?.meta.next_cursor) {
      setCursorStack((prev) => [...prev, data.meta.next_cursor as string])
    }
  }

  const handlePrevious = () => {
    setCursorStack((prev) => (prev.length > 1 ? prev.slice(0, -1) : prev))
  }

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <h1 className="text-lg font-semibold text-gray-900">Earnings</h1>

      <Card>
        <p className="text-sm text-gray-500">Released this page</p>
        <p className="text-2xl font-semibold text-gray-900">${totalReleased.toFixed(2)} CAD</p>
      </Card>

      <Table
        columns={COLUMNS}
        rows={earnings}
        getRowKey={(row) => row.id}
        emptyMessage={isLoading ? 'Loading…' : 'No earnings yet.'}
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
