import { useState } from 'react'
import { Card, Pagination, Table, type TableColumn } from '@/components'
import { MoneyFigure, StatusStamp } from '../../components'
import { formatMoney } from '@/lib/format/money'
import { useEarningsPage } from '../hooks/useEarnings'
import type { EarningRow } from '../types'

const COLUMNS: TableColumn<EarningRow>[] = [
  { key: 'milestone', header: 'Milestone', render: (row) => row.milestone_title ?? '—' },
  {
    key: 'amount',
    header: 'Amount',
    className: 'font-mono tabular-nums',
    render: (row) => <MoneyFigure amount={row.amount} />,
  },
  {
    key: 'platform_fee',
    header: 'Platform fee',
    className: 'font-mono tabular-nums text-docket-soft',
    render: (row) => `−${formatMoney(row.platform_fee_amount)}`,
  },
  {
    key: 'net',
    header: 'Net',
    className: 'font-mono font-semibold tabular-nums',
    render: (row) => <MoneyFigure amount={row.net_amount} />,
  },
  {
    key: 'status',
    header: 'Status',
    render: (row) =>
      row.released ? (
        <StatusStamp role="paid" animate={false}>
          Released
        </StatusStamp>
      ) : (
        <StatusStamp role="awaiting" animate={false}>
          In escrow
        </StatusStamp>
      ),
  },
  {
    key: 'created_at',
    header: 'Date',
    className: 'font-mono text-docket-soft',
    render: (row) => (row.created_at ? new Date(row.created_at).toLocaleDateString('en-CA') : '—'),
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
      <h1 className="font-mono text-xs font-bold uppercase tracking-widest text-docket-soft">
        Earnings statement
      </h1>

      <Card variant="ticket">
        <p className="font-mono text-xs font-bold uppercase tracking-widest text-docket-soft">
          Released this page
        </p>
        <MoneyFigure amount={totalReleased} className="text-2xl font-semibold text-docket-ink" />
      </Card>

      <Table
        variant="ledger"
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
