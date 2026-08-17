import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchPlatformSettings, updatePlatformSetting } from '../api/platformSettingsApi'
import type { PlatformSettingType } from '../types'

const PLATFORM_SETTINGS_QUERY_KEY = ['admin', 'platform-settings'] as const

export function usePlatformSettings() {
  return useQuery({
    queryKey: PLATFORM_SETTINGS_QUERY_KEY,
    queryFn: fetchPlatformSettings,
  })
}

export function useUpdatePlatformSetting() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      key,
      value,
      type,
      description,
    }: {
      key: string
      value: string
      type: PlatformSettingType
      description?: string | null
    }) => updatePlatformSetting(key, value, type, description),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: PLATFORM_SETTINGS_QUERY_KEY })
    },
  })
}
