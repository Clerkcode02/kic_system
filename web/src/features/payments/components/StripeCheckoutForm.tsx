import { useState } from 'react'
import { PaymentElement, useElements, useStripe } from '@stripe/react-stripe-js'
import { Button } from '@/components'
import { formatMoney } from '@/lib/format/money'

interface StripeCheckoutFormProps {
  amount: string
  onSucceeded: () => void
}

/**
 * Payment Element (not CardElement) so Apple Pay / Google Pay surface
 * automatically for eligible browsers/devices without extra wiring.
 * `redirect: 'if_required'` keeps card + wallet payments inline — Stripe
 * only navigates away for payment methods that mandate a full-page
 * redirect, and handles 3DS challenges as an in-page modal either way.
 *
 * The PaymentIntent itself is never marked paid here (CLAUDE.md §6 "never
 * trust the client") — a Stripe-side `succeeded` just means the card was
 * charged; the caller still has to wait for the webhook-driven status
 * change before treating the booking/milestone as paid.
 */
export function StripeCheckoutForm({ amount, onSucceeded }: StripeCheckoutFormProps) {
  const stripe = useStripe()
  const elements = useElements()
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault()
    if (!stripe || !elements) return

    setIsSubmitting(true)
    setErrorMessage(null)

    const { error, paymentIntent } = await stripe.confirmPayment({
      elements,
      redirect: 'if_required',
      confirmParams: {
        return_url: window.location.href,
      },
    })

    if (error) {
      // card_error / validation_error are recoverable — the PaymentElement
      // stays mounted so the payer can fix the card and resubmit without
      // losing the PaymentIntent (same client_secret, same intent).
      setErrorMessage(error.message ?? 'Your payment could not be processed. Please try again.')
      setIsSubmitting(false)
      return
    }

    if (paymentIntent && (paymentIntent.status === 'succeeded' || paymentIntent.status === 'processing')) {
      onSucceeded()
      return
    }

    setErrorMessage('Payment requires additional confirmation. Please try again.')
    setIsSubmitting(false)
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      <PaymentElement />
      {errorMessage && (
        <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          {errorMessage}
        </p>
      )}
      <Button type="submit" isLoading={isSubmitting} disabled={!stripe || !elements}>
        Pay {formatMoney(amount)}
      </Button>
    </form>
  )
}
