import { useParams } from 'react-router-dom'
import { Card, EmptyState, Skeleton } from '@/components'
import { MoneyFigure } from '../../components'
import { useContract } from '../hooks/useContracts'
import { ContractStatusBadge } from './ContractStatusBadge'
import { MilestonePanel } from './MilestonePanel'

export function ContractDetailPage() {
  const { contractId } = useParams<{ contractId: string }>()
  const { data: contract, isLoading, isError } = useContract(contractId)

  if (isLoading) {
    return (
      <div className="flex flex-col gap-4 p-4 sm:p-6">
        <Skeleton className="h-32 rounded-sm" />
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
    <div className="flex flex-col gap-6 p-4 sm:p-6">
      <Card variant="ticket" className="flex flex-col gap-2">
        <div className="flex items-start justify-between gap-2">
          <h1 className="text-lg font-semibold text-docket-ink">
            {contract.project?.title ?? 'Contract'}
          </h1>
          <ContractStatusBadge status={contract.status} />
        </div>
        <p className="font-mono text-sm tabular-nums text-docket-soft">
          Total <MoneyFigure amount={contract.total_amount} className="text-docket-ink" />
        </p>
      </Card>

      <div className="flex flex-col gap-4">
        <h2 className="font-mono text-xs font-bold uppercase tracking-widest text-docket-soft">
          Milestones
        </h2>
        {(contract.milestones ?? []).length === 0 && (
          <EmptyState title="No milestones on this contract" />
        )}
        <div className="flex flex-col gap-4">
          {(contract.milestones ?? []).map((milestone) => (
            <MilestonePanel key={milestone.id} milestone={milestone} contractId={contract.id} />
          ))}
        </div>
      </div>
    </div>
  )
}
