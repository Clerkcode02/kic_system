import { Card } from '@/components'
import type { Quotation } from '@/features/booking/types'
import { QuotationLineItems } from './QuotationLineItems'
import { QuotationStatusBadge } from './QuotationStatusBadge'

/**
 * All quotations sent for a booking, newest revision first, each showing its
 * own server-computed breakdown via the shared `QuotationLineItems`. Used on
 * the provider side alongside `QuotationBuilderForm` — the customer-facing
 * equivalent lives inline in `QuotationPanel`.
 */
export function QuotationHistoryList({ quotations }: { quotations: Quotation[] }) {
  if (quotations.length === 0) return null

  const sorted = [...quotations].sort((a, b) => b.revision_number - a.revision_number)

  return (
    <Card className="flex flex-col gap-3">
      <h2 className="text-sm font-semibold text-gray-900">Quotation history</h2>
      <div className="flex flex-col gap-3">
        {sorted.map((quotation) => (
          <div key={quotation.id} className="rounded-md border border-gray-200 p-3">
            <div className="mb-2 flex items-center justify-between">
              <span className="text-xs font-medium text-gray-700">
                Revision {quotation.revision_number}
              </span>
              <QuotationStatusBadge status={quotation.status} />
            </div>
            <p className="mb-2 text-xs text-gray-500">
              Valid until {new Date(quotation.valid_until).toLocaleString()}
            </p>
            <QuotationLineItems quotation={quotation} />
          </div>
        ))}
      </div>
    </Card>
  )
}
