import { useInfiniteQuery, useQuery } from '@tanstack/react-query'
import { fetchBooking, fetchBookings } from '../api/bookingApi'
import type { BookingStatus } from '../types'

export function useInfiniteBookings(status: BookingStatus | undefined) {
  return useInfiniteQuery({
    queryKey: ['bookings', 'customer', status] as const,
    queryFn: ({ pageParam }) => fetchBookings(status, pageParam),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.meta.next_cursor ?? undefined,
  })
}

export function useBooking(bookingId: string | undefined) {
  return useQuery({
    queryKey: ['bookings', 'detail', bookingId] as const,
    queryFn: () => fetchBooking(bookingId as string),
    enabled: Boolean(bookingId),
  })
}
