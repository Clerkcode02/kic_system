import { cn } from '@/lib/cn'

/** Trade Docket world: a punched tear-perforation between a ticket's regions. */
export function Perforation({ className }: { className?: string }) {
  return <div aria-hidden="true" className={cn('docket-perforation', className)} />
}
