import { Badge } from '@/components'
import type { MilestoneStatus } from '../types'

const STATUS_TONE: Record<MilestoneStatus, 'neutral' | 'success' | 'warning' | 'danger' | 'info'> = {
  pending: 'neutral',
  submitted: 'info',
  approved: 'success',
  paid: 'success',
  disputed: 'danger',
}

const STATUS_LABEL: Record<MilestoneStatus, string> = {
  pending: 'Pending',
  submitted: 'Awaiting approval',
  approved: 'Approved',
  paid: 'Paid',
  disputed: 'Disputed',
}

export function MilestoneStatusBadge({ status }: { status: MilestoneStatus }) {
  return <Badge tone={STATUS_TONE[status]}>{STATUS_LABEL[status]}</Badge>
}
