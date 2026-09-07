import { StatusStamp, type StampRole } from '../../components'
import type { ProposalStatus } from '../types'

const STATUS_ROLE: Record<ProposalStatus, StampRole> = {
  submitted: 'active',
  shortlisted: 'awaiting',
  accepted: 'paid',
  rejected: 'disputed',
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
  return <StatusStamp role={STATUS_ROLE[status]}>{STATUS_LABEL[status]}</StatusStamp>
}
