import { apiClient } from '@/lib/api'
import type { CursorPage, FailedTransferPayment, Payout } from '../types'

export async function fetchPayouts(cursor?: string): Promise<CursorPage<Payout>> {
  const { data } = await apiClient.get<CursorPage<Payout>>('/admin/payouts', {
    params: { cursor },
  })
  return data
}

export async function fetchFailedTransfers(
  cursor?: string,
): Promise<CursorPage<FailedTransferPayment>> {
  const { data } = await apiClient.get<CursorPage<FailedTransferPayment>>(
    '/admin/payouts/failed-transfers',
    { params: { cursor } },
  )
  return data
}

export async function retryFailedTransfer(paymentId: string): Promise<FailedTransferPayment> {
  const { data } = await apiClient.post<{ data: FailedTransferPayment }>(
    `/admin/payouts/failed-transfers/${paymentId}/retry`,
  )
  return data.data
}
