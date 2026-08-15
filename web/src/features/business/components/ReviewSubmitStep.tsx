import { useNavigate } from 'react-router-dom'
import toast from 'react-hot-toast'
import { Button, Card } from '@/components'
import { ApiError } from '@/lib/api'
import { useSubmitBusinessForVerification } from '../hooks/useBusiness'
import type { Business } from '../types'

interface ReviewSubmitStepProps {
  business: Business
  onBack: () => void
}

export function ReviewSubmitStep({ business, onBack }: ReviewSubmitStepProps) {
  const navigate = useNavigate()
  const { mutateAsync, isPending } = useSubmitBusinessForVerification()

  const handleSubmit = async () => {
    try {
      await mutateAsync()
      navigate('/provider/pending', { replace: true })
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not submit for verification.')
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <Card className="bg-gray-50">
        <dl className="flex flex-col gap-2 text-sm">
          <div>
            <dt className="font-medium text-gray-700">Legal name</dt>
            <dd className="text-gray-600">{business.legal_name}</dd>
          </div>
          <div>
            <dt className="font-medium text-gray-700">Address</dt>
            <dd className="text-gray-600">
              {[
                business.address.street,
                business.address.city,
                business.address.province,
                business.address.postal_code,
              ]
                .filter(Boolean)
                .join(', ') || 'Not set'}
            </dd>
          </div>
        </dl>
      </Card>
      <p className="text-sm text-gray-500">
        Submitting sends your profile and documents to our team for review. You&apos;ll be notified
        once it&apos;s approved.
      </p>
      <div className="flex gap-3">
        <Button type="button" variant="secondary" onClick={onBack} className="flex-1">
          Back
        </Button>
        <Button type="button" isLoading={isPending} onClick={handleSubmit} className="flex-1">
          Submit for verification
        </Button>
      </div>
    </div>
  )
}
