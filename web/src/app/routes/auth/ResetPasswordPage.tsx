import { Card } from '@/components'
import { ResetPasswordForm } from '@/features/auth/components/ResetPasswordForm'

export function ResetPasswordPage() {
  return (
    <div className="flex min-h-svh items-center justify-center bg-gray-50 px-4">
      <Card className="w-full max-w-sm">
        <h1 className="mb-6 text-xl font-semibold text-gray-900">Set a new password</h1>
        <ResetPasswordForm />
      </Card>
    </div>
  )
}
