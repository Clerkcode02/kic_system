import { apiClient } from '@/lib/api'
import type { BookingDetail, BookingListItem, BookingStatus, CursorPage } from '@/features/booking/types'

/**
 * Provider-side variant of the customer `fetchBookings`/`fetchBooking` in
 * `features/booking/api/bookingApi.ts` — same `/bookings` endpoints, just
 * `role: 'provider'` instead of `role: 'customer'` so the API scopes the
 * queue to bookings against this provider's business.
 */
export async function fetchProviderBookings(
  status: BookingStatus | undefined,
  cursor: string | undefined,
): Promise<CursorPage<BookingListItem>> {
  const { data } = await apiClient.get<CursorPage<BookingListItem>>('/bookings', {
    params: { role: 'provider', status, cursor },
  })
  return data
}

export async function fetchProviderBooking(bookingId: string): Promise<BookingDetail> {
  const { data } = await apiClient.get<{ data: BookingDetail }>(`/bookings/${bookingId}`)
  return data.data
}
