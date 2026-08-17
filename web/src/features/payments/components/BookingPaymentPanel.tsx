import { useEffect, useState } from 'react'
import toast from 'react-hot-toast'
import { Button, Card } from '@/components'
import { ApiError } from '@/lib/api'
import { formatMoney } from '@/lib/format/money'
import type { BookingDetail } from '@/features/booking/types'
import { useIdempotencyKey } from '@/features/booking/hooks/useIdempotencyKey'
import { createPaymentIntent } from '../api/paymentsApi'
import { useBookingPaymentConfirmation } from '../hooks/useBookingPaymentConfirmation'
import { loadPendingCheckout, type PendingCheckout } from '../lib/pendingCheckout'
import { PaymentCheckoutModal } from './PaymentCheckoutModal'

const NEEDS_PAYMENT_STATUSES = new Set<BookingDetail['status']>(['accepted', 'scheduled', 'in_progress'])

interface BookingPaymentPanelProps {
  booking: BookingDetail
}

/**
 * Handles the payment paths QuotationPanel's inline checkout doesn't cover:
 * resuming a payment after a page refresh (recovered from sessionStorage —
 * see lib/pendingCheckout, "refreshing mid-payment doesn't create a second
 * charge") and paying a remainder balance after a deposit. Both go through
 * the generic POST /payments/intents endpoint, which recomputes the
 * still-unpaid amount server-side.
 */
export function BookingPaymentPanel({ booking }: BookingPaymentPanelProps) {
  const [checkout, setCheckout] = useState<{ paymentId: string; clientSecret: string; amount: string } | null>(
    null,
  )
  const [isCreating, setIsCreating] = useState(false)
  const { key: idempotencyKey, renew } = useIdempotencyKey()
  const isConfirmed = useBookingPaymentConfirmation(booking.id, checkout !== null)

  const needsPayment =
    NEEDS_PAYMENT_STATUSES.has(booking.status) &&
    (booking.payment_status === 'unpaid' || booking.payment_status === 'partial')

  const [resumable, setResumable] = useState<PendingCheckout | null>(null)

  useEffect(() => {
    if (!needsPayment) {
      setResumable(null)
      return
    }
    setResumable(loadPendingCheckout('booking', booking.id))
  }, [needsPayment, booking.id])

  if (!needsPayment) return null

  const handleResume = () => {
    if (!resumable) return
    setCheckout(resumable)
  }

  const handlePayNow = async () => {
    setIsCreating(true)
    try {
      const result = await createPaymentIntent('booking', booking.id, idempotencyKey)
      if (!result.clientSecret) {
        toast.error('Could not start this payment. Please try again.')
        return
      }
      setCheckout({
        paymentId: result.payment.id,
        clientSecret: result.clientSecret,
        amount: result.payment.amount,
      })
      renew()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not start this payment.')
    } finally {
      setIsCreating(false)
    }
  }

  return (
    <Card className="flex flex-col gap-3">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-semibold text-gray-900">
          {booking.payment_status === 'partial' ? 'Remaining balance' : 'Payment'}
        </h2>
      </div>
      <p className="text-sm text-gray-500">
        {booking.payment_status === 'partial'
          ? 'Your deposit was received. Pay the remaining balance to complete this booking.'
          : 'This booking is waiting on payment to be confirmed.'}
      </p>
      {resumable ? (
        <Button type="button" onClick={handleResume} className="self-start">
          Resume payment ({formatMoney(resumable.amount)})
        </Button>
      ) : (
        <Button type="button" isLoading={isCreating} onClick={handlePayNow} className="self-start">
          {booking.payment_status === 'partial' ? 'Pay remaining balance' : 'Pay now'}
        </Button>
      )}

      {checkout && (
        <PaymentCheckoutModal
          isOpen
          onClose={() => setCheckout(null)}
          title="Complete payment"
          payableType="booking"
          payableId={booking.id}
          paymentId={checkout.paymentId}
          clientSecret={checkout.clientSecret}
          amount={checkout.amount}
          isConfirmed={isConfirmed}
        />
      )}
    </Card>
  )
}
