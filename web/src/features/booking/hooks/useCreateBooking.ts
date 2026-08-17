import { useMutation, useQueryClient } from '@tanstack/react-query'
import { createBooking, createGuestBooking } from '../api/bookingApi'
import type { CreateBookingPayload } from '../types'
import type { CreateBookingRequestPayload } from '../types.guest'

export function useCreateBooking() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      payload,
      idempotencyKey,
    }: {
      payload: CreateBookingPayload
      idempotencyKey: string
    }) => createBooking(payload, idempotencyKey),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bookings'] })
    },
  })
}

/**
 * The anonymous submission of the same endpoint (SRS §6.1).
 *
 * No cache invalidation: a guest has no booking list to refresh, and the
 * one-time access token in the response must be handed to the caller rather
 * than cached — TanStack Query's cache is not where a credential belongs.
 */
export function useCreateGuestBooking() {
  return useMutation({
    mutationFn: ({
      payload,
      idempotencyKey,
    }: {
      payload: CreateBookingRequestPayload
      idempotencyKey: string
    }) => createGuestBooking(payload, idempotencyKey),
  })
}
