import { useState } from 'react'
import toast from 'react-hot-toast'
import { Button, Card } from '@/components'
import { ApiError } from '@/lib/api'
import { BookingStatusBadge } from './BookingStatusBadge'
import {
  useAcceptGuestQuotation,
  useCancelGuestBooking,
  useRejectGuestQuotation,
} from '../hooks/useGuestBooking'
import { FEE_APPLIES_STATUSES } from '../types'
import type { GuestBooking } from '../types.guest'

interface GuestBookingTrackerProps {
  booking: GuestBooking
}

const ACTIONABLE_QUOTATION_STATUSES = new Set(['sent'])

/**
 * The tracked view a booking access token opens: timeline, quotation
 * accept/reject, payment, and cancel-with-reason including the fee warning
 * (SRS §6.1).
 *
 * Nothing here renders optimistically. Every mutation writes the server's
 * own response into the cache, so a booking never shows as paid or
 * confirmed before the API — and, for payment, before the Stripe webhook —
 * says it is.
 */
export function GuestBookingTracker({ booking }: GuestBookingTrackerProps) {
  const [isCancelling, setIsCancelling] = useState(false)
  const [cancelReason, setCancelReason] = useState('')
  const [clientSecret, setClientSecret] = useState<string | null>(null)

  const accept = useAcceptGuestQuotation(booking.booking_number)
  const reject = useRejectGuestQuotation(booking.booking_number)
  const cancel = useCancelGuestBooking(booking.booking_number)

  const quotation = booking.quotation
  const canAct = quotation !== null && ACTIONABLE_QUOTATION_STATUSES.has(quotation.status)
  const feeWillApply = FEE_APPLIES_STATUSES.includes(booking.status)
  const isClosed = ['completed', 'cancelled', 'refunded', 'declined', 'quotation_expired'].includes(
    booking.status,
  )

  const handleAccept = async () => {
    if (!quotation) return
    try {
      const result = await accept.mutateAsync({
        quotationId: quotation.id,
        // One key per acceptance attempt, reused on retry by the mutation's
        // own retry path.
        idempotencyKey: crypto.randomUUID(),
      })
      setClientSecret(result.clientSecret)
      toast.success('Quotation accepted. Complete payment to confirm your booking.')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not accept this quotation.')
    }
  }

  const handleReject = async () => {
    if (!quotation) return
    try {
      await reject.mutateAsync({ quotationId: quotation.id })
      toast.success('Quotation declined. The provider can send a revised quote.')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not decline this quotation.')
    }
  }

  const handleCancel = async () => {
    try {
      const result = await cancel.mutateAsync(cancelReason || undefined)
      setIsCancelling(false)
      setCancelReason('')
      toast.success(
        result.cancellationFeeApplied
          ? 'Booking cancelled. A cancellation fee applies.'
          : 'Booking cancelled.',
      )
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not cancel this booking.')
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <Card className="flex flex-col gap-3">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-xl font-bold tracking-tight text-gray-900">
              Booking {booking.booking_number}
            </h1>
            <p className="mt-1 text-sm text-gray-600">
              {booking.service.title} with {booking.provider.display_name}
            </p>
          </div>
          <BookingStatusBadge status={booking.status} />
        </div>

        <dl className="grid gap-2 text-sm sm:grid-cols-2">
          <div>
            <dt className="text-gray-500">When</dt>
            <dd className="font-medium text-gray-900">
              {booking.scheduled_date}, {booking.time_slot_start.slice(0, 5)}–
              {booking.time_slot_end.slice(0, 5)}
            </dd>
          </div>
          <div>
            <dt className="text-gray-500">Where</dt>
            <dd className="font-medium text-gray-900">
              {booking.service_address.line1}
              {booking.service_address.line2 ? `, ${booking.service_address.line2}` : ''},{' '}
              {booking.service_address.city}, {booking.service_address.province}{' '}
              {booking.service_address.postal_code}
            </dd>
          </div>
          <div>
            <dt className="text-gray-500">Payment</dt>
            <dd className="font-medium capitalize text-gray-900">
              {booking.payment_status.replace('_', ' ')}
            </dd>
          </div>
        </dl>
      </Card>

      {quotation && (
        <Card className="flex flex-col gap-3">
          <div className="flex items-center justify-between gap-3">
            <h2 className="text-base font-semibold text-gray-900">
              Quotation
              {quotation.revision_number > 1 && (
                <span className="ml-2 text-sm font-normal text-gray-500">
                  revision {quotation.revision_number}
                </span>
              )}
            </h2>
            <span className="text-sm capitalize text-gray-600">{quotation.status}</span>
          </div>

          <ul className="flex flex-col gap-1 text-sm">
            {quotation.line_items.map((item, index) => (
              <li key={`${item.description}-${index}`} className="flex justify-between gap-4">
                <span className="text-gray-600">
                  {item.description}
                  {Number(item.quantity) !== 1 ? ` × ${item.quantity}` : ''}
                </span>
                <span className="font-medium text-gray-900">${item.amount}</span>
              </li>
            ))}
          </ul>

          <dl className="flex flex-col gap-1 border-t border-gray-200 pt-3 text-sm">
            <div className="flex justify-between gap-4">
              <dt className="text-gray-600">Labour</dt>
              <dd className="text-gray-900">${quotation.labor_cost}</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-gray-600">Materials</dt>
              <dd className="text-gray-900">${quotation.materials_cost}</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-gray-600">Platform fee</dt>
              <dd className="text-gray-900">${quotation.platform_fee}</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-gray-600">Tax</dt>
              <dd className="text-gray-900">${quotation.tax_amount}</dd>
            </div>
            {Number(quotation.discount_amount) > 0 && (
              <div className="flex justify-between gap-4">
                <dt className="text-gray-600">Discount</dt>
                <dd className="text-gray-900">−${quotation.discount_amount}</dd>
              </div>
            )}
            <div className="flex justify-between gap-4 border-t border-gray-200 pt-2 text-base">
              <dt className="font-semibold text-gray-900">Total</dt>
              <dd className="font-semibold text-gray-900">
                ${quotation.total_amount} {quotation.currency}
              </dd>
            </div>
          </dl>

          <p className="text-xs text-gray-500">
            Valid until {new Date(quotation.valid_until).toLocaleString()}
          </p>

          {canAct && (
            <div className="flex flex-col gap-2 sm:flex-row">
              <Button
                type="button"
                variant="secondary"
                onClick={handleReject}
                isLoading={reject.isPending}
                className="flex-1"
              >
                Decline
              </Button>
              <Button
                type="button"
                onClick={handleAccept}
                isLoading={accept.isPending}
                className="flex-1"
              >
                Accept and pay ${quotation.total_amount}
              </Button>
            </div>
          )}
        </Card>
      )}

      {clientSecret && (
        <Card className="flex flex-col gap-2 border-blue-200 bg-blue-50/50">
          <h2 className="text-base font-semibold text-gray-900">Complete your payment</h2>
          <p className="text-sm text-gray-700">
            Your booking is confirmed once the payment clears. This page updates when it does —
            it&apos;s deliberately not marked as paid before your bank confirms it.
          </p>
        </Card>
      )}

      <Card>
        <h2 className="text-base font-semibold text-gray-900">Progress</h2>
        <ol className="mt-3 flex flex-col gap-3">
          {booking.timeline.map((entry, index) => (
            <li key={`${entry.to_status}-${index}`} className="flex gap-3 text-sm">
              <span aria-hidden="true" className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-blue-500" />
              <span>
                <span className="font-medium capitalize text-gray-900">
                  {entry.to_status.replace(/_/g, ' ')}
                </span>
                {entry.note && <span className="block text-gray-600">{entry.note}</span>}
                <span className="block text-xs text-gray-400">
                  {new Date(entry.occurred_at).toLocaleString()}
                </span>
              </span>
            </li>
          ))}
        </ol>
      </Card>

      {!isClosed && (
        <Card className="flex flex-col gap-3">
          <h2 className="text-base font-semibold text-gray-900">Need to cancel?</h2>

          {feeWillApply && (
            <p className="rounded-md bg-amber-50 p-3 text-sm text-amber-900">
              You&apos;ve already accepted a quotation for this booking, so a cancellation fee
              applies. We&apos;ll confirm the exact amount by email.
            </p>
          )}

          {isCancelling ? (
            <>
              <label htmlFor="cancel-reason" className="text-sm font-medium text-gray-700">
                Reason (optional)
              </label>
              <textarea
                id="cancel-reason"
                rows={3}
                value={cancelReason}
                onChange={(event) => setCancelReason(event.target.value)}
                maxLength={500}
                className="rounded-md border border-gray-300 p-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <div className="flex gap-3">
                <Button
                  type="button"
                  variant="secondary"
                  onClick={() => setIsCancelling(false)}
                  className="flex-1"
                >
                  Keep booking
                </Button>
                <Button
                  type="button"
                  onClick={handleCancel}
                  isLoading={cancel.isPending}
                  className="flex-1"
                >
                  Confirm cancellation
                </Button>
              </div>
            </>
          ) : (
            <Button
              type="button"
              variant="secondary"
              onClick={() => setIsCancelling(true)}
              className="self-start"
            >
              Cancel this booking
            </Button>
          )}
        </Card>
      )}
    </div>
  )
}
