import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  fetchMyProposals,
  fetchProjectProposals,
  hireProposal,
  shortlistProposal,
  submitProposal,
  withdrawProposal,
} from '../api/proposalApi'
import type { ProposalSort, SubmitProposalPayload } from '../types'

const PROPOSALS_QUERY_KEY = ['freelance', 'proposals'] as const

export const projectProposalsQueryKey = (projectId: string, sort: ProposalSort) =>
  ['freelance', 'project-proposals', projectId, sort] as const

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

export function useInfiniteProjectProposals(projectId: string, sort: ProposalSort = 'newest') {
  return useInfiniteQuery({
    queryKey: projectProposalsQueryKey(projectId, sort),
    queryFn: ({ pageParam }) => fetchProjectProposals(projectId, sort, pageParam),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.meta.next_cursor ?? undefined,
  })
}

export function useShortlistProposal(projectId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (proposalId: string) => shortlistProposal(proposalId),
    onSuccess: () => {
      // Every sort of this project's list is stale, so invalidate the
      // prefix rather than the one key the component happens to hold.
      queryClient.invalidateQueries({ queryKey: ['freelance', 'project-proposals', projectId] })
    },
  })
}

export function useHireProposal(projectId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (proposalId: string) => hireProposal(proposalId),
    onSuccess: () => {
      // Hiring rewrites far more than the proposal: siblings are rejected,
      // the project moves to in_progress, and a contract now exists.
      queryClient.invalidateQueries({ queryKey: ['freelance', 'project-proposals', projectId] })
      queryClient.invalidateQueries({ queryKey: ['freelance', 'project', projectId] })
      queryClient.invalidateQueries({ queryKey: ['freelance', 'my-projects'] })
      queryClient.invalidateQueries({ queryKey: ['freelance', 'projects'] })
      queryClient.invalidateQueries({ queryKey: ['freelance', 'contracts'] })
    },
  })
}
