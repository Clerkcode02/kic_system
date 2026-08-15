import { useInfiniteQuery, useQuery } from '@tanstack/react-query'
import { fetchService, fetchServices } from '../api/catalogApi'
import type { ServiceListFilters } from '../types'

export function useInfiniteServices(filters: ServiceListFilters) {
  return useInfiniteQuery({
    queryKey: ['catalog', 'services', filters] as const,
    queryFn: ({ pageParam }) => fetchServices(filters, pageParam),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.meta.next_cursor ?? undefined,
  })
}

export function useService(serviceId: string | undefined) {
  return useQuery({
    queryKey: ['catalog', 'service', serviceId] as const,
    queryFn: () => fetchService(serviceId as string),
    enabled: Boolean(serviceId),
  })
}
