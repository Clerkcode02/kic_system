import { apiClient } from '@/lib/api'
import type { Quotation } from '@/features/booking/types'

export interface QuotationLineItemInput {
  description?: string
  quantity?: number
  unit_price?: number
}

/**
 * Raw cost inputs only — labor/materials/fees/discount/line-items.
 * `total_amount`/`platform_fee`/`tax_amount` are never sent: the server
 * always recomputes them (StoreQuotationRequest/ReviseQuotationRequest have
 * no such fields, CLAUDE.md §2/§5).
 */
export interface QuotationBuilderPayload {
  labor_cost: number
  materials_cost: number
  additional_fees: number
  discount_amount?: number | null
  line_items?: QuotationLineItemInput[] | null
}

export async function sendQuotation(
  bookingId: string,
  payload: QuotationBuilderPayload,
): Promise<Quotation> {
  const { data } = await apiClient.post<{ data: Quotation }>(
    `/bookings/${bookingId}/quotations`,
    payload,
  )
  return data.data
}

export async function reviseQuotation(
  quotationId: string,
  payload: QuotationBuilderPayload,
): Promise<Quotation> {
  const { data } = await apiClient.post<{ data: Quotation }>(
    `/quotations/${quotationId}/revise`,
    payload,
  )
  return data.data
}

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
