import type { BookingListItem } from '@/features/booking/types'

export interface Payout {
  id: string
  amount: string
  currency: string
  stripe_transfer_id: string | null
  status: 'scheduled' | 'processing' | 'paid' | 'failed'
  created_at: string | null
}

export interface ProviderDashboardSummary {
  today_schedule: BookingListItem[]
  pending_quotations: BookingListItem[]
  upcoming_bookings: BookingListItem[]
  earnings: {
    total: string
    currency: 'CAD'
    recent_payouts: Payout[]
  }
}
