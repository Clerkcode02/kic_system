import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { createAddress, fetchMyAddresses } from '../api/addressApi'

const ADDRESSES_QUERY_KEY = ['addresses', 'me'] as const

/**
 * Saved addresses are a registered-customer feature (SRS §6.1). `enabled`
 * keeps the wizard from firing an authenticated request while an anonymous
 * visitor is booking — which would 401 and, worse, look like a session
 * problem rather than the normal guest state.
 */
export function useMyAddresses(enabled = true) {
  return useQuery({
    queryKey: ADDRESSES_QUERY_KEY,
    queryFn: fetchMyAddresses,
    enabled,
  })
}

export function useCreateAddress() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: createAddress,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ADDRESSES_QUERY_KEY })
    },
  })
}
