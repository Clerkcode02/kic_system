import { useQuery } from '@tanstack/react-query'
import { fetchPayouts } from '../api/earningsApi'

export function usePayouts(cursor: string | undefined) {
  return useQuery({
    queryKey: ['provider', 'earnings', 'payouts', cursor] as const,
    queryFn: () => fetchPayouts(cursor),
    placeholderData: (previous) => previous,
  })
}
