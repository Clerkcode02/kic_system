import { useMutation, useQuery } from '@tanstack/react-query'
import { createStripeOnboardingLink, fetchStripeConnectStatus } from '../api/earningsApi'

export function useStripeConnectStatus() {
  return useQuery({
    queryKey: ['provider', 'stripe', 'status'] as const,
    queryFn: fetchStripeConnectStatus,
  })
}

export function useCreateStripeOnboardingLink() {
  return useMutation({
    mutationFn: createStripeOnboardingLink,
  })
}
