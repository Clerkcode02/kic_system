import { useQuery } from '@tanstack/react-query'
import { fetchCategories } from '../api/catalogApi'

export function useCategories() {
  return useQuery({
    queryKey: ['catalog', 'categories'] as const,
    queryFn: fetchCategories,
    staleTime: 5 * 60 * 1000,
  })
}
