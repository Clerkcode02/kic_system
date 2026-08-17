import { apiClient } from '@/lib/api'
import type {
  BookingDetail,
  BookingListItem,
  BookingStatus,
  CreateBookingPayload,
  CursorPage,
} from '../types'
import type {
  CreateBookingRequestPayload,
  GuestBooking,
  GuestBookingCreated,
} from '../types.guest'
import { readGuestCreationResponse } from './guestBookingApi'

export async function fetchBookings(
  status: BookingStatus | undefined,
  cursor: string | undefined,
): Promise<CursorPage<BookingListItem>> {
  const { data } = await apiClient.get<CursorPage<BookingListItem>>('/bookings', {
    params: { role: 'customer', status, cursor },
  })
  return data
}

export async function fetchBooking(bookingId: string): Promise<BookingDetail> {
  const { data } = await apiClient.get<{ data: BookingDetail }>(`/bookings/${bookingId}`)
  return data.data
}

export async function createBooking(
  payload: CreateBookingPayload,
  idempotencyKey: string,
): Promise<BookingDetail> {
  const { data } = await apiClient.post<{ data: BookingDetail }>('/bookings', payload, {
    headers: { 'Idempotency-Key': idempotencyKey },
  })
  return data.data
}

/**
 * The same `POST /bookings` endpoint, submitted anonymously (SRS §6.1).
 * The response is the reduced guest resource plus the one-time access
 * token in `meta` — a different shape from the registered path, which is
 * why this has its own signature rather than a flag on `createBooking`.
 */
export async function createGuestBooking(
  payload: CreateBookingRequestPayload,
  idempotencyKey: string,
): Promise<GuestBookingCreated> {
  const { data } = await apiClient.post<{ data: GuestBooking; meta?: { access_token?: string } }>(
    '/bookings',
    payload,
    { headers: { 'Idempotency-Key': idempotencyKey } },
  )
  return readGuestCreationResponse(data)
}

export async function cancelBooking(bookingId: string, reason?: string): Promise<BookingDetail> {
  const { data } = await apiClient.patch<{ data: BookingDetail }>(`/bookings/${bookingId}/cancel`, {
    reason,
  })
  return data.data
}
