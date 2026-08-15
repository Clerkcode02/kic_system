import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { fetchMyEarnings } from '../api/earningsApi'

export function useEarningsPage(cursor: string | undefined) {
  return useQuery({
    queryKey: ['freelance', 'earnings', cursor] as const,
    queryFn: () => fetchMyEarnings(cursor),
    placeholderData: keepPreviousData,
  })
}
