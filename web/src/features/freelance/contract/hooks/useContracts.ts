import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  fetchContract,
  fetchMilestoneDeliverables,
  fetchMyContracts,
  submitMilestone,
} from '../api/contractApi'

const CONTRACTS_QUERY_KEY = ['freelance', 'contracts'] as const

export function useInfiniteMyContracts() {
  return useInfiniteQuery({
    queryKey: CONTRACTS_QUERY_KEY,
    queryFn: ({ pageParam }) => fetchMyContracts(pageParam),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.meta.next_cursor ?? undefined,
  })
}

export function useContract(contractId: string | undefined) {
  return useQuery({
    queryKey: ['freelance', 'contract', contractId] as const,
    queryFn: () => fetchContract(contractId as string),
    enabled: Boolean(contractId),
  })
}

export function milestoneDeliverablesQueryKey(milestoneId: string) {
  return ['freelance', 'milestone-deliverables', milestoneId] as const
}

export function useMilestoneDeliverables(milestoneId: string) {
  return useQuery({
    queryKey: milestoneDeliverablesQueryKey(milestoneId),
    queryFn: () => fetchMilestoneDeliverables(milestoneId),
  })
}

export function useSubmitMilestone(contractId: string | undefined) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ milestoneId, deliverableIds }: { milestoneId: string; deliverableIds: string[] }) =>
      submitMilestone(milestoneId, deliverableIds),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: CONTRACTS_QUERY_KEY })
      if (contractId) {
        queryClient.invalidateQueries({ queryKey: ['freelance', 'contract', contractId] })
      }
      queryClient.invalidateQueries({ queryKey: ['freelance', 'dashboard'] })
    },
  })
}
