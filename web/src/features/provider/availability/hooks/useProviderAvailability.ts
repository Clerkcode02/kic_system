import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchAvailability, replaceAvailability } from '../api/availabilityApi'
import type { ReplaceAvailabilityPayload } from '../types'

export function useAvailability() {
  return useQuery({
    queryKey: ['provider', 'availability'] as const,
    queryFn: fetchAvailability,
  })
}

export function useReplaceAvailability() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: ReplaceAvailabilityPayload) => replaceAvailability(payload),
    onSuccess: (data) => {
      queryClient.setQueryData(['provider', 'availability'], data)
    },
  })
}
