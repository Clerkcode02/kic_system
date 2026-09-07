import { cn } from '@/lib/cn'
import type { MilestoneStatus } from '../types'

/**
 * Trade Docket world raise (from the declined orizuru-crane challenger): a
 * milestone's whole path stays visible as a persistent stepped strip —
 * history never collapses away to just the current stamp. The API only
 * exposes current status (no state log), so this renders the fixed pipeline
 * every milestone travels and marks how far this one has gotten, rather than
 * a literal audit trail.
 */
const STAGES: { key: MilestoneStatus; label: string }[] = [
  { key: 'pending', label: 'Pending' },
  { key: 'submitted', label: 'Submitted' },
  { key: 'approved', label: 'Approved' },
  { key: 'paid', label: 'Paid' },
]

function stageIndex(status: MilestoneStatus): number {
  if (status === 'disputed') return 1
  return STAGES.findIndex((stage) => stage.key === status)
}

export function MilestoneTimeline({ status }: { status: MilestoneStatus }) {
  const currentIndex = stageIndex(status)
  const isDisputed = status === 'disputed'

  return (
    <ol className="flex items-center" aria-label="Milestone progress">
      {STAGES.map((stage, index) => {
        const isDone = index < currentIndex
        const isCurrent = index === currentIndex
        const isCurrentDisputed = isCurrent && isDisputed
        const isFuture = index > currentIndex

        return (
          <li key={stage.key} className="flex flex-1 items-center last:flex-none">
            <div className="flex flex-col items-center gap-1">
              <span
                aria-current={isCurrent ? 'step' : undefined}
                className={cn(
                  'h-3 w-3 rounded-full border-2',
                  isCurrentDisputed && 'border-stamp-disputed bg-stamp-disputed',
                  isCurrent && !isCurrentDisputed && 'border-stamp-active bg-stamp-active',
                  isDone && 'border-stamp-paid bg-stamp-paid',
                  isFuture && 'border-docket-line bg-docket-paper',
                )}
              />
              <span
                className={cn(
                  'whitespace-nowrap font-mono text-[0.625rem] font-semibold uppercase tracking-wide',
                  isCurrentDisputed && 'text-stamp-disputed',
                  isCurrent && !isCurrentDisputed && 'text-stamp-active',
                  isDone && 'text-stamp-paid',
                  isFuture && 'text-docket-soft',
                )}
              >
                {isCurrentDisputed ? 'Disputed' : stage.label}
              </span>
            </div>
            {index < STAGES.length - 1 && (
              <div
                className={cn(
                  'mx-2 h-0 flex-1 border-t-2',
                  index < currentIndex ? 'border-solid border-stamp-paid' : 'border-dashed border-docket-line',
                )}
              />
            )}
          </li>
        )
      })}
    </ol>
  )
}
