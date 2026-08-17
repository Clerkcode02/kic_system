import { useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { useBookingWizardStore, EMPTY_ADDRESS } from '@/stores/bookingWizardStore'
import type { BookingDetail } from '../types'

/**
 * "Book again" (SRS §6.1 re-booking): prefills the wizard with the service,
 * provider and address from a previous booking, then drops the user into
 * the schedule step with the **date left blank**.
 *
 * The provider comes along implicitly — a service belongs to exactly one
 * business, so navigating to that service's wizard is what pins the
 * provider. There is no separate provider field to carry.
 */
export function useBookAgain() {
  const navigate = useNavigate()
  const prefill = useBookingWizardStore((state) => state.prefill)

  return useCallback(
    (booking: BookingDetail) => {
      const snapshot = booking.service_address

      prefill({
        serviceId: booking.service.id,
        // A booking always carries a denormalized address snapshot, so
        // re-booking works whether the original used a saved address or an
        // inline one — including a booking placed as a guest and later
        // claimed.
        address: snapshot
          ? {
              line1: snapshot.line1 ?? '',
              line2: snapshot.line2 ?? '',
              city: snapshot.city ?? '',
              province: snapshot.province ?? '',
              postal_code: snapshot.postal_code ?? '',
              lat: booking.address?.lat ?? null,
              lng: booking.address?.lng ?? null,
            }
          : EMPTY_ADDRESS,
        // Reuse the saved address row when there was one, so the user
        // doesn't have to re-confirm a pin they've already placed.
        addressId: booking.address?.id ?? null,
      })

      navigate(`/book/${booking.service.id}`)
    },
    [navigate, prefill],
  )
}
