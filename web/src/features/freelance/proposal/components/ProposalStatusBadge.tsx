import { Badge } from '@/components'
import type { ProposalStatus } from '../types'

const STATUS_TONE: Record<ProposalStatus, 'neutral' | 'success' | 'warning' | 'danger' | 'info'> = {
  submitted: 'info',
  shortlisted: 'warning',
  accepted: 'success',
  rejected: 'danger',
  withdrawn: 'neutral',
}

const STATUS_LABEL: Record<ProposalStatus, string> = {
  submitted: 'Submitted',
  shortlisted: 'Shortlisted',
  accepted: 'Hired',
  rejected: 'Rejected',
  withdrawn: 'Withdrawn',
}

export function ProposalStatusBadge({ status }: { status: ProposalStatus }) {
  return <Badge tone={STATUS_TONE[status]}>{STATUS_LABEL[status]}</Badge>
}
