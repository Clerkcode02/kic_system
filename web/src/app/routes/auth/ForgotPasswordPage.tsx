import { Link } from 'react-router-dom'
import { Card } from '@/components'
import { ForgotPasswordForm } from '@/features/auth/components/ForgotPasswordForm'

export function ForgotPasswordPage() {
  return (
    <div className="flex min-h-svh items-center justify-center bg-gray-50 px-4">
      <Card className="w-full max-w-sm">
        <h1 className="mb-1 text-xl font-semibold text-gray-900">Reset your password</h1>
        <p className="mb-6 text-sm text-gray-500">
          Enter your email and we&apos;ll send you a link to reset your password.
        </p>
        <ForgotPasswordForm />
        <p className="mt-6 text-center text-sm text-gray-500">
          <Link to="/login" className="font-medium text-blue-600 hover:underline">
            Back to sign in
          </Link>
        </p>
      </Card>
    </div>
  )
}
