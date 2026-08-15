export interface EarningRow {
  id: string
  milestone_id: string
  milestone_title: string | null
  amount: string
  platform_fee_amount: string
  net_amount: string
  currency: string
  stripe_transfer_id: string | null
  released: boolean
  created_at: string | null
}

export interface CursorPage<T> {
  data: T[]
  meta: {
    next_cursor: string | null
    prev_cursor: string | null
  }
}
