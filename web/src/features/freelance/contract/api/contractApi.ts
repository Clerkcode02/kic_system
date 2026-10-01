import { apiClient } from '@/lib/api'
import { uploadFileToS3 } from '@/lib/uploads'
import type {
  ConfirmDeliverablePayload,
  ContractDetail,
  ContractSummary,
  CursorPage,
  Deliverable,
  DeliverableUploadUrl,
  Milestone,
  NewMilestoneInput,
} from '../types'

// Re-exported so existing callers keep importing it from the contract API
// while the implementation lives in the shared lib/uploads module (the
// project brief uploader needs the same PUT helper).
export { uploadFileToS3 }

export async function fetchMyContracts(cursor?: string): Promise<CursorPage<ContractSummary>> {
  const { data } = await apiClient.get<CursorPage<ContractSummary>>('/freelancer/me/contracts', {
    params: { cursor },
  })
  return data
}

/**
 * Contracts the caller is a party to, from either side. Distinct from
 * `fetchMyContracts`, which is the freelancer-scoped route — this one serves
 * the client's list too (ListMyContractsQuery matches on either party).
 */
export async function fetchContractsForMe(cursor?: string): Promise<CursorPage<ContractSummary>> {
  const { data } = await apiClient.get<CursorPage<ContractSummary>>('/me/contracts', {
    params: { cursor },
  })
  return data
}

export async function fetchContract(contractId: string): Promise<ContractDetail> {
  const { data } = await apiClient.get<{ data: ContractDetail }>(`/contracts/${contractId}`)
  return data.data
}

export async function fetchMilestoneDeliverables(milestoneId: string): Promise<Deliverable[]> {
  const { data } = await apiClient.get<{ data: Deliverable[] }>(
    `/milestones/${milestoneId}/deliverables`,
  )
  return data.data
}

export async function requestDeliverableUploadUrl(
  milestoneId: string,
  filename: string,
): Promise<DeliverableUploadUrl> {
  const { data } = await apiClient.post<{ data: DeliverableUploadUrl }>(
    `/milestones/${milestoneId}/deliverables/upload-url`,
    { filename },
  )
  return data.data
}

export async function confirmDeliverable(
  milestoneId: string,
  payload: ConfirmDeliverablePayload,
): Promise<Deliverable> {
  const { data } = await apiClient.post<{ data: Deliverable }>(
    `/milestones/${milestoneId}/deliverables`,
    payload,
  )
  return data.data
}

export async function submitMilestone(
  milestoneId: string,
  deliverableIds: string[],
): Promise<Milestone> {
  const { data } = await apiClient.post<{ data: Milestone }>(`/milestones/${milestoneId}/submit`, {
    deliverable_ids: deliverableIds,
  })
  return data.data
}

/**
 * Only flips the milestone to 'approved' and enqueues escrow release
 * (App\Domain\Freelance\Actions\ApproveMilestone) — the actual Stripe
 * Transfer happens async on the payments queue, and fails loudly with a
 * `milestone_not_funded` 409 if escrow wasn't funded first.
 */
export async function approveMilestone(milestoneId: string): Promise<Milestone> {
  const { data } = await apiClient.post<{ data: Milestone }>(`/milestones/${milestoneId}/approve`)
  return data.data
}

/**
 * Defines the whole milestone breakdown in one call — this is a full
 * replace, not an append.
 *
 * `CreateContractMilestones` rejects the call unless the amounts sum exactly
 * to the contract total (422 on `milestones`), and refuses to redefine once
 * any milestone has left `pending` (409 `milestones_locked`).
 */
export async function createContractMilestones(
  contractId: string,
  milestones: NewMilestoneInput[],
): Promise<Milestone[]> {
  const { data } = await apiClient.post<{ data: Milestone[] }>(
    `/contracts/${contractId}/milestones`,
    { milestones },
  )
  return data.data
}

/** Sends a submitted milestone back to the freelancer with a reason. */
export async function rejectMilestone(milestoneId: string, reason: string): Promise<Milestone> {
  const { data } = await apiClient.post<{ data: Milestone }>(`/milestones/${milestoneId}/reject`, {
    reason,
  })
  return data.data
}
