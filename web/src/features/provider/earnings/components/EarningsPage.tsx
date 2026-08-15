import { useState } from 'react'
import { Badge, Pagination, Table, type TableColumn } from '@/components'
import { usePayouts } from '../hooks/useEarnings'
import type { Payout, PayoutStatus } from '../types'
import { StripeConnectBanner } from './StripeConnectBanner'

const STATUS_TONE: Record<PayoutStatus, 'neutral' | 'success' | 'warning' | 'danger' | 'info'> = {
  scheduled: 'neutral',
  processing: 'info',
  paid: 'success',
  failed: 'danger',
}

const columns: TableColumn<Payout>[] = [
  {
    key: 'created_at',
    header: 'Date',
    render: (payout) => (payout.created_at ? new Date(payout.created_at).toLocaleDateString() : '—'),
  },
  {
    key: 'amount',
    header: 'Amount',
    render: (payout) => `$${payout.amount} ${payout.currency}`,
  },
  {
    key: 'status',
    header: 'Status',
    render: (payout) => <Badge tone={STATUS_TONE[payout.status]}>{payout.status}</Badge>,
  },
  {
    key: 'stripe_transfer_id',
    header: 'Stripe transfer',
    render: (payout) => payout.stripe_transfer_id ?? '—',
  },
]

export function EarningsPage() {
  const [cursorStack, setCursorStack] = useState<(string | undefined)[]>([undefined])
  const cursor = cursorStack[cursorStack.length - 1]
  const { data, isLoading } = usePayouts(cursor)

  const handleNext = () => {
    if (data?.meta.next_cursor) setCursorStack((stack) => [...stack, data.meta.next_cursor!])
  }
  const handlePrevious = () => {
    setCursorStack((stack) => (stack.length > 1 ? stack.slice(0, -1) : stack))
  }

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <h1 className="text-lg font-semibold text-gray-900">Earnings</h1>

      <StripeConnectBanner />

      <Table
        columns={columns}
        rows={data?.data ?? []}
        getRowKey={(payout) => payout.id}
        emptyMessage={isLoading ? 'Loading…' : 'No payouts yet.'}
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
