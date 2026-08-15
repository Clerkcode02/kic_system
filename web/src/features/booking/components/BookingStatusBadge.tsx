import { Badge } from '@/components'
import type { BookingStatus } from '../types'

const STATUS_LABELS: Record<BookingStatus, string> = {
  pending: 'Pending',
  waiting_for_quotation: 'Awaiting quotation',
  quotation_sent: 'Quotation received',
  waiting_for_customer: 'Awaiting your response',
  accepted: 'Accepted',
  scheduled: 'Scheduled',
  in_progress: 'In progress',
  completed: 'Completed',
  declined: 'Declined',
  quotation_expired: 'Quotation expired',
  cancelled: 'Cancelled',
  refunded: 'Refunded',
}

const STATUS_TONES: Record<BookingStatus, 'neutral' | 'success' | 'warning' | 'danger' | 'info'> = {
  pending: 'neutral',
  waiting_for_quotation: 'warning',
  quotation_sent: 'info',
  waiting_for_customer: 'warning',
  accepted: 'success',
  scheduled: 'success',
  in_progress: 'info',
  completed: 'success',
  declined: 'danger',
  quotation_expired: 'danger',
  cancelled: 'danger',
  refunded: 'neutral',
}

export function BookingStatusBadge({ status }: { status: BookingStatus }) {
  return <Badge tone={STATUS_TONES[status]}>{STATUS_LABELS[status]}</Badge>
}

export { STATUS_LABELS as BOOKING_STATUS_LABELS }
