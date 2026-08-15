import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  fetchMyBusiness,
  submitBusinessForVerification,
  updateMyBusiness,
} from '../api/businessApi'
import type { Business } from '../types'

const BUSINESS_QUERY_KEY = ['business', 'me'] as const

export function useMyBusiness() {
  return useQuery<Business>({
    queryKey: BUSINESS_QUERY_KEY,
    queryFn: fetchMyBusiness,
  })
}

export function useUpdateBusinessProfile() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: updateMyBusiness,
    onSuccess: (business) => {
      queryClient.setQueryData(BUSINESS_QUERY_KEY, business)
    },
  })
}

export function useSubmitBusinessForVerification() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: submitBusinessForVerification,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: BUSINESS_QUERY_KEY })
    },
  })
}
