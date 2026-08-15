import { Link } from 'react-router-dom'
import { Card, EmptyState, Skeleton } from '@/components'
import { BookingStatusBadge } from '@/features/booking/components/BookingStatusBadge'
import type { BookingListItem } from '@/features/booking/types'
import { useProviderDashboard } from '../hooks/useProviderDashboard'

function BookingRow({ booking }: { booking: BookingListItem }) {
  return (
    <Link
      to={`/provider/bookings/${booking.id}`}
      className="flex items-center justify-between gap-3 rounded-md border border-gray-100 px-3 py-2 hover:bg-gray-50"
    >
      <div>
        <p className="text-sm font-medium text-gray-900">{booking.service.title}</p>
        <p className="text-xs text-gray-500">
          {booking.customer.name} · {booking.scheduled_date} ·{' '}
          {booking.time_slot_start.slice(0, 5)}–{booking.time_slot_end.slice(0, 5)}
        </p>
      </div>
      <BookingStatusBadge status={booking.status} />
    </Link>
  )
}

function BookingListCard({
  title,
  bookings,
  emptyMessage,
}: {
  title: string
  bookings: BookingListItem[]
  emptyMessage: string
}) {
  return (
    <Card className="flex flex-col gap-3">
      <h2 className="text-sm font-semibold text-gray-900">{title}</h2>
      {bookings.length === 0 ? (
        <p className="text-sm text-gray-500">{emptyMessage}</p>
      ) : (
        <div className="flex flex-col gap-2">
          {bookings.map((booking) => (
            <BookingRow key={booking.id} booking={booking} />
          ))}
        </div>
      )}
    </Card>
  )
}

export function ProviderDashboardHome() {
  const { data, isLoading, isError } = useProviderDashboard()

  if (isLoading) {
    return (
      <div className="flex flex-col gap-4 p-4 sm:p-6">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="h-40 w-full" />
        <Skeleton className="h-40 w-full" />
      </div>
    )
  }

  if (isError || !data) {
    return (
      <div className="p-4 sm:p-6">
        <EmptyState title="Couldn't load your dashboard" description="Please try again." />
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <h1 className="text-lg font-semibold text-gray-900">Dashboard</h1>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <Card className="flex flex-col gap-1">
          <p className="text-xs font-medium text-gray-500">Today's schedule</p>
          <p className="text-2xl font-semibold text-gray-900">{data.today_schedule.length}</p>
        </Card>
        <Card className="flex flex-col gap-1">
          <p className="text-xs font-medium text-gray-500">Pending quotations</p>
          <p className="text-2xl font-semibold text-gray-900">{data.pending_quotations.length}</p>
        </Card>
        <Card className="flex flex-col gap-1">
          <p className="text-xs font-medium text-gray-500">Lifetime earnings</p>
          <p className="text-2xl font-semibold text-gray-900">
            ${data.earnings.total} <span className="text-sm font-normal">{data.earnings.currency}</span>
          </p>
        </Card>
      </div>

      <BookingListCard
        title="Today's schedule"
        bookings={data.today_schedule}
        emptyMessage="Nothing scheduled for today."
      />

      <BookingListCard
        title="Pending quotation requests"
        bookings={data.pending_quotations}
        emptyMessage="No bookings waiting on a quotation."
      />

      <BookingListCard
        title="Upcoming bookings"
        bookings={data.upcoming_bookings}
        emptyMessage="No upcoming bookings."
      />

      <Card className="flex flex-col gap-3">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-semibold text-gray-900">Recent payouts</h2>
          <Link to="/provider/earnings" className="text-xs font-medium text-blue-600 underline">
            View all
          </Link>
        </div>
        {data.earnings.recent_payouts.length === 0 ? (
          <p className="text-sm text-gray-500">No payouts yet.</p>
        ) : (
          <div className="flex flex-col gap-2">
            {data.earnings.recent_payouts.map((payout) => (
              <div key={payout.id} className="flex items-center justify-between text-sm">
                <span className="text-gray-600">
                  {payout.created_at ? new Date(payout.created_at).toLocaleDateString() : '—'}
                </span>
                <span className="font-medium text-gray-900">
                  ${payout.amount} {payout.currency}
                </span>
              </div>
            ))}
          </div>
        )}
      </Card>
    </div>
  )
}
