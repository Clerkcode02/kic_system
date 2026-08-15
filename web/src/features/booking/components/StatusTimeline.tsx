import type { BookingStatusHistoryEntry } from '../types'
import { BOOKING_STATUS_LABELS } from './BookingStatusBadge'

export function StatusTimeline({ history }: { history: BookingStatusHistoryEntry[] }) {
  if (history.length === 0) return null

  return (
    <ol className="flex flex-col gap-3">
      {history.map((entry, index) => (
        <li key={entry.id} className="flex gap-3">
          <div className="flex flex-col items-center">
            <span
              className={`h-2.5 w-2.5 rounded-full ${index === history.length - 1 ? 'bg-blue-600' : 'bg-gray-300'}`}
            />
            {index < history.length - 1 && <span className="w-px flex-1 bg-gray-200" />}
          </div>
          <div className="pb-3">
            <p className="text-sm font-medium text-gray-900">
              {BOOKING_STATUS_LABELS[entry.to_status]}
            </p>
            {entry.note && <p className="text-sm text-gray-500">{entry.note}</p>}
            <p className="text-xs text-gray-400">{new Date(entry.created_at).toLocaleString()}</p>
          </div>
        </li>
      ))}
    </ol>
  )
}
