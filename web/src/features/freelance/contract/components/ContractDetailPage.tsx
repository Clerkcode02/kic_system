import { useParams } from 'react-router-dom'
import { Badge, Card, EmptyState, Skeleton } from '@/components'
import { useContract } from '../hooks/useContracts'
import type { ContractStatus } from '../types'
import { MilestonePanel } from './MilestonePanel'

const STATUS_TONE: Record<ContractStatus, 'neutral' | 'success' | 'warning' | 'danger' | 'info'> = {
  active: 'info',
  completed: 'success',
  terminated: 'danger',
}

export function ContractDetailPage() {
  const { contractId } = useParams<{ contractId: string }>()
  const { data: contract, isLoading, isError } = useContract(contractId)

  if (isLoading) {
    return (
      <div className="flex flex-col gap-4 p-4 sm:p-6">
        <Skeleton className="h-40 rounded-lg" />
      </div>
    )
  }

  if (isError || !contract) {
    return (
      <div className="p-4 sm:p-6">
        <EmptyState title="Couldn't load this contract" description="Please try again." />
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <Card className="flex flex-col gap-2">
        <div className="flex items-start justify-between gap-2">
          <h1 className="text-lg font-semibold text-gray-900">
            {contract.project?.title ?? 'Contract'}
          </h1>
          <Badge tone={STATUS_TONE[contract.status]}>{contract.status}</Badge>
        </div>
        <p className="text-sm text-gray-500">
          Total: ${contract.total_amount} {contract.currency}
        </p>
      </Card>

      <Card className="flex flex-col gap-4">
        <h2 className="text-sm font-semibold text-gray-900">Milestones</h2>
        {(contract.milestones ?? []).length === 0 && (
          <p className="text-sm text-gray-400">No milestones on this contract.</p>
        )}
        {(contract.milestones ?? []).map((milestone) => (
          <MilestonePanel key={milestone.id} milestone={milestone} contractId={contract.id} />
        ))}
      </Card>
    </div>
  )
}
