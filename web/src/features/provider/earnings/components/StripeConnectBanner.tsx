import toast from 'react-hot-toast'
import { Button, Card, Skeleton } from '@/components'
import { ApiError } from '@/lib/api'
import { useCreateStripeOnboardingLink, useStripeConnectStatus } from '../hooks/useStripeConnect'

/** Redirects the browser to Stripe's own onboarding flow — never collect bank/KYC details ourselves. */
export function StripeConnectBanner() {
  const { data: status, isLoading } = useStripeConnectStatus()
  const { mutateAsync: createLink, isPending } = useCreateStripeOnboardingLink()

  const handleOnboard = async () => {
    try {
      const url = await createLink()
      window.location.href = url
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : 'Could not start Stripe onboarding.',
      )
    }
  }

  if (isLoading) return <Skeleton className="h-20 w-full" />
  if (!status) return null

  const isFullyOnboarded = status.charges_enabled && status.payouts_enabled

  if (isFullyOnboarded) {
    return (
      <Card className="flex items-center justify-between gap-3 bg-green-50">
        <p className="text-sm text-green-800">Stripe payouts are active and ready to receive funds.</p>
      </Card>
    )
  }

  return (
    <Card className="flex items-center justify-between gap-3 bg-amber-50">
      <div>
        <p className="text-sm font-medium text-amber-900">Finish connecting Stripe to get paid</p>
        <p className="text-xs text-amber-800">
          {status.charges_enabled ? 'Payouts are not yet enabled.' : 'Charges are not yet enabled.'}
        </p>
      </div>
      <Button type="button" size="sm" isLoading={isPending} onClick={handleOnboard}>
        Continue onboarding
      </Button>
    </Card>
  )
}
