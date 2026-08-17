import { useEffect, useState } from 'react'
import { Modal } from '@/components'
import { formatMoney } from '@/lib/format/money'
import { clearPendingCheckout, savePendingCheckout, type PayableType } from '../lib/pendingCheckout'
import { usePollTimeout } from '../hooks/usePollTimeout'
import { StripeCheckoutForm } from './StripeCheckoutForm'
import { StripeElementsProvider } from './StripeElementsProvider'

type Phase = 'form' | 'processing' | 'confirmed'

interface PaymentCheckoutModalProps {
  isOpen: boolean
  onClose: () => void
  title: string
  payableType: PayableType
  payableId: string
  paymentId: string
  clientSecret: string
  amount: string
  /** Shown above the form when this charge is a deposit, not the full total. */
  depositInfo?: { totalAmount: string; remainingAmount: string }
  /**
   * True once the caller's own polling (of the booking or milestone) sees
   * the webhook-driven status change land. The modal never infers success
   * from Stripe's client-side confirmation alone (CLAUDE.md §6).
   */
  isConfirmed: boolean
  /** Fires once when the modal transitions into the confirmed phase. */
  onConfirmed?: () => void
}

export function PaymentCheckoutModal({
  isOpen,
  onClose,
  title,
  payableType,
  payableId,
  paymentId,
  clientSecret,
  amount,
  depositInfo,
  isConfirmed,
  onConfirmed,
}: PaymentCheckoutModalProps) {
  const [phase, setPhase] = useState<Phase>('form')
  const timedOut = usePollTimeout(phase === 'processing' && !isConfirmed)

  useEffect(() => {
    if (!isOpen) {
      setPhase('form')
      return
    }
    savePendingCheckout(payableType, payableId, { paymentId, clientSecret, amount })
  }, [isOpen, payableType, payableId, paymentId, clientSecret, amount])

  useEffect(() => {
    if (phase === 'processing' && isConfirmed) {
      clearPendingCheckout(payableType, payableId)
      setPhase('confirmed')
      onConfirmed?.()
    }
  }, [phase, isConfirmed, payableType, payableId, onConfirmed])

  const handleClose = () => {
    // Closing before confirmation intentionally leaves the pending-checkout
    // record in place — the PaymentIntent may still be settling, and the
    // next visit should resume it rather than let the payer start a
    // duplicate charge attempt.
    onClose()
  }

  return (
    <Modal isOpen={isOpen} onClose={handleClose} title={title}>
      {phase === 'form' && (
        <div className="flex flex-col gap-4">
          {depositInfo && (
            <div className="flex flex-col gap-1 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm">
              <div className="flex justify-between">
                <span className="text-gray-600">Deposit due now</span>
                <span className="font-semibold text-gray-900">{formatMoney(amount)}</span>
              </div>
              <div className="flex justify-between text-gray-500">
                <span>Remaining balance (due on completion)</span>
                <span>{formatMoney(depositInfo.remainingAmount)}</span>
              </div>
              <div className="flex justify-between border-t border-blue-100 pt-1 text-gray-500">
                <span>Total</span>
                <span>{formatMoney(depositInfo.totalAmount)}</span>
              </div>
            </div>
          )}
          <StripeElementsProvider clientSecret={clientSecret}>
            <StripeCheckoutForm amount={amount} onSucceeded={() => setPhase('processing')} />
          </StripeElementsProvider>
        </div>
      )}

      {phase === 'processing' && (
        <div className="flex flex-col items-center gap-3 py-6 text-center">
          <span
            className="h-8 w-8 animate-spin rounded-full border-2 border-blue-600 border-t-transparent"
            aria-hidden="true"
          />
          <p className="text-sm font-medium text-gray-900">Confirming your payment…</p>
          <p className="text-sm text-gray-500">
            Your card was charged {formatMoney(amount)}. This page will update automatically once
            it's confirmed — please don't close it.
          </p>
          {timedOut && (
            <p className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
              This is taking longer than usual. Your payment is still being confirmed — you can
              safely leave this page and check back, we'll update the status as soon as it lands.
            </p>
          )}
        </div>
      )}

      {phase === 'confirmed' && (
        <div className="flex flex-col items-center gap-3 py-6 text-center">
          <span className="text-2xl" aria-hidden="true">
            ✓
          </span>
          <p className="text-sm font-medium text-gray-900">Payment confirmed</p>
          <p className="text-sm text-gray-500">{formatMoney(amount)} — thank you.</p>
        </div>
      )}
    </Modal>
  )
}
