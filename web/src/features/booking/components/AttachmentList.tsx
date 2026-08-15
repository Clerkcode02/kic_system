import type { BookingAttachment } from '../types'

export function AttachmentList({ attachments }: { attachments: BookingAttachment[] }) {
  if (attachments.length === 0) {
    return <p className="text-sm text-gray-500">No attachments on this booking.</p>
  }

  return (
    <ul className="flex flex-col gap-2">
      {attachments.map((attachment) => (
        <li
          key={attachment.id}
          className="flex items-center justify-between rounded-md border border-gray-200 p-2 text-sm"
        >
          <span className="truncate text-gray-700">
            {attachment.caption ?? attachment.file_path.split('/').pop()}
          </span>
          <span className="text-xs text-gray-400">
            {Math.round(attachment.size_bytes / 1024)} KB
          </span>
        </li>
      ))}
    </ul>
  )
}
