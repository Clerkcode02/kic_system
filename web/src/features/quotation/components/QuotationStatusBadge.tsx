import { Badge } from '@/components'
import type { Quotation } from '@/features/booking/types'

const STATUS_TONE: Record<Quotation['status'], 'neutral' | 'success' | 'warning' | 'danger' | 'info'> = {
  sent: 'info',
  accepted: 'success',
  rejected: 'danger',
  superseded: 'neutral',
  expired: 'danger',
}

/** Shared status pill for a quotation, used on both the customer and provider sides. */
export function QuotationStatusBadge({ status }: { status: Quotation['status'] }) {
  return <Badge tone={STATUS_TONE[status]}>{status}</Badge>
}

export { STATUS_TONE as QUOTATION_STATUS_TONE }
