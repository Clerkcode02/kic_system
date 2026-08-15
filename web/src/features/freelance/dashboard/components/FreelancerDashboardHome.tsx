import { Link } from 'react-router-dom'
import { Badge, Card, EmptyState, Skeleton } from '@/components'
import { useFreelancerDashboard } from '../hooks/useFreelancerDashboard'

export function FreelancerDashboardHome() {
  const { data, isLoading, isError } = useFreelancerDashboard()

  if (isLoading) {
    return (
      <div className="flex flex-col gap-4 p-4 sm:p-6">
        <Skeleton className="h-24 rounded-lg" />
        <Skeleton className="h-40 rounded-lg" />
      </div>
    )
  }

  if (isError || !data) {
    return (
      <div className="p-4 sm:p-6">
        <EmptyState title="Couldn't load your dashboard" description="Please try again." />
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <h1 className="text-lg font-semibold text-gray-900">Freelancer dashboard</h1>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <Card>
          <p className="text-sm text-gray-500">Open proposals</p>
          <p className="text-2xl font-semibold text-gray-900">{data.open_proposal_count}</p>
        </Card>
        <Card>
          <p className="text-sm text-gray-500">Active contracts</p>
          <p className="text-2xl font-semibold text-gray-900">{data.active_contract_count}</p>
        </Card>
        <Card>
          <p className="text-sm text-gray-500">Lifetime earnings</p>
          <p className="text-2xl font-semibold text-gray-900">
            ${data.earnings.total} {data.earnings.currency}
          </p>
        </Card>
      </div>

      <Card className="flex flex-col gap-3">
        <h2 className="text-sm font-semibold text-gray-900">Milestones needing attention</h2>
        {data.attention_milestones.length === 0 && (
          <p className="text-sm text-gray-400">Nothing needs your attention right now.</p>
        )}
        {data.attention_milestones.map((milestone) => (
          <Link
            key={milestone.id}
            to={`/freelancer/contracts/${milestone.contract_id}`}
            className="flex items-center justify-between gap-4 rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50"
          >
            <div>
              <p className="text-sm font-medium text-gray-900">{milestone.title}</p>
              <p className="text-xs text-gray-500">
                ${milestone.amount} {milestone.currency}
              </p>
            </div>
            <Badge tone={milestone.status === 'disputed' ? 'danger' : 'info'}>
              {milestone.status === 'disputed' ? 'Disputed' : 'Awaiting approval'}
            </Badge>
          </Link>
        ))}
      </Card>
    </div>
  )
}
