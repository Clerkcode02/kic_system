import { cn } from '@/lib/cn'
import { formatMoney } from '@/lib/format/money'

/**
 * Trade Docket world: every money amount is set in monospace tabular
 * figures, stated flatly — no ambiguity about what's owed.
 */
export function MoneyFigure({
  amount,
  className,
}: {
  amount: string | number
  className?: string
}) {
  return <span className={cn('font-mono tabular-nums', className)}>{formatMoney(amount)}</span>
}
