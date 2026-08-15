import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  fetchMyFreelancerProfile,
  fetchMyPortfolio,
  replaceMySkills,
  updateMyFreelancerProfile,
} from '../api/freelancerApi'
import type { FreelancerProfile, PortfolioItem } from '../types'

const PROFILE_QUERY_KEY = ['freelancer', 'me'] as const
const PORTFOLIO_QUERY_KEY = ['freelancer', 'me', 'portfolio'] as const

export function useMyFreelancerProfile() {
  return useQuery<FreelancerProfile>({
    queryKey: PROFILE_QUERY_KEY,
    queryFn: fetchMyFreelancerProfile,
  })
}

export function useUpdateFreelancerProfile() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: updateMyFreelancerProfile,
    onSuccess: (profile) => queryClient.setQueryData(PROFILE_QUERY_KEY, profile),
  })
}

export function useReplaceMySkills() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: replaceMySkills,
    onSuccess: (skills) => {
      queryClient.setQueryData<FreelancerProfile | undefined>(PROFILE_QUERY_KEY, (profile) =>
        profile ? { ...profile, skills } : profile,
      )
    },
  })
}

export function useMyPortfolio() {
  return useQuery<PortfolioItem[]>({
    queryKey: PORTFOLIO_QUERY_KEY,
    queryFn: fetchMyPortfolio,
  })
}

export { PORTFOLIO_QUERY_KEY }
