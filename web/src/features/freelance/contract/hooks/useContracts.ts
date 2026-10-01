import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  approveMilestone,
  createContractMilestones,
  fetchContract,
  fetchContractsForMe,
  fetchMilestoneDeliverables,
  fetchMyContracts,
  rejectMilestone,
  submitMilestone,
} from '../api/contractApi'
import type { NewMilestoneInput } from '../types'

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

export function useApproveMilestone(contractId: string | undefined) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (milestoneId: string) => approveMilestone(milestoneId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: CONTRACTS_QUERY_KEY })
      if (contractId) {
        queryClient.invalidateQueries({ queryKey: ['freelance', 'contract', contractId] })
      }
    },
  })
}

export function useSubmitMilestone(contractId: string | undefined) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      milestoneId,
      deliverableIds,
    }: {
      milestoneId: string
      deliverableIds: string[]
    }) => submitMilestone(milestoneId, deliverableIds),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: CONTRACTS_QUERY_KEY })
      if (contractId) {
        queryClient.invalidateQueries({ queryKey: ['freelance', 'contract', contractId] })
      }
      queryClient.invalidateQueries({ queryKey: ['freelance', 'dashboard'] })
    },
  })
}

/**
 * The client's contract list. Separate hook (and cache key) from
 * `useInfiniteMyContracts` because the two hit different routes — the
 * freelancer-scoped one and the shared `/me/contracts`.
 */
export function useInfiniteContractsForMe() {
  return useInfiniteQuery({
    queryKey: [...CONTRACTS_QUERY_KEY, 'mine'] as const,
    queryFn: ({ pageParam }) => fetchContractsForMe(pageParam),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.meta.next_cursor ?? undefined,
  })
}

export function useCreateContractMilestones(contractId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (milestones: NewMilestoneInput[]) =>
      createContractMilestones(contractId, milestones),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: CONTRACTS_QUERY_KEY })
      queryClient.invalidateQueries({ queryKey: ['freelance', 'contract', contractId] })
    },
  })
}

export function useRejectMilestone(contractId: string | undefined) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ milestoneId, reason }: { milestoneId: string; reason: string }) =>
      rejectMilestone(milestoneId, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: CONTRACTS_QUERY_KEY })
      if (contractId) {
        queryClient.invalidateQueries({ queryKey: ['freelance', 'contract', contractId] })
      }
    },
  })
}
