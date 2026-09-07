import { Link } from 'react-router-dom'
import { Card, EmptyState, Skeleton } from '@/components'
import { MoneyFigure, Perforation } from '../../components'
import { MilestoneStatusBadge } from '../../contract/components/MilestoneStatusBadge'
import { useFreelancerDashboard } from '../hooks/useFreelancerDashboard'

export function FreelancerDashboardHome() {
  const { data, isLoading, isError } = useFreelancerDashboard()

  if (isLoading) {
    return (
      <div className="flex flex-col gap-4 p-4 sm:p-6">
        <Skeleton className="h-28 rounded-sm" />
        <Skeleton className="h-48 rounded-sm" />
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

  const attention = [...data.attention_milestones].sort((a, b) =>
    a.status === 'disputed' && b.status !== 'disputed' ? -1 : 0,
  )

  return (
    <div className="flex flex-col gap-6 p-4 sm:p-6">
      {/* The pay-stub ledger — earnings lead, because "is money coming?" is
          the one thing this screen must answer in the first glance. */}
      <Card variant="ticket" className="!p-0 overflow-hidden">
        <div className="flex flex-wrap items-baseline justify-between gap-x-8 gap-y-3 px-4 py-5 sm:px-6">
          <div>
            <p className="font-mono text-xs font-bold uppercase tracking-widest text-docket-soft">
              Lifetime earnings
            </p>
            <MoneyFigure
              amount={data.earnings.total}
              className="text-3xl font-semibold text-docket-ink sm:text-4xl"
            />
          </div>
          <div className="flex gap-8">
            <div>
              <p className="font-mono text-xs font-bold uppercase tracking-widest text-docket-soft">
                Open proposals
              </p>
              <p className="font-mono text-2xl font-semibold tabular-nums text-docket-ink">
                {data.open_proposal_count}
              </p>
            </div>
            <div>
              <p className="font-mono text-xs font-bold uppercase tracking-widest text-docket-soft">
                Active contracts
              </p>
              <p className="font-mono text-2xl font-semibold tabular-nums text-docket-ink">
                {data.active_contract_count}
              </p>
            </div>
          </div>
        </div>
      </Card>

      {/* Ticket-stub docket board — one stamped stub per milestone needing
          this freelancer's attention, disputes surfaced first. */}
      <div>
        <h1 className="mb-3 font-mono text-xs font-bold uppercase tracking-widest text-docket-soft">
          Needs your attention
        </h1>

        {attention.length === 0 && (
          <EmptyState
            title="Nothing needs your attention"
            description="Milestones awaiting approval or disputed by a client will show up here."
          />
        )}

        <div className="flex flex-col divide-y divide-dashed divide-docket-line overflow-hidden rounded-sm border border-docket-line bg-docket-paper shadow-ticket">
          {attention.map((milestone) => (
            <Link
              key={milestone.id}
              to={`/freelancer/contracts/${milestone.contract_id}`}
              className="group flex items-center justify-between gap-4 px-4 py-3 transition-colors hover:bg-docket-well sm:px-6"
            >
              <div className="min-w-0">
                <p className="truncate font-semibold text-docket-ink">{milestone.title}</p>
                <p className="font-mono text-sm tabular-nums text-docket-soft">
                  <MoneyFigure amount={milestone.amount} /> · due {milestone.due_date}
                </p>
              </div>
              <MilestoneStatusBadge status={milestone.status} animate={false} />
            </Link>
          ))}
        </div>
        {attention.length > 0 && <Perforation className="mx-2" />}
      </div>
    </div>
  )
}
