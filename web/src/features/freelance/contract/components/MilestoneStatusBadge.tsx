import { StatusStamp, type StampRole } from '../../components'
import type { MilestoneStatus } from '../types'

const STATUS_ROLE: Record<MilestoneStatus, StampRole> = {
  pending: 'neutral',
  submitted: 'active',
  approved: 'awaiting',
  paid: 'paid',
  disputed: 'disputed',
}

const STATUS_LABEL: Record<MilestoneStatus, string> = {
  pending: 'Pending',
  submitted: 'Awaiting approval',
  approved: 'Approved',
  paid: 'Paid',
  disputed: 'Disputed',
}

export function MilestoneStatusBadge({
  status,
  animate = true,
}: {
  status: MilestoneStatus
  animate?: boolean
}) {
  return (
    <StatusStamp role={STATUS_ROLE[status]} animate={animate}>
      {STATUS_LABEL[status]}
    </StatusStamp>
  )
}
