import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { fetchMyProposals, submitProposal, withdrawProposal } from '../api/proposalApi'
import type { SubmitProposalPayload } from '../types'

const PROPOSALS_QUERY_KEY = ['freelance', 'proposals'] as const

export function useInfiniteMyProposals() {
  return useInfiniteQuery({
    queryKey: PROPOSALS_QUERY_KEY,
    queryFn: ({ pageParam }) => fetchMyProposals(pageParam),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.meta.next_cursor ?? undefined,
  })
}

export function useSubmitProposal(projectId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: SubmitProposalPayload) => submitProposal(projectId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: PROPOSALS_QUERY_KEY })
    },
  })
}

export function useWithdrawProposal() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (proposalId: string) => withdrawProposal(proposalId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: PROPOSALS_QUERY_KEY })
    },
  })
}
