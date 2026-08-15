import { useQuery } from '@tanstack/react-query'
import { fetchProviderDashboard } from '../api/dashboardApi'

export function useProviderDashboard() {
  return useQuery({
    queryKey: ['provider', 'dashboard'] as const,
    queryFn: fetchProviderDashboard,
  })
}
