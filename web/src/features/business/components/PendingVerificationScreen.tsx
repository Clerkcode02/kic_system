import { Navigate } from 'react-router-dom'
import { Card, EmptyState, Skeleton } from '@/components'
import { useMyBusiness } from '../hooks/useBusiness'

export function PendingVerificationScreen() {
  const { data: business, isLoading } = useMyBusiness()

  if (isLoading) {
    return (
      <div className="p-6">
        <Skeleton className="h-8 w-48" />
      </div>
    )
  }

  if (business?.verification_status === 'verified') {
    return <Navigate to="/provider" replace />
  }

  return (
    <div className="flex min-h-svh items-center justify-center px-4">
      <Card className="w-full max-w-sm">
        {business?.verification_status === 'rejected' ? (
          <EmptyState
            title="Verification was not approved"
            description="Review your profile and documents, then contact support for next steps."
          />
        ) : (
          <EmptyState
            title="Verification pending"
            description="Our team is reviewing your business profile and documents. This usually takes 1-2 business days."
          />
        )}
      </Card>
    </div>
  )
}
