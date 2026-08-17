import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { Card, EmptyState, Select, Skeleton } from '@/components'
import { useInfiniteBookings } from '../hooks/useBookings'
import type { BookingStatus } from '../types'
import { BOOKING_STATUS_LABELS, BookingStatusBadge } from './BookingStatusBadge'

const STATUS_FILTER_OPTIONS = [
  { value: '', label: 'All statuses' },
  ...(Object.entries(BOOKING_STATUS_LABELS) as [BookingStatus, string][]).map(([value, label]) => ({
    value,
    label,
  })),
]

export function BookingListPage() {
  const [status, setStatus] = useState<BookingStatus | ''>('')
  const { data, isLoading, isError, fetchNextPage, hasNextPage, isFetchingNextPage } =
    useInfiniteBookings(status || undefined)
  const sentinelRef = useRef<HTMLDivElement | null>(null)

  useEffect(() => {
    const sentinel = sentinelRef.current
    if (!sentinel) return
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && hasNextPage && !isFetchingNextPage) fetchNextPage()
      },
      { rootMargin: '200px' },
    )
    observer.observe(sentinel)
    return () => observer.disconnect()
  }, [fetchNextPage, hasNextPage, isFetchingNextPage])

  const bookings = data?.pages.flatMap((page) => page.data) ?? []

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <div className="flex items-center justify-between">
        <h1 className="text-lg font-semibold text-gray-900">Your bookings</h1>
        <Select
          aria-label="Filter by status"
          options={STATUS_FILTER_OPTIONS}
          value={status}
          onChange={(event) => setStatus(event.target.value as BookingStatus | '')}
        />
      </div>

      {isLoading && (
        <div className="flex flex-col gap-3">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} className="h-20 rounded-lg" />
          ))}
        </div>
      )}

      {isError && <EmptyState title="Couldn't load bookings" description="Please try again." />}

      {!isLoading && !isError && bookings.length === 0 && (
        <EmptyState
          title="No bookings yet"
          description="Browse services to request your first booking."
          action={
            <Link to="/services" className="text-sm font-medium text-blue-600 underline">
              Browse services
            </Link>
          }
        />
      )}

      <div className="flex flex-col gap-3">
        {bookings.map((booking) => (
          <Link key={booking.id} to={`/customer/bookings/${booking.id}`}>
            <Card className="flex items-center justify-between gap-4 transition hover:shadow-md">
              <div>
                <p className="font-medium text-gray-900">{booking.service.title}</p>
                <p className="text-sm text-gray-500">
                  {booking.provider.legal_name} · {booking.scheduled_date} ·{' '}
                  {booking.time_slot_start.slice(0, 5)}–{booking.time_slot_end.slice(0, 5)}
                </p>
                <p className="text-xs text-gray-400">#{booking.booking_number}</p>
              </div>
              <BookingStatusBadge status={booking.status} />
            </Card>
          </Link>
        ))}
      </div>
      <div ref={sentinelRef} className="h-4" />
      {isFetchingNextPage && (
        <p className="py-2 text-center text-sm text-gray-500">Loading more…</p>
      )}
    </div>
  )
}
