import { apiClient } from '@/lib/api'
import type { CursorPage, Dispute, IssueRefundPayload, Refund, ResolveDisputePayload } from '../types'

export async function fetchDisputes(cursor?: string): Promise<CursorPage<Dispute>> {
  const { data } = await apiClient.get<CursorPage<Dispute>>('/disputes', {
    params: { cursor },
  })
  return data
}

export async function fetchDispute(disputeId: string): Promise<Dispute> {
  const { data } = await apiClient.get<{ data: Dispute }>(`/disputes/${disputeId}`)
  return data.data
}

export async function resolveDispute(
  disputeId: string,
  payload: ResolveDisputePayload,
): Promise<Dispute> {
  const { data } = await apiClient.post<{ data: Dispute }>(`/disputes/${disputeId}/resolve`, payload)
  return data.data
}

export async function assignDispute(disputeId: string, adminId: string): Promise<Dispute> {
  const { data } = await apiClient.post<{ data: Dispute }>(`/admin/disputes/${disputeId}/assign`, {
    admin_id: adminId,
  })
  return data.data
}

export async function issueRefund(
  paymentId: string,
  payload: IssueRefundPayload,
): Promise<Refund> {
  const { data } = await apiClient.post<{ data: Refund }>(
    `/admin/payments/${paymentId}/refund`,
    payload,
  )
  return data.data
}
