export type PayoutStatus = 'scheduled' | 'processing' | 'paid' | 'failed'

export interface Payout {
  id: string
  amount: string
  currency: string
  stripe_transfer_id: string | null
  status: PayoutStatus
  created_at: string | null
}

export interface CursorPage<T> {
  data: T[]
  links: { first: string | null; last: string | null; prev: string | null; next: string | null }
  meta: {
    path: string | null
    per_page: number
    next_cursor: string | null
    prev_cursor: string | null
  }
}

export interface StripeConnectStatus {
  charges_enabled: boolean
  payouts_enabled: boolean
}
