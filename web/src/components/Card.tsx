import type { HTMLAttributes } from 'react'
import { cn } from '@/lib/cn'

interface CardProps extends HTMLAttributes<HTMLDivElement> {
  /** 'ticket' is the Trade Docket world's paper-panel treatment (freelancer area only). */
  variant?: 'default' | 'ticket'
}

const variantClasses = {
  default: 'rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-6',
  ticket: 'rounded-sm border border-docket-line bg-docket-paper p-4 shadow-ticket sm:p-6',
}

export function Card({ className, variant = 'default', ...rest }: CardProps) {
  return <div className={cn(variantClasses[variant], className)} {...rest} />
}
