import { apiClient } from '@/lib/api'
import type { AvailabilitySlot } from '@/lib/calendar'

export async function fetchProviderAvailability(
  businessId: string,
  date: string,
): Promise<AvailabilitySlot[]> {
  const { data } = await apiClient.get<{ data: { date: string; slots: AvailabilitySlot[] } }>(
    `/providers/${businessId}/availability`,
    { params: { date } },
  )
  return data.data.slots
}
