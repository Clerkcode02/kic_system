import { apiClient } from '@/lib/api'
import type {
  CursorPage,
  HiredContract,
  Proposal,
  ProposalListItem,
  ProposalSort,
  SubmitProposalPayload,
} from '../types'

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

/**
 * The proposals on one project — the client's comparison list.
 * `ProjectPolicy::viewProposals` restricts this to the owning client and
 * admins, so a competing freelancer calling it gets a 403.
 */
export async function fetchProjectProposals(
  projectId: string,
  sort?: ProposalSort,
  cursor?: string,
): Promise<CursorPage<Proposal>> {
  const { data } = await apiClient.get<CursorPage<Proposal>>(`/projects/${projectId}/proposals`, {
    params: { sort, cursor },
  })
  return data
}

export async function shortlistProposal(proposalId: string): Promise<Proposal> {
  const { data } = await apiClient.post<{ data: Proposal }>(`/proposals/${proposalId}/shortlist`)
  return data.data
}

/**
 * Hiring is exclusive and irreversible: it creates the contract, moves the
 * project to in_progress and auto-rejects every sibling proposal.
 *
 * Two failures are expected rather than exceptional, and callers should
 * render both as their own state:
 * - **409 `project_not_open`** — someone already hired on this project.
 * - **403 `freelancer_payouts_not_enabled`** — the freelancer hasn't
 *   finished Stripe Connect onboarding, so they can't receive funds yet.
 */
export async function hireProposal(proposalId: string): Promise<HiredContract> {
  const { data } = await apiClient.post<{ data: HiredContract }>(`/proposals/${proposalId}/hire`)
  return data.data
}
