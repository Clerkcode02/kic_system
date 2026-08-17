export interface AnalyticsSnapshotMetrics {
  bookings_total: number
  bookings_active_24h: number
  gmv_24h: number
  new_signups_24h: {
    customer: number
    provider: number
    freelancer: number
  }
  verification_queue_depth: number
  open_disputes: number
  payout_volume_24h: number
}

export interface AnalyticsSnapshot {
  id: string
  snapshot_at: string
  metrics: AnalyticsSnapshotMetrics
}
