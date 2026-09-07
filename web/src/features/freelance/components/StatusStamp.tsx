import { cn } from '@/lib/cn'

/**
 * Trade Docket world: a hand-stamped status mark. Color carries the status
 * (a full ink band, never a small muted badge), but color is never the only
 * signal — each role also gets its own border pattern, so the mark still
 * reads correctly without color vision. Text is the third, always-present
 * signal.
 */
export type StampRole = 'paid' | 'awaiting' | 'active' | 'disputed' | 'neutral'

const ROLE_CLASSES: Record<StampRole, string> = {
  paid: 'border-[3px] border-solid border-stamp-paid bg-stamp-paid-tint text-stamp-paid',
  awaiting: 'border-2 border-dashed border-stamp-awaiting bg-stamp-awaiting-tint text-stamp-awaiting',
  active: 'border-2 border-solid border-stamp-active bg-stamp-active-tint text-stamp-active',
  disputed: 'border-4 border-double border-stamp-disputed bg-stamp-disputed-tint text-stamp-disputed',
  neutral: 'border-2 border-dotted border-stamp-neutral bg-stamp-neutral-tint text-stamp-neutral',
}

interface StatusStampProps {
  role: StampRole
  children: React.ReactNode
  className?: string
  /** Skip the stamp-landing entrance — used when many stamps mount at once in a dense list. */
  animate?: boolean
}

export function StatusStamp({ role, children, className, animate = true }: StatusStampProps) {
  return (
    <span
      className={cn(
        'inline-flex -rotate-2 items-center rounded-sm px-2 py-0.5 text-[0.6875rem] font-bold uppercase tracking-wide',
        ROLE_CLASSES[role],
        animate && 'animate-stamp-land',
        className,
      )}
    >
      {children}
    </span>
  )
}
