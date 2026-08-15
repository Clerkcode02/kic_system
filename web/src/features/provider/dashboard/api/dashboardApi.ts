import { apiClient } from '@/lib/api'
import type { ProviderDashboardSummary } from '../types'

export async function fetchProviderDashboard(): Promise<ProviderDashboardSummary> {
  const { data } = await apiClient.get<{ data: ProviderDashboardSummary }>(
    '/provider/me/dashboard',
  )
  return data.data
}
