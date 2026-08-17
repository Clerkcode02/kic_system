import { apiClient } from '@/lib/api'
import type {
  ConfirmDeliverablePayload,
  ContractDetail,
  ContractSummary,
  CursorPage,
  Deliverable,
  DeliverableUploadUrl,
  Milestone,
} from '../types'

export async function fetchMyContracts(cursor?: string): Promise<CursorPage<ContractSummary>> {
  const { data } = await apiClient.get<CursorPage<ContractSummary>>('/freelancer/me/contracts', {
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

export async function uploadFileToS3(
  uploadUrl: DeliverableUploadUrl,
  file: File,
  onProgress: (percent: number) => void,
): Promise<void> {
  await new Promise<void>((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    xhr.open('PUT', uploadUrl.url)
    Object.entries(uploadUrl.headers).forEach(([key, value]) => {
      xhr.setRequestHeader(key, value)
    })
    xhr.upload.onprogress = (event) => {
      if (event.lengthComputable) {
        onProgress(Math.round((event.loaded / event.total) * 100))
      }
    }
    xhr.onload = () => {
      if (xhr.status >= 200 && xhr.status < 300) {
        resolve()
      } else {
        reject(new Error(`Upload failed with status ${xhr.status}`))
      }
    }
    xhr.onerror = () => reject(new Error('Upload failed.'))
    xhr.send(file)
  })
}
