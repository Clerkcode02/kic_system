import { useEffect, useRef, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { fetchBooking } from '@/features/booking/api/bookingApi'
import type { BookingPaymentStatus, BookingStatus } from '@/features/booking/types'

/**
 * Polls the same booking-detail query the rest of the app reads
 * (CapturePayment moves Accepted -> Scheduled on the webhook, and flips
 * payment_status to 'paid' once the succeeded sum covers the quotation
 * total — see api/app/Domain/Payment/Actions/CapturePayment.php). A
 * baseline is snapshotted when polling starts so this only reports
 * "confirmed" on a change from that snapshot, not on the booking already
 * having a matching status before this particular payment (e.g. a
 * remainder payment on a booking that's already 'scheduled').
 */
export function useBookingPaymentConfirmation(bookingId: string, active: boolean): boolean {
  const [isConfirmed, setIsConfirmed] = useState(false)
  const baseline = useRef<{ status: BookingStatus; paymentStatus: BookingPaymentStatus } | null>(null)

  const { data } = useQuery({
    queryKey: ['bookings', 'detail', bookingId] as const,
    queryFn: () => fetchBooking(bookingId),
    enabled: active,
    refetchInterval: active && !isConfirmed ? 3000 : false,
  })

  useEffect(() => {
    if (!active) {
      baseline.current = null
      setIsConfirmed(false)
    }
  }, [active])

  useEffect(() => {
    if (!active || !data) return

    if (baseline.current === null) {
      baseline.current = { status: data.status, paymentStatus: data.payment_status }
      return
    }

    const changed =
      data.status !== baseline.current.status ||
      (data.payment_status !== baseline.current.paymentStatus && data.payment_status === 'paid')

    if (changed) setIsConfirmed(true)
  }, [active, data])

  return isConfirmed
}
