import { useMutation, useQueryClient } from '@tanstack/react-query'
import { cancelBooking } from '../api/bookingApi'

export function useCancelBooking(bookingId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (reason: string | undefined) => cancelBooking(bookingId, reason),
    onSuccess: (booking) => {
      queryClient.setQueryData(['bookings', 'detail', bookingId], booking)
      queryClient.invalidateQueries({ queryKey: ['bookings', 'customer'] })
    },
  })
}
