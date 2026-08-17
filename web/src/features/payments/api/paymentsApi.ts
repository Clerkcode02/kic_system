import { apiClient } from '@/lib/api'
import type { Payment, PaymentIntentResult } from '../types'

/**
 * POST /payments/intents (CLAUDE.md §7 item 4) — the generic payable-kind
 * entry point. Used to fund milestone escrow and as the booking-side retry
 * path when no pending checkout was recovered from storage. Never sends an
 * amount: the server always recomputes it from persisted rows.
 */
export async function createPaymentIntent(
  payableType: 'booking' | 'milestone',
  payableId: string,
  idempotencyKey: string,
): Promise<PaymentIntentResult> {
  const { data } = await apiClient.post<{ data: Payment; meta: { client_secret: string | null } }>(
    '/payments/intents',
    { payable_type: payableType, payable_id: payableId },
    { headers: { 'Idempotency-Key': idempotencyKey } },
  )
  return { payment: data.data, clientSecret: data.meta.client_secret }
}
