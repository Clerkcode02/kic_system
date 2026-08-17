import { Fragment, useState } from 'react'
import { Badge, Button, Card, EmptyState, Pagination, Skeleton, Table, type TableColumn } from '@/components'
import { useFailedTransfers, usePayouts, useRetryFailedTransfer } from '../hooks/usePayouts'
import type { FailedTransferPayment, Payout, PayoutStatus } from '../types'

const STATUS_TONE: Record<PayoutStatus, 'neutral' | 'success' | 'warning' | 'danger' | 'info'> = {
  scheduled: 'neutral',
  processing: 'info',
  paid: 'success',
  failed: 'danger',
}

const GROUPED_COLUMNS = ['Provider', 'Amount', 'Status', 'Stripe transfer']

function RecentPayoutsTable({ payouts, isLoading }: { payouts: Payout[]; isLoading: boolean }) {
  if (isLoading) {
    return (
      <div className="flex flex-col gap-2">
        <Skeleton className="h-8 w-full" />
        <Skeleton className="h-8 w-full" />
        <Skeleton className="h-8 w-full" />
      </div>
    )
  }

  if (payouts.length === 0) {
    return <EmptyState title="No payouts yet." description="Payouts will appear here once scheduled." />
  }

  let lastDateLabel: string | null = null

  return (
    <div className="overflow-x-auto rounded-lg border border-gray-200">
      <table className="min-w-full divide-y divide-gray-200 text-sm">
        <thead className="bg-gray-50">
          <tr>
            {GROUPED_COLUMNS.map((header) => (
              <th key={header} scope="col" className="px-4 py-3 text-left font-medium text-gray-500">
                {header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100 bg-white">
          {payouts.map((payout) => {
            const dateLabel = new Date(payout.created_at).toLocaleDateString()
            const showHeader = dateLabel !== lastDateLabel
            lastDateLabel = dateLabel

            return (
              <Fragment key={payout.id}>
                {showHeader && (
                  <tr className="bg-gray-50">
                    <td colSpan={GROUPED_COLUMNS.length} className="px-4 py-2 font-semibold text-gray-700">
                      {dateLabel}
                    </td>
                  </tr>
                )}
                <tr>
                  <td className="px-4 py-3 text-gray-900">{payout.provider_name ?? '—'}</td>
                  <td className="px-4 py-3 text-gray-900">
                    ${payout.amount} {payout.currency}
                  </td>
                  <td className="px-4 py-3 text-gray-900">
                    <Badge tone={STATUS_TONE[payout.status]}>{payout.status}</Badge>
                  </td>
                  <td className="px-4 py-3 text-gray-900">{payout.stripe_transfer_id ?? '—'}</td>
                </tr>
              </Fragment>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}

export function PayoutMonitorPage() {
  const [payoutCursorStack, setPayoutCursorStack] = useState<(string | undefined)[]>([undefined])
  const payoutCursor = payoutCursorStack[payoutCursorStack.length - 1]
  const { data: payoutsData, isLoading: isPayoutsLoading } = usePayouts(payoutCursor)

  const [failedCursorStack, setFailedCursorStack] = useState<(string | undefined)[]>([undefined])
  const failedCursor = failedCursorStack[failedCursorStack.length - 1]
  const { data: failedData, isLoading: isFailedLoading } = useFailedTransfers(failedCursor)

  const retryMutation = useRetryFailedTransfer()

  const handlePayoutsNext = () => {
    if (payoutsData?.meta.next_cursor) {
      setPayoutCursorStack((stack) => [...stack, payoutsData.meta.next_cursor!])
    }
  }
  const handlePayoutsPrevious = () => {
    setPayoutCursorStack((stack) => (stack.length > 1 ? stack.slice(0, -1) : stack))
  }

  const handleFailedNext = () => {
    if (failedData?.meta.next_cursor) {
      setFailedCursorStack((stack) => [...stack, failedData.meta.next_cursor!])
    }
  }
  const handleFailedPrevious = () => {
    setFailedCursorStack((stack) => (stack.length > 1 ? stack.slice(0, -1) : stack))
  }

  const failedColumns: TableColumn<FailedTransferPayment>[] = [
    {
      key: 'created_at',
      header: 'Date',
      render: (payment) => new Date(payment.created_at).toLocaleDateString(),
    },
    {
      key: 'payable',
      header: 'Milestone',
      render: (payment) => payment.payable_id,
    },
    {
      key: 'amount',
      header: 'Amount',
      render: (payment) => `$${payment.amount} ${payment.currency}`,
    },
    {
      key: 'provider_net_amount',
      header: 'Provider net',
      render: (payment) => `$${payment.provider_net_amount} ${payment.currency}`,
    },
    {
      key: 'status',
      header: 'Status',
      render: () => <Badge tone="danger">failed</Badge>,
    },
    {
      key: 'actions',
      header: '',
      render: (payment) => {
        const isRetrying = retryMutation.isPending && retryMutation.variables === payment.id
        return (
          <Button
            variant="secondary"
            size="sm"
            isLoading={isRetrying}
            disabled={isRetrying}
            onClick={() => retryMutation.mutate(payment.id)}
          >
            Retry
          </Button>
        )
      },
    },
  ]

  return (
    <div className="flex flex-col gap-6 p-4 sm:p-6">
      <h1 className="text-lg font-semibold text-gray-900">Payout monitor</h1>

      <Card>
        <h2 className="mb-4 text-base font-semibold text-gray-900">Recent payouts</h2>
        <RecentPayoutsTable payouts={payoutsData?.data ?? []} isLoading={isPayoutsLoading} />
        <Pagination
          hasNextPage={Boolean(payoutsData?.meta.next_cursor)}
          hasPreviousPage={payoutCursorStack.length > 1}
          onNext={handlePayoutsNext}
          onPrevious={handlePayoutsPrevious}
          isLoading={isPayoutsLoading}
        />
      </Card>

      <Card>
        <h2 className="mb-4 text-base font-semibold text-gray-900">Failed milestone transfers</h2>
        <Table
          columns={failedColumns}
          rows={failedData?.data ?? []}
          getRowKey={(payment) => payment.id}
          emptyMessage={isFailedLoading ? 'Loading…' : 'No failed transfers.'}
        />
        <Pagination
          hasNextPage={Boolean(failedData?.meta.next_cursor)}
          hasPreviousPage={failedCursorStack.length > 1}
          onNext={handleFailedNext}
          onPrevious={handleFailedPrevious}
          isLoading={isFailedLoading}
        />
      </Card>
    </div>
  )
}
