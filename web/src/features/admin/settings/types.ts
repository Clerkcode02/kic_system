export type PlatformSettingType = 'string' | 'integer' | 'float' | 'boolean' | 'json'

export interface PlatformSetting {
  key: string
  value: string | number | boolean | Record<string, unknown>
  type: PlatformSettingType
  description: string | null
  updated_at: string
}
