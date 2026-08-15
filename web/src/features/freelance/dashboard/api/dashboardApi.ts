import { apiClient } from '@/lib/api'
import type { FreelancerDashboardSummary } from '../types'

export async function fetchFreelancerDashboard(): Promise<FreelancerDashboardSummary> {
  const { data } = await apiClient.get<{ data: FreelancerDashboardSummary }>(
    '/freelancer/me/dashboard',
  )
  return data.data
}
