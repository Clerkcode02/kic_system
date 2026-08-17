import { apiClient } from '@/lib/api'
import type { GuestBooking, GuestBookingCreated } from '../types.guest'

/**
 * The guest surface (SRS §6.1). Every path here is authorized by the
 * `X-Booking-Token` header, attached centrally by the credential strategy
 * in `lib/api/authStrategy` — no call site handles the token itself.
 */

export async function fetchGuestBooking(bookingNumber: string): Promise<GuestBooking> {
  const { data } = await apiClient.get<{ data: GuestBooking }>(
    `/guest/bookings/${encodeURIComponent(bookingNumber)}`,
  )
  return data.data
}

export async function cancelGuestBooking(
  bookingNumber: string,
  reason?: string,
): Promise<{ booking: GuestBooking; cancellationFeeApplied: boolean }> {
  const { data } = await apiClient.patch<{
    data: GuestBooking
    meta: { cancellation_fee_applied: boolean }
  }>(`/guest/bookings/${encodeURIComponent(bookingNumber)}/cancel`, { reason })

  return { booking: data.data, cancellationFeeApplied: data.meta.cancellation_fee_applied }
}

export async function acceptGuestQuotation(
  quotationId: string,
  idempotencyKey: string,
): Promise<{ booking: GuestBooking; clientSecret: string | null }> {
  const { data } = await apiClient.post<{
    data: GuestBooking
    meta: { payment: { client_secret: string | null } }
  }>(`/guest/quotations/${encodeURIComponent(quotationId)}/accept`, undefined, {
    headers: { 'Idempotency-Key': idempotencyKey },
  })

  return { booking: data.data, clientSecret: data.meta.payment.client_secret }
}

export async function rejectGuestQuotation(
  quotationId: string,
  reason?: string,
): Promise<GuestBooking> {
  const { data } = await apiClient.post<{ data: GuestBooking }>(
    `/guest/quotations/${encodeURIComponent(quotationId)}/reject`,
    { reason },
  )
  return data.data
}

export async function createGuestPaymentIntent(
  idempotencyKey: string,
): Promise<{ clientSecret: string | null }> {
  const { data } = await apiClient.post<{ meta: { client_secret: string | null } }>(
    '/guest/payments/intents',
    undefined,
    { headers: { 'Idempotency-Key': idempotencyKey } },
  )
  return { clientSecret: data.meta.client_secret }
}

/**
 * Always resolves on a well-formed request, whether or not anything
 * matched — the API returns an identical 202 either way so it can't be used
 * to discover which booking numbers exist (SRS §6.1). The UI must render
 * the same confirmation regardless.
 */
export async function requestGuestTrackingLink(
  bookingNumber: string,
  email: string,
): Promise<void> {
  await apiClient.post('/guest/bookings/lookup', { booking_number: bookingNumber, email })
}

/** Narrows the creation response, which carries the one-time token in meta. */
export function readGuestCreationResponse(payload: {
  data: GuestBooking
  meta?: { access_token?: string }
}): GuestBookingCreated {
  return {
    booking: payload.data,
    accessToken: payload.meta?.access_token ?? '',
  }
}
