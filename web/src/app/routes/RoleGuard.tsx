import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '@/app/providers/useAuth'
import type { Role } from '@/features/auth/types'
import { Skeleton } from '@/components/Skeleton'

interface RoleGuardProps {
  allowedRoles: Role[]
  requireVerified?: boolean
}

export function RoleGuard({ allowedRoles, requireVerified = false }: RoleGuardProps) {
  const { user, isLoading, isAuthenticated } = useAuth()
  const location = useLocation()

  if (isLoading) {
    return (
      <div className="p-8">
        <Skeleton className="h-8 w-48" />
      </div>
    )
  }

  if (!isAuthenticated || !user) {
    return <Navigate to="/login" state={{ from: location }} replace />
  }

  if (!allowedRoles.includes(user.role)) {
    return <Navigate to="/unauthorized" replace />
  }

  if (requireVerified && !user.email_verified_at) {
    return <Navigate to="/verify-pending" replace />
  }

  if (user.status === 'suspended') {
    return <Navigate to="/suspended" replace />
  }

  return <Outlet />
}
