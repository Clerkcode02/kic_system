export type PayoutStatus = 'scheduled' | 'processing' | 'paid' | 'failed'

export interface Payout {
  id: string
  provider_id: string
  provider_name: string | null
  amount: string
  currency: string
  status: PayoutStatus
  stripe_transfer_id: string | null
  created_at: string
}

export interface FailedTransferPayment {
  id: string
  payable_type: string
  payable_id: string
  stripe_payment_intent_id: string | null
  amount: string
  platform_fee_amount: string
  provider_net_amount: string
  currency: string
  type: string
  status: string
  created_at: string
}

export interface CursorPage<T> {
  data: T[]
  meta: {
    next_cursor: string | null
    prev_cursor: string | null
  }
}
