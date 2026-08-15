import { Navigate } from 'react-router-dom'
import { useAuth } from '@/app/providers/useAuth'
import type { Role } from '@/features/auth/types'

const ROLE_HOME: Record<Role, string> = {
  customer: '/customer',
  provider_owner: '/provider',
  provider_staff: '/provider',
  freelancer: '/freelancer',
  admin: '/admin',
  super_admin: '/admin',
}

export function HomePage() {
  const { user, isLoading, isAuthenticated } = useAuth()

  if (isLoading) return null
  if (!isAuthenticated || !user) return <Navigate to="/login" replace />

  const destination = user.roles.map((role) => ROLE_HOME[role]).find(Boolean) ?? '/login'
  return <Navigate to={destination} replace />
}
