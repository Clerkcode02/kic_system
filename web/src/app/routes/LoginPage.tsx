import { Navigate } from 'react-router-dom'
import { useAuth } from '@/app/providers/useAuth'
import { LoginForm } from '@/features/auth/components/LoginForm'
import { Card } from '@/components'

export function LoginPage() {
  const { isAuthenticated, isLoading } = useAuth()

  if (!isLoading && isAuthenticated) {
    return <Navigate to="/" replace />
  }

  return (
    <div className="flex min-h-svh items-center justify-center bg-gray-50 px-4">
      <Card className="w-full max-w-sm">
        <h1 className="mb-6 text-xl font-semibold text-gray-900">Sign in</h1>
        <LoginForm />
      </Card>
    </div>
  )
}
