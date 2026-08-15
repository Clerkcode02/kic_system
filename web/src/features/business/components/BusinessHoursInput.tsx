import type { BusinessHours } from '@/features/auth/types'

const DAYS: { key: keyof BusinessHours; label: string }[] = [
  { key: 'monday', label: 'Monday' },
  { key: 'tuesday', label: 'Tuesday' },
  { key: 'wednesday', label: 'Wednesday' },
  { key: 'thursday', label: 'Thursday' },
  { key: 'friday', label: 'Friday' },
  { key: 'saturday', label: 'Saturday' },
  { key: 'sunday', label: 'Sunday' },
]

interface BusinessHoursInputProps {
  value: BusinessHours
  onChange: (value: BusinessHours) => void
  error?: string
}

export function BusinessHoursInput({ value, onChange, error }: BusinessHoursInputProps) {
  const toggleDay = (day: keyof BusinessHours, open: boolean) => {
    onChange({ ...value, [day]: open ? { open: '09:00', close: '17:00' } : null })
  }

  const setTime = (day: keyof BusinessHours, field: 'open' | 'close', time: string) => {
    const current = value[day]
    if (!current) return
    onChange({ ...value, [day]: { ...current, [field]: time } })
  }

  return (
    <div className="flex flex-col gap-2">
      <span className="text-sm font-medium text-gray-700">Business hours</span>
      <div className="flex flex-col gap-2">
        {DAYS.map(({ key, label }) => {
          const hours = value[key]
          return (
            <div key={key} className="flex flex-wrap items-center gap-3">
              <label className="flex w-28 items-center gap-2 text-sm text-gray-700">
                <input
                  type="checkbox"
                  checked={hours !== null}
                  onChange={(event) => toggleDay(key, event.target.checked)}
                />
                {label}
              </label>
              {hours && (
                <div className="flex items-center gap-2">
                  <input
                    type="time"
                    value={hours.open}
                    onChange={(event) => setTime(key, 'open', event.target.value)}
                    className="rounded-md border border-gray-300 px-2 py-1 text-sm"
                  />
                  <span className="text-sm text-gray-500">to</span>
                  <input
                    type="time"
                    value={hours.close}
                    onChange={(event) => setTime(key, 'close', event.target.value)}
                    className="rounded-md border border-gray-300 px-2 py-1 text-sm"
                  />
                </div>
              )}
            </div>
          )
        })}
      </div>
      {error && <p className="text-sm text-red-600">{error}</p>}
    </div>
  )
}
