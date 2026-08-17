import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { Button, Card, EmptyState, Skeleton } from '@/components'
import { QuotationPanel } from '@/features/quotation'
import { BookingPaymentPanel } from '@/features/payments'
import { useBooking } from '../hooks/useBookings'
import { AttachmentList } from './AttachmentList'
import { BookingStatusBadge } from './BookingStatusBadge'
import { CancelBookingModal } from './CancelBookingModal'
import { StatusTimeline } from './StatusTimeline'

const CANCELLABLE_STATUSES = new Set([
  'pending',
  'waiting_for_quotation',
  'quotation_sent',
  'waiting_for_customer',
  'accepted',
  'scheduled',
])

export function BookingDetailPage() {
  const { bookingId } = useParams<{ bookingId: string }>()
  const { data: booking, isLoading, isError } = useBooking(bookingId)
  const [isCancelOpen, setIsCancelOpen] = useState(false)

  if (isLoading) {
    return (
      <div className="p-6">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="mt-4 h-40 w-full" />
      </div>
    )
  }

  if (isError || !booking) {
    return (
      <div className="p-6">
        <EmptyState
          title="Booking not found"
          description="It may not exist or you may not have access."
        />
      </div>
    )
  }

  const canCancel = CANCELLABLE_STATUSES.has(booking.status)

  return (
    <div className="mx-auto flex max-w-2xl flex-col gap-4 p-4 sm:p-6">
      <Card className="flex flex-col gap-2">
        <div className="flex items-start justify-between gap-2">
          <div>
            <h1 className="text-lg font-semibold text-gray-900">{booking.service.title}</h1>
            <p className="text-sm text-gray-500">
              #{booking.booking_number} · {booking.provider.legal_name}
            </p>
          </div>
          <BookingStatusBadge status={booking.status} />
        </div>
        <div className="grid grid-cols-2 gap-2 text-sm">
          <div>
            <p className="text-gray-500">Date</p>
            <p className="font-medium text-gray-900">{booking.scheduled_date}</p>
          </div>
          <div>
            <p className="text-gray-500">Time</p>
            <p className="font-medium text-gray-900">
              {booking.time_slot_start.slice(0, 5)}–{booking.time_slot_end.slice(0, 5)}
            </p>
          </div>
          {booking.address && (
            <div className="col-span-2">
              <p className="text-gray-500">Location</p>
              <p className="font-medium text-gray-900">
                {booking.address.street}, {booking.address.city}, {booking.address.state_province}
              </p>
            </div>
          )}
          {booking.notes && (
            <div className="col-span-2">
              <p className="text-gray-500">Notes</p>
              <p className="font-medium text-gray-900">{booking.notes}</p>
            </div>
          )}
        </div>
        {canCancel && (
          <Button
            type="button"
            variant="danger"
            size="sm"
            onClick={() => setIsCancelOpen(true)}
            className="mt-2 self-start"
          >
            Cancel booking
          </Button>
        )}
      </Card>

      {booking.quotations.length > 0 && (
        <QuotationPanel bookingId={booking.id} quotations={booking.quotations} />
      )}

      <BookingPaymentPanel booking={booking} />

      <Card className="flex flex-col gap-3">
        <h2 className="text-sm font-semibold text-gray-900">Status history</h2>
        <StatusTimeline history={booking.status_history} />
      </Card>

      <Card className="flex flex-col gap-3">
        <h2 className="text-sm font-semibold text-gray-900">Attachments</h2>
        <AttachmentList attachments={booking.attachments} />
      </Card>

      <CancelBookingModal
        bookingId={booking.id}
        status={booking.status}
        isOpen={isCancelOpen}
        onClose={() => setIsCancelOpen(false)}
      />
    </div>
  )
}
