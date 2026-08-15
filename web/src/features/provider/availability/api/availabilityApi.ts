import { apiClient } from '@/lib/api'
import type { AvailabilityConfig, ReplaceAvailabilityPayload } from '../types'

export async function fetchAvailability(): Promise<AvailabilityConfig> {
  const { data } = await apiClient.get<{ data: AvailabilityConfig }>('/provider/me/availability')
  return {
    weekly: data.data.weekly ?? [],
    overrides: data.data.overrides ?? [],
  }
}

export async function replaceAvailability(
  payload: ReplaceAvailabilityPayload,
): Promise<AvailabilityConfig> {
  const { data } = await apiClient.put<{ data: AvailabilityConfig }>(
    '/provider/me/availability',
    payload,
  )
  return {
    weekly: data.data.weekly ?? [],
    overrides: data.data.overrides ?? [],
  }
}
