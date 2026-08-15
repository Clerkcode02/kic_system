import { Link } from 'react-router-dom'
import { EmptyState } from '@/components'

export function UnauthorizedPage() {
  return (
    <div className="flex min-h-svh items-center justify-center px-4">
      <EmptyState
        title="You don't have access to this page"
        description="Your account role doesn't permit this action. If you think this is a mistake, contact support."
        action={
          <Link to="/" className="text-sm font-medium text-blue-600 hover:underline">
            Back home
          </Link>
        }
      />
    </div>
  )
}

export function VerifyPendingPage() {
  return (
    <div className="flex min-h-svh items-center justify-center px-4">
      <EmptyState
        title="Verification pending"
        description="Your account is awaiting verification before you can access this area."
      />
    </div>
  )
}

export function SuspendedPage() {
  return (
    <div className="flex min-h-svh items-center justify-center px-4">
      <EmptyState
        title="Account suspended"
        description="Your account has been suspended. Contact support for more information."
      />
    </div>
  )
}

export function NotFoundPage() {
  return (
    <div className="flex min-h-svh items-center justify-center px-4">
      <EmptyState
        title="Page not found"
        action={
          <Link to="/" className="text-sm font-medium text-blue-600 hover:underline">
            Back home
          </Link>
        }
      />
    </div>
  )
}
