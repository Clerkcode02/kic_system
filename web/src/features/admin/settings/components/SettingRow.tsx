import { useState } from 'react'
import toast from 'react-hot-toast'
import { Button, Input } from '@/components'
import { ApiError } from '@/lib/api'
import { useUpdatePlatformSetting } from '../hooks/usePlatformSettings'
import type { PlatformSetting } from '../types'

function toEditableString(setting: PlatformSetting): string {
  if (setting.type === 'json') {
    try {
      return JSON.stringify(setting.value, null, 2)
    } catch {
      return String(setting.value)
    }
  }
  return String(setting.value)
}

interface SettingRowProps {
  setting: PlatformSetting
}

export function SettingRow({ setting }: SettingRowProps) {
  const [draft, setDraft] = useState(() => toEditableString(setting))
  const [checked, setChecked] = useState(() => Boolean(setting.value))
  const [jsonError, setJsonError] = useState<string | null>(null)
  const { mutateAsync: save, isPending } = useUpdatePlatformSetting()

  const handleSave = async () => {
    let stringValue: string
    if (setting.type === 'boolean') {
      stringValue = String(checked)
    } else if (setting.type === 'json') {
      try {
        const parsed = JSON.parse(draft)
        stringValue = JSON.stringify(parsed)
        setJsonError(null)
      } catch {
        setJsonError('Invalid JSON.')
        return
      }
    } else {
      stringValue = draft
    }

    try {
      await save({ key: setting.key, value: stringValue, type: setting.type, description: setting.description })
      toast.success(`Saved ${setting.key}.`)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : `Could not save ${setting.key}.`)
    }
  }

  return (
    <div className="flex flex-col gap-2 border-b border-gray-100 py-3 last:border-b-0">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex-1">
          <p className="font-mono text-sm font-medium text-gray-900">{setting.key}</p>
          {setting.description && <p className="text-sm text-gray-500">{setting.description}</p>}
        </div>
        <div className="flex items-center gap-2">
          {setting.type === 'boolean' ? (
            <label className="flex items-center gap-2 text-sm text-gray-700">
              <input
                type="checkbox"
                className="h-4 w-4 rounded border-gray-300"
                checked={checked}
                onChange={(event) => setChecked(event.target.checked)}
              />
              Enabled
            </label>
          ) : setting.type === 'json' ? (
            <textarea
              rows={4}
              className="w-64 rounded-md border border-gray-300 px-3 py-2 font-mono text-xs shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
            />
          ) : (
            <Input
              type={setting.type === 'integer' || setting.type === 'float' ? 'number' : 'text'}
              step={setting.type === 'float' ? '0.01' : undefined}
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
              className="w-56"
            />
          )}
          <Button type="button" size="sm" isLoading={isPending} onClick={handleSave}>
            Save
          </Button>
        </div>
      </div>
      {jsonError && <p className="text-sm text-red-600">{jsonError}</p>}
    </div>
  )
}
