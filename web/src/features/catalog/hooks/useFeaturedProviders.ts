import { useQuery } from '@tanstack/react-query'
import { fetchServices } from '../api/catalogApi'
import type { BusinessSummary } from '../types'

const FEATURED_LIMIT = 6

/**
 * Providers to surface on the landing page.
 *
 * Derived from the public `/services` listing rather than a new endpoint:
 * every business reachable there is already verified (the API only lists
 * services from verified providers), and adding an unauthenticated route
 * outside the enumerated guest surface would be a spec change
 * (CLAUDE.md §2).
 */
export function useFeaturedProviders() {
  return useQuery({
    queryKey: ['catalog', 'featured-providers'] as const,
    queryFn: async (): Promise<BusinessSummary[]> => {
      const page = await fetchServices({ sort: 'newest' })

      // One card per business — a provider offering five services should
      // not fill the whole row.
      const seen = new Map<string, BusinessSummary>()
      for (const service of page.data) {
        if (!seen.has(service.business.id)) {
          seen.set(service.business.id, service.business)
        }
        if (seen.size >= FEATURED_LIMIT) break
      }

      return [...seen.values()]
    },
    staleTime: 5 * 60 * 1000,
  })
}
