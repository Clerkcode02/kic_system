import { Card, EmptyState, Skeleton } from '@/components'
import { usePlatformSettings } from '../hooks/usePlatformSettings'
import type { PlatformSetting } from '../types'
import { SettingRow } from './SettingRow'

function groupByPrefix(settings: PlatformSetting[]): Array<[string, PlatformSetting[]]> {
  const groups = new Map<string, PlatformSetting[]>()
  for (const setting of settings) {
    const prefix = setting.key.split('.')[0] ?? setting.key
    const existing = groups.get(prefix) ?? []
    existing.push(setting)
    groups.set(prefix, existing)
  }
  return Array.from(groups.entries())
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([prefix, items]) => [prefix, items.sort((a, b) => a.key.localeCompare(b.key))])
}

export function PlatformSettingsPage() {
  const { data: settings, isLoading } = usePlatformSettings()

  if (isLoading) {
    return (
      <div className="p-4 sm:p-6">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="mt-4 h-96 w-full" />
      </div>
    )
  }

  const groups = groupByPrefix(settings ?? [])

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <h1 className="text-lg font-semibold text-gray-900">Platform settings</h1>

      {groups.length === 0 ? (
        <EmptyState title="No settings found" description="Platform settings will appear here." />
      ) : (
        groups.map(([prefix, items]) => (
          <Card key={prefix}>
            <h2 className="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
              {prefix}
            </h2>
            <div className="flex flex-col">
              {items.map((setting) => (
                <SettingRow key={setting.key} setting={setting} />
              ))}
            </div>
          </Card>
        ))
      )}
    </div>
  )
}
