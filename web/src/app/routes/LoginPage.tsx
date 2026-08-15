import { Link, Navigate } from 'react-router-dom'
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
        <div className="mt-6 flex flex-col gap-2 text-center text-sm">
          <Link to="/forgot-password" className="font-medium text-blue-600 hover:underline">
            Forgot your password?
          </Link>
          <span className="text-gray-500">
            New here?{' '}
            <Link to="/register" className="font-medium text-blue-600 hover:underline">
              Create an account
            </Link>
          </span>
        </div>
      </Card>
    </div>
  )
}
