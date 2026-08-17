import { type ReactNode, useMemo } from 'react'
import { loadStripe } from '@stripe/stripe-js'
import { Elements } from '@stripe/react-stripe-js'

const publishableKey = import.meta.env.VITE_STRIPE_PUBLISHABLE_KEY as string | undefined

// A single Stripe.js instance for the app's lifetime — loadStripe caches
// the script load internally, but memoizing the promise here avoids
// re-invoking it on every mount.
const stripePromise = publishableKey ? loadStripe(publishableKey) : null

interface StripeElementsProviderProps {
  clientSecret: string
  children: ReactNode
}

export function StripeElementsProvider({ clientSecret, children }: StripeElementsProviderProps) {
  const options = useMemo(
    () => ({
      clientSecret,
      appearance: { theme: 'stripe' as const },
    }),
    [clientSecret],
  )

  if (!stripePromise) {
    return (
      <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
        Payments are not configured (missing Stripe publishable key).
      </p>
    )
  }

  return (
    <Elements stripe={stripePromise} options={options}>
      {children}
    </Elements>
  )
}
