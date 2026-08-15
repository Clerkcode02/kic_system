import { apiClient } from '@/lib/api'
import type { CursorPage, Proposal, ProposalListItem, SubmitProposalPayload } from '../types'

export async function submitProposal(
  projectId: string,
  payload: SubmitProposalPayload,
): Promise<Proposal> {
  const { data } = await apiClient.post<{ data: Proposal }>(
    `/projects/${projectId}/proposals`,
    payload,
  )
  return data.data
}

export async function fetchMyProposals(cursor?: string): Promise<CursorPage<ProposalListItem>> {
  const { data } = await apiClient.get<CursorPage<ProposalListItem>>('/freelancers/me/proposals', {
    params: { cursor },
  })
  return data
}

export async function withdrawProposal(proposalId: string): Promise<Proposal> {
  const { data } = await apiClient.post<{ data: Proposal }>(`/proposals/${proposalId}/withdraw`)
  return data.data
}
