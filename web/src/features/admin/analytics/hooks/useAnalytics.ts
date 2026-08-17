import { useQuery } from '@tanstack/react-query'
import { fetchDashboardMetrics } from '../api/analyticsApi'

const DASHBOARD_METRICS_QUERY_KEY = ['admin', 'analytics', 'dashboard-metrics'] as const

export function useDashboardMetrics() {
  return useQuery({
    queryKey: DASHBOARD_METRICS_QUERY_KEY,
    queryFn: fetchDashboardMetrics,
  })
}
