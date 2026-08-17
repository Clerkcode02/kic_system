import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  acceptGuestQuotation,
  cancelGuestBooking,
  fetchGuestBooking,
  rejectGuestQuotation,
  requestGuestTrackingLink,
} from '../api/guestBookingApi'

export const guestBookingKey = (bookingNumber: string) =>
  ['guest', 'booking', bookingNumber] as const

export function useGuestBooking(bookingNumber: string | null, enabled: boolean) {
  return useQuery({
    queryKey: guestBookingKey(bookingNumber ?? ''),
    queryFn: () => fetchGuestBooking(bookingNumber as string),
    enabled: Boolean(bookingNumber) && enabled,
    // A dead token is a permanent 404, not a transient failure — retrying
    // just delays the fallback to the lookup form.
    retry: false,
  })
}

export function useAcceptGuestQuotation(bookingNumber: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      quotationId,
      idempotencyKey,
    }: {
      quotationId: string
      idempotencyKey: string
    }) => acceptGuestQuotation(quotationId, idempotencyKey),
    onSuccess: ({ booking }) => {
      // Written from the server's response, never optimistically: a
      // booking must never render as paid or confirmed before the API says
      // so (SRS §6.1 / CLAUDE.md §2 — no client-trusted state).
      queryClient.setQueryData(guestBookingKey(bookingNumber), booking)
    },
  })
}

export function useRejectGuestQuotation(bookingNumber: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ quotationId, reason }: { quotationId: string; reason?: string }) =>
      rejectGuestQuotation(quotationId, reason),
    onSuccess: (booking) => {
      queryClient.setQueryData(guestBookingKey(bookingNumber), booking)
    },
  })
}

export function useCancelGuestBooking(bookingNumber: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (reason?: string) => cancelGuestBooking(bookingNumber, reason),
    onSuccess: ({ booking }) => {
      queryClient.setQueryData(guestBookingKey(bookingNumber), booking)
    },
  })
}

export function useRequestTrackingLink() {
  return useMutation({
    mutationFn: ({ bookingNumber, email }: { bookingNumber: string; email: string }) =>
      requestGuestTrackingLink(bookingNumber, email),
  })
}
