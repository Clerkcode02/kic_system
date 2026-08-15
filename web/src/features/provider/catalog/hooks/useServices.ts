import { useInfiniteQuery, useQuery } from '@tanstack/react-query'
import { fetchMyServices, fetchService } from '../api/serviceApi'

export function useInfiniteServices() {
  return useInfiniteQuery({
    queryKey: ['provider', 'services'] as const,
    queryFn: ({ pageParam }) => fetchMyServices(pageParam),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.meta.next_cursor ?? undefined,
  })
}

export function useService(serviceId: string | undefined) {
  return useQuery({
    queryKey: ['provider', 'services', 'detail', serviceId] as const,
    queryFn: () => fetchService(serviceId as string),
    enabled: Boolean(serviceId),
  })
}
