import { useState } from 'react'
import toast from 'react-hot-toast'
import { Badge, Button, Card } from '@/components'
import { ApiError } from '@/lib/api'
import type { Quotation } from '@/features/booking/types'
import { useIdempotencyKey } from '@/features/booking/hooks/useIdempotencyKey'
import { useAcceptQuotation, useRejectQuotation } from '../hooks/useQuotationActions'
import { QuotationLineItems } from './QuotationLineItems'

interface QuotationPanelProps {
  bookingId: string
  quotations: Quotation[]
}

const STATUS_TONE: Record<
  Quotation['status'],
  'neutral' | 'success' | 'warning' | 'danger' | 'info'
> = {
  sent: 'info',
  accepted: 'success',
  rejected: 'danger',
  superseded: 'neutral',
  expired: 'danger',
}

/**
 * No dedicated "request revision" endpoint exists — the SRS's quotation
 * flow is provider-initiated revise, so rejecting with a reason is the
 * customer-side way to prompt a new quotation (CLAUDE.md §5 quotation
 * states: sent -> accepted|rejected|superseded|expired).
 */
export function QuotationPanel({ bookingId, quotations }: QuotationPanelProps) {
  const [isRejecting, setIsRejecting] = useState(false)
  const [reason, setReason] = useState('')
  const { key: idempotencyKey } = useIdempotencyKey()
  const { mutateAsync: accept, isPending: isAccepting } = useAcceptQuotation(bookingId)
  const { mutateAsync: reject, isPending: isRejectingRequest } = useRejectQuotation(bookingId)

  if (quotations.length === 0) return null

  const active = quotations.find((q) => q.status === 'sent') ?? quotations[0]
  const history = quotations.filter((q) => q.id !== active.id)

  const handleAccept = async () => {
    try {
      await accept({ quotationId: active.id, idempotencyKey })
      toast.success('Quotation accepted. Proceed to payment to confirm your booking.')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not accept this quotation.')
    }
  }

  const handleReject = async () => {
    try {
      await reject({ quotationId: active.id, reason: reason || undefined })
      toast.success('Quotation rejected.')
      setIsRejecting(false)
      setReason('')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not reject this quotation.')
    }
  }

  return (
    <Card className="flex flex-col gap-3">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-semibold text-gray-900">
          Quotation {active.revision_number > 0 ? `(revision ${active.revision_number})` : ''}
        </h2>
        <Badge tone={STATUS_TONE[active.status]}>{active.status}</Badge>
      </div>
      <p className="text-xs text-gray-500">
        Valid until {new Date(active.valid_until).toLocaleString()}
      </p>

      <QuotationLineItems quotation={active} />

      {active.status === 'sent' && !isRejecting && (
        <div className="flex gap-3">
          <Button
            type="button"
            variant="secondary"
            onClick={() => setIsRejecting(true)}
            disabled={isAccepting}
            className="flex-1"
          >
            Reject
          </Button>
          <Button type="button" isLoading={isAccepting} onClick={handleAccept} className="flex-1">
            Accept
          </Button>
        </div>
      )}

      {active.status === 'sent' && isRejecting && (
        <div className="flex flex-col gap-2">
          <label htmlFor="reject-reason" className="text-sm font-medium text-gray-700">
            Let the provider know why (optional) — they can send a revised quotation.
          </label>
          <textarea
            id="reject-reason"
            rows={3}
            maxLength={500}
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <div className="flex gap-3">
            <Button
              type="button"
              variant="secondary"
              onClick={() => setIsRejecting(false)}
              className="flex-1"
            >
              Cancel
            </Button>
            <Button
              type="button"
              variant="danger"
              isLoading={isRejectingRequest}
              onClick={handleReject}
              className="flex-1"
            >
              Confirm reject
            </Button>
          </div>
        </div>
      )}

      {history.length > 0 && (
        <details className="text-sm text-gray-500">
          <summary className="cursor-pointer">Previous revisions ({history.length})</summary>
          <div className="mt-2 flex flex-col gap-3">
            {history.map((quotation) => (
              <div key={quotation.id} className="rounded-md border border-gray-200 p-2">
                <div className="mb-1 flex items-center justify-between">
                  <span className="text-xs font-medium text-gray-700">
                    Revision {quotation.revision_number}
                  </span>
                  <Badge tone={STATUS_TONE[quotation.status]}>{quotation.status}</Badge>
                </div>
                <QuotationLineItems quotation={quotation} />
              </div>
            ))}
          </div>
        </details>
      )}
    </Card>
  )
}
