import { useState } from 'react'
import { Navigate } from 'react-router-dom'
import { Card, Skeleton } from '@/components'
import { useMyBusiness } from '../hooks/useBusiness'
import { ProfileStep } from './ProfileStep'
import { LocationStep } from './LocationStep'
import { DocumentUploadStep } from './DocumentUploadStep'
import { ReviewSubmitStep } from './ReviewSubmitStep'

const STEPS = ['Profile', 'Location', 'Documents', 'Review'] as const

export function OnboardingWizard() {
  const { data: business, isLoading } = useMyBusiness()
  const [stepIndex, setStepIndex] = useState(0)

  if (isLoading) {
    return (
      <div className="p-6">
        <Skeleton className="h-8 w-48" />
      </div>
    )
  }

  if (!business) {
    return null
  }

  return (
    <div className="mx-auto flex min-h-svh max-w-lg flex-col justify-center px-4 py-8">
      <Card>
        <ol className="mb-6 flex justify-between text-xs font-medium text-gray-500">
          {STEPS.map((label, index) => (
            <li key={label} className={index === stepIndex ? 'text-blue-600' : undefined}>
              {index + 1}. {label}
            </li>
          ))}
        </ol>

        {stepIndex === 0 && <ProfileStep business={business} onNext={() => setStepIndex(1)} />}
        {stepIndex === 1 && (
          <LocationStep
            business={business}
            onNext={() => setStepIndex(2)}
            onBack={() => setStepIndex(0)}
          />
        )}
        {stepIndex === 2 && (
          <DocumentUploadStep onNext={() => setStepIndex(3)} onBack={() => setStepIndex(1)} />
        )}
        {stepIndex === 3 && <ReviewSubmitStep business={business} onBack={() => setStepIndex(2)} />}
      </Card>
    </div>
  )
}

/** Redirects away from the wizard once verification is no longer pending submission. */
export function OnboardingEntry() {
  const { data: business, isLoading } = useMyBusiness()

  if (isLoading) return null
  if (business?.verification_status === 'verified') return <Navigate to="/provider" replace />
  if (business?.verification_status === 'rejected')
    return <Navigate to="/provider/pending" replace />

  return <OnboardingWizard />
}
