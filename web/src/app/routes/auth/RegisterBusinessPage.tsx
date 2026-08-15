import { Card } from '@/components'
import { RegisterBusinessForm } from '@/features/auth/components/RegisterBusinessForm'

export function RegisterBusinessPage() {
  return (
    <div className="flex min-h-svh items-center justify-center bg-gray-50 px-4 py-8">
      <Card className="w-full max-w-lg">
        <h1 className="mb-1 text-xl font-semibold text-gray-900">Register your business</h1>
        <p className="mb-6 text-sm text-gray-500">
          You&apos;ll complete address details, document upload, and verification after signing up.
        </p>
        <RegisterBusinessForm />
      </Card>
    </div>
  )
}
