import { apiClient } from '@/lib/api'
import type { Quotation } from '@/features/booking/types'

export async function acceptQuotation(
  quotationId: string,
  idempotencyKey: string,
): Promise<Quotation> {
  const { data } = await apiClient.post<{ data: Quotation }>(
    `/quotations/${quotationId}/accept`,
    {},
    { headers: { 'Idempotency-Key': idempotencyKey } },
  )
  return data.data
}

export async function rejectQuotation(quotationId: string, reason?: string): Promise<Quotation> {
  const { data } = await apiClient.post<{ data: Quotation }>(`/quotations/${quotationId}/reject`, {
    reason,
  })
  return data.data
}
