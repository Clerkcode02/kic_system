import { useQuery } from '@tanstack/react-query'
import { fetchProviderAvailability } from '../api/availabilityApi'

export function useProviderAvailability(businessId: string | undefined, date: string) {
  return useQuery({
    queryKey: ['availability', businessId, date] as const,
    queryFn: () => fetchProviderAvailability(businessId as string, date),
    enabled: Boolean(businessId && date),
  })
}
