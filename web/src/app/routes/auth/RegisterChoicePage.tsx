import { Link } from 'react-router-dom'
import { Card } from '@/components'

const OPTIONS = [
  {
    to: '/register/customer',
    title: 'Customer',
    description: 'Book services from verified providers.',
  },
  {
    to: '/register/business',
    title: 'Business',
    description: 'Offer services and respond to bookings.',
  },
  {
    to: '/register/freelancer',
    title: 'Freelancer',
    description: 'Find projects and submit proposals.',
  },
]

export function RegisterChoicePage() {
  return (
    <div className="flex min-h-svh items-center justify-center bg-gray-50 px-4 py-8">
      <div className="w-full max-w-md">
        <h1 className="mb-6 text-center text-xl font-semibold text-gray-900">Create an account</h1>
        <div className="flex flex-col gap-3">
          {OPTIONS.map((option) => (
            <Link key={option.to} to={option.to}>
              <Card className="transition-shadow hover:shadow-md">
                <h2 className="font-medium text-gray-900">{option.title}</h2>
                <p className="mt-1 text-sm text-gray-500">{option.description}</p>
              </Card>
            </Link>
          ))}
        </div>
        <p className="mt-6 text-center text-sm text-gray-500">
          Already have an account?{' '}
          <Link to="/login" className="font-medium text-blue-600 hover:underline">
            Sign in
          </Link>
        </p>
      </div>
    </div>
  )
}
