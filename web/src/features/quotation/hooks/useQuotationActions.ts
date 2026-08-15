import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  acceptQuotation,
  rejectQuotation,
  reviseQuotation,
  sendQuotation,
  type QuotationBuilderPayload,
} from '../api/quotationApi'

export function useSendQuotation(bookingId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: QuotationBuilderPayload) => sendQuotation(bookingId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bookings', 'detail', bookingId] })
      queryClient.invalidateQueries({ queryKey: ['bookings', 'provider'] })
      queryClient.invalidateQueries({ queryKey: ['provider', 'dashboard'] })
    },
  })
}

export function useReviseQuotation(bookingId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      quotationId,
      payload,
    }: {
      quotationId: string
      payload: QuotationBuilderPayload
    }) => reviseQuotation(quotationId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bookings', 'detail', bookingId] })
      queryClient.invalidateQueries({ queryKey: ['bookings', 'provider'] })
      queryClient.invalidateQueries({ queryKey: ['provider', 'dashboard'] })
    },
  })
}

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
