import { apiClient } from '@/lib/api'
import type {
  BookingDetail,
  BookingListItem,
  BookingStatus,
  CreateBookingPayload,
  CursorPage,
} from '../types'

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

export async function cancelBooking(bookingId: string, reason?: string): Promise<BookingDetail> {
  const { data } = await apiClient.patch<{ data: BookingDetail }>(`/bookings/${bookingId}/cancel`, {
    reason,
  })
  return data.data
}
