import { StatusStamp, type StampRole } from '../../components'
import type { ContractStatus } from '../types'

const STATUS_ROLE: Record<ContractStatus, StampRole> = {
  active: 'active',
  completed: 'paid',
  terminated: 'disputed',
}

const STATUS_LABEL: Record<ContractStatus, string> = {
  active: 'Active',
  completed: 'Completed',
  terminated: 'Terminated',
}

export function ContractStatusBadge({ status }: { status: ContractStatus }) {
  return <StatusStamp role={STATUS_ROLE[status]}>{STATUS_LABEL[status]}</StatusStamp>
}
