import { Link } from 'react-router-dom'
import { Card } from '@/components'
import { RegisterCustomerForm } from '@/features/auth/components/RegisterCustomerForm'

export function RegisterCustomerPage() {
  return (
    <div className="flex min-h-svh items-center justify-center bg-gray-50 px-4 py-8">
      <Card className="w-full max-w-sm">
        <h1 className="mb-1 text-xl font-semibold text-gray-900">Create a customer account</h1>
        <p className="mb-6 text-sm text-gray-500">
          Looking to offer services instead?{' '}
          <Link to="/register/business" className="font-medium text-blue-600 hover:underline">
            Register as a business
          </Link>{' '}
          or{' '}
          <Link to="/register/freelancer" className="font-medium text-blue-600 hover:underline">
            a freelancer
          </Link>
          .
        </p>
        <RegisterCustomerForm />
      </Card>
    </div>
  )
}
