import { Link, useParams } from 'react-router-dom'
import { Badge, Card, EmptyState, Skeleton } from '@/components'
import { formatMoney } from '@/lib/format/money'
import { useContract } from '../hooks/useContracts'
import type { ContractStatus } from '../types'
import { ClientMilestoneCard } from './ClientMilestoneCard'
import { MilestoneSetupForm } from './MilestoneSetupForm'

const STATUS_TONE: Record<ContractStatus, 'neutral' | 'success' | 'info' | 'danger'> = {
  active: 'info',
  completed: 'success',
  terminated: 'danger',
}

const STATUS_LABEL: Record<ContractStatus, string> = {
  active: 'Active',
  completed: 'Completed',
  terminated: 'Terminated',
}

/**
 * The client's view of a contract. Separate from the freelancer's
 * ContractDetailPage, which renders the Trade Docket surface and offers
 * deliverable upload / submit-for-approval — actions a client can't take.
 */
export function ClientContractDetailPage() {
  const { contractId } = useParams<{ contractId: string }>()
  const { data: contract, isLoading, isError } = useContract(contractId)

  if (isLoading) {
    return (
      <div className="flex flex-col gap-4 p-4 sm:p-6">
        <Skeleton className="h-32 rounded-lg" />
        <Skeleton className="h-48 rounded-lg" />
      </div>
    )
  }

  if (isError || !contract) {
    return (
      <div className="p-4 sm:p-6">
        <EmptyState
          title="Couldn't load this contract"
          description="It may have been removed, or you may not have access to it."
        />
      </div>
    )
  }

  const milestones = contract.milestones ?? []
  const paidCents = milestones
    .filter((milestone) => milestone.status === 'paid')
    .reduce((sum, milestone) => sum + Math.round(Number(milestone.amount) * 100), 0)

  return (
    <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4 sm:p-6">
      <Link to="/customer/contracts" className="text-sm font-medium text-blue-600 hover:underline">
        ← All contracts
      </Link>

      <Card>
        <div className="flex flex-col gap-2">
          <div className="flex flex-wrap items-start justify-between gap-2">
            <h1 className="text-lg font-semibold text-gray-900">
              {contract.project?.title ?? 'Contract'}
            </h1>
            <Badge tone={STATUS_TONE[contract.status]}>{STATUS_LABEL[contract.status]}</Badge>
          </div>
          <p className="text-sm tabular-nums text-gray-600">
            Contract total {formatMoney(contract.total_amount)}
            {milestones.length > 0 && (
              <> · {formatMoney((paidCents / 100).toFixed(2))} paid out so far</>
            )}
          </p>
          {contract.project && (
            <Link
              to={`/customer/projects/${contract.project.id}`}
              className="self-start text-sm font-medium text-blue-600 hover:underline"
            >
              View the project
            </Link>
          )}
        </div>
      </Card>

      {/*
        A freshly hired contract has no milestones — nothing can be funded,
        submitted or paid until the client defines them, so the setup form is
        the page's primary action rather than something tucked away.
      */}
      {milestones.length === 0 ? (
        contract.status === 'active' ? (
          <MilestoneSetupForm
            contractId={contract.id}
            contractTotal={contract.total_amount}
            currency={contract.currency}
          />
        ) : (
          <EmptyState
            title="No milestones on this contract"
            description="This contract is no longer active, so milestones can't be added."
          />
        )
      ) : (
        <div className="flex flex-col gap-3">
          <h2 className="text-xs font-medium uppercase tracking-wide text-gray-500">Milestones</h2>
          {milestones.map((milestone) => (
            <ClientMilestoneCard
              key={milestone.id}
              milestone={milestone}
              contractId={contract.id}
            />
          ))}
        </div>
      )}
    </div>
  )
}
