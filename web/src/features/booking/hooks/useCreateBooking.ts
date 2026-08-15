import { useMutation, useQueryClient } from '@tanstack/react-query'
import { createBooking } from '../api/bookingApi'
import type { CreateBookingPayload } from '../types'

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
