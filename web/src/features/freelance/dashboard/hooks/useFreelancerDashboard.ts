import { useQuery } from '@tanstack/react-query'
import { fetchFreelancerDashboard } from '../api/dashboardApi'

export function useFreelancerDashboard() {
  return useQuery({
    queryKey: ['freelance', 'dashboard'] as const,
    queryFn: fetchFreelancerDashboard,
  })
}
