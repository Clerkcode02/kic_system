import { apiClient } from '@/lib/api'
import type { CursorPage, EarningRow } from '../types'

export async function fetchMyEarnings(cursor?: string): Promise<CursorPage<EarningRow>> {
  const { data } = await apiClient.get<CursorPage<EarningRow>>('/freelancer/me/earnings', {
    params: { cursor },
  })
  return data
}
