export type PaymentStatus = 'pending' | 'succeeded' | 'failed' | 'refunded' | 'disputed'
export type PaymentType = 'full' | 'deposit' | 'partial' | 'escrow'

export interface Payment {
  id: string
  payable_type: 'booking' | 'milestone'
  payable_id: string
  stripe_payment_intent_id: string
  amount: string
  platform_fee_amount: string
  provider_net_amount: string
  currency: string
  type: PaymentType
  status: PaymentStatus
  created_at: string
}

export interface PaymentIntentResult {
  payment: Payment
  clientSecret: string | null
}
