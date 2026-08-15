import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { createAddress, fetchMyAddresses } from '../api/addressApi'

const ADDRESSES_QUERY_KEY = ['addresses', 'me'] as const

export function useMyAddresses() {
  return useQuery({
    queryKey: ADDRESSES_QUERY_KEY,
    queryFn: fetchMyAddresses,
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
