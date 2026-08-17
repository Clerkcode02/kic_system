import { apiClient } from '@/lib/api'
import type { AnalyticsSnapshot } from '../types'

/**
 * Reads the pre-computed hourly snapshot table (GenerateAdminAnalyticsSnapshotJob) —
 * never live-aggregates. No filtering/date-range params; returns whatever history
 * exists, oldest first, up to the last 30 hourly snapshots (may be as few as 1 row
 * in a fresh environment).
 */
export async function fetchDashboardMetrics(): Promise<AnalyticsSnapshot[]> {
  const { data } = await apiClient.get<{ data: AnalyticsSnapshot[] }>('/admin/dashboard/metrics')
  return data.data
}
