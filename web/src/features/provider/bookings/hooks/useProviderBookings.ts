import { useInfiniteQuery, useQuery } from '@tanstack/react-query'
import type { BookingStatus } from '@/features/booking/types'
import { fetchProviderBooking, fetchProviderBookings } from '../api/providerBookingApi'

export function useInfiniteProviderBookings(status: BookingStatus | undefined) {
  return useInfiniteQuery({
    queryKey: ['bookings', 'provider', status] as const,
    queryFn: ({ pageParam }) => fetchProviderBookings(status, pageParam),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.meta.next_cursor ?? undefined,
  })
}

export function useProviderBooking(bookingId: string | undefined) {
  return useQuery({
    queryKey: ['bookings', 'detail', bookingId] as const,
    queryFn: () => fetchProviderBooking(bookingId as string),
    enabled: Boolean(bookingId),
  })
}
