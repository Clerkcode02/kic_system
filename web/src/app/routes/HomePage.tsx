import { Navigate } from 'react-router-dom'
import { useAuth } from '@/app/providers/useAuth'
import type { Role } from '@/features/auth/types'

const ROLE_HOME: Record<Role, string> = {
  customer: '/customer',
  provider: '/provider',
  freelancer: '/freelancer',
  admin: '/admin',
}

export function HomePage() {
  const { user, isLoading, isAuthenticated } = useAuth()

  if (isLoading) return null
  if (!isAuthenticated || !user) return <Navigate to="/login" replace />

  return <Navigate to={ROLE_HOME[user.role]} replace />
}
