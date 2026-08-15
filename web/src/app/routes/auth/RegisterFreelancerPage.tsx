import { Card } from '@/components'
import { RegisterFreelancerForm } from '@/features/auth/components/RegisterFreelancerForm'

export function RegisterFreelancerPage() {
  return (
    <div className="flex min-h-svh items-center justify-center bg-gray-50 px-4 py-8">
      <Card className="w-full max-w-lg">
        <h1 className="mb-1 text-xl font-semibold text-gray-900">Register as a freelancer</h1>
        <p className="mb-6 text-sm text-gray-500">
          You&apos;ll add skills and portfolio items after signing up.
        </p>
        <RegisterFreelancerForm />
      </Card>
    </div>
  )
}
