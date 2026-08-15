import { useMutation, useQueryClient } from '@tanstack/react-query'
import { acceptQuotation, rejectQuotation } from '../api/quotationApi'

export function useAcceptQuotation(bookingId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      quotationId,
      idempotencyKey,
    }: {
      quotationId: string
      idempotencyKey: string
    }) => acceptQuotation(quotationId, idempotencyKey),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bookings', 'detail', bookingId] })
    },
  })
}

export function useRejectQuotation(bookingId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ quotationId, reason }: { quotationId: string; reason?: string }) =>
      rejectQuotation(quotationId, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bookings', 'detail', bookingId] })
    },
  })
}
