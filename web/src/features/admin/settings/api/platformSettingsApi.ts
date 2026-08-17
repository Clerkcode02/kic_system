import { apiClient } from '@/lib/api'
import type { PlatformSetting, PlatformSettingType } from '../types'

export async function fetchPlatformSettings(): Promise<PlatformSetting[]> {
  const { data } = await apiClient.get<{ data: PlatformSetting[] }>('/admin/platform-settings')
  return data.data
}

export async function updatePlatformSetting(
  key: string,
  value: string,
  type: PlatformSettingType,
  description?: string | null,
): Promise<PlatformSetting> {
  const { data } = await apiClient.patch<{ data: PlatformSetting }>(
    `/admin/platform-settings/${key}`,
    { value, type, description: description ?? undefined },
  )
  return data.data
}
