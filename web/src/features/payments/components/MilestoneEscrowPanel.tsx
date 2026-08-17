import { useState } from 'react'
import toast from 'react-hot-toast'
import { Button, Card } from '@/components'
import { ApiError } from '@/lib/api'
import { formatMoney } from '@/lib/format/money'
import { useAuth } from '@/app/providers/useAuth'
import { useIdempotencyKey } from '@/features/booking/hooks/useIdempotencyKey'
import { useApproveMilestone } from '@/features/freelance/contract/hooks/useContracts'
import type { Milestone } from '@/features/freelance/contract/types'
import { createPaymentIntent } from '../api/paymentsApi'
import { PaymentCheckoutModal } from './PaymentCheckoutModal'

const APPROVE_RETRY_ATTEMPTS = 5
const APPROVE_RETRY_DELAY_MS = 2000

function delay(ms: number) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

interface MilestoneEscrowPanelProps {
  milestone: Milestone
  contractId: string
}

/**
 * Client-side counterpart to MilestonePanel's freelancer submit flow: fund
 * this milestone's escrow, then release it to the freelancer. Only rendered
 * for the project's client (role: customer) — the freelancer side never
 * moves money.
 *
 * Money never moves before approval (CLAUDE.md §5): approve just flips the
 * milestone status and queues ReleaseMilestoneEscrowJob, which requires an
 * already-succeeded escrow Payment. Since there's no field on Milestone
 * exposing "is this funded" for polling, funding state is tracked
 * optimistically client-side and the approve call itself is the real
 * gate — a `milestone_not_funded` 409 (webhook still in flight) is retried
 * a few times before surfacing an error.
 */
export function MilestoneEscrowPanel({ milestone, contractId }: MilestoneEscrowPanelProps) {
  const { user } = useAuth()
  const [checkout, setCheckout] = useState<{ paymentId: string; clientSecret: string } | null>(null)
  const [isFunded, setIsFunded] = useState(false)
  const [isConfirmingRelease, setIsConfirmingRelease] = useState(false)
  const [isFunding, setIsFunding] = useState(false)
  const [isReleasing, setIsReleasing] = useState(false)
  const { key: idempotencyKey, renew } = useIdempotencyKey()
  const { mutateAsync: approve } = useApproveMilestone(contractId)

  if (user?.role !== 'customer' || milestone.status !== 'submitted') return null

  const handleFundEscrow = async () => {
    setIsFunding(true)
    try {
      const result = await createPaymentIntent('milestone', milestone.id, idempotencyKey)
      if (!result.clientSecret) {
        toast.error('Could not start escrow funding. Please try again.')
        return
      }
      setCheckout({ paymentId: result.payment.id, clientSecret: result.clientSecret })
      renew()
    } catch (error) {
      if (error instanceof ApiError && error.code === 'milestone_already_funded') {
        setIsFunded(true)
        toast('This milestone is already funded — you can release escrow below.')
        return
      }
      toast.error(error instanceof ApiError ? error.message : 'Could not start escrow funding.')
    } finally {
      setIsFunding(false)
    }
  }

  const handleConfirmRelease = async () => {
    setIsReleasing(true)
    try {
      for (let attempt = 0; attempt < APPROVE_RETRY_ATTEMPTS; attempt++) {
        try {
          await approve(milestone.id)
          toast.success('Escrow released to the freelancer.')
          setIsConfirmingRelease(false)
          return
        } catch (error) {
          const isNotYetFunded = error instanceof ApiError && error.code === 'milestone_not_funded'
          const isLastAttempt = attempt === APPROVE_RETRY_ATTEMPTS - 1
          if (!isNotYetFunded || isLastAttempt) {
            throw error
          }
          await delay(APPROVE_RETRY_DELAY_MS)
        }
      }
    } catch (error) {
      toast.error(
        error instanceof ApiError
          ? error.message
          : 'Could not release escrow. Your payment confirmation may still be in progress — try again shortly.',
      )
    } finally {
      setIsReleasing(false)
    }
  }

  return (
    <Card className="flex flex-col gap-3 border-blue-200 bg-blue-50/40">
      <h3 className="text-sm font-semibold text-gray-900">Escrow</h3>

      {!isFunded && !checkout && (
        <>
          <p className="text-sm text-gray-600">
            Fund escrow for this milestone before releasing payment to the freelancer.
          </p>
          <Button type="button" isLoading={isFunding} onClick={handleFundEscrow} className="self-start">
            Fund escrow ({formatMoney(milestone.amount)})
          </Button>
        </>
      )}

      {isFunded && !isConfirmingRelease && (
        <>
          <p className="text-sm text-gray-600">
            Escrow is funded. Review the deliverables above, then release payment.
          </p>
          <Button type="button" onClick={() => setIsConfirmingRelease(true)} className="self-start">
            Release {formatMoney(milestone.amount)} to freelancer
          </Button>
        </>
      )}

      {isFunded && isConfirmingRelease && (
        <div className="flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50 p-3">
          <p className="text-sm font-medium text-amber-900">
            This releases {formatMoney(milestone.amount)} to the freelancer. This cannot be undone.
          </p>
          <div className="flex gap-3">
            <Button
              type="button"
              variant="secondary"
              onClick={() => setIsConfirmingRelease(false)}
              disabled={isReleasing}
              className="flex-1"
            >
              Cancel
            </Button>
            <Button
              type="button"
              isLoading={isReleasing}
              onClick={handleConfirmRelease}
              className="flex-1"
            >
              Confirm release
            </Button>
          </div>
        </div>
      )}

      {checkout && (
        <PaymentCheckoutModal
          isOpen
          onClose={() => setCheckout(null)}
          title="Fund milestone escrow"
          payableType="milestone"
          payableId={milestone.id}
          paymentId={checkout.paymentId}
          clientSecret={checkout.clientSecret}
          amount={milestone.amount}
          isConfirmed
          onConfirmed={() => setIsFunded(true)}
        />
      )}
    </Card>
  )
}
