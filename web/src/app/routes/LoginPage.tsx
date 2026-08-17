import { Link, Navigate, useSearchParams } from 'react-router-dom'
import { useAuth } from '@/app/providers/useAuth'
import { LoginForm } from '@/features/auth/components/LoginForm'
import { Card } from '@/components'
import { usePageMeta } from '@/lib/meta/usePageMeta'
import { safeNextPath, withNext } from '@/lib/navigation/nextParam'

const REGISTER_OPTIONS = [
  {
    to: '/register/customer',
    title: 'Customer',
    description: 'Save your booking history, track jobs and re-book in a couple of clicks.',
  },
  {
    to: '/register/business',
    title: 'Service provider',
    description: 'List your business, send quotations and get paid to your bank account.',
  },
  {
    to: '/register/freelancer',
    title: 'Freelancer',
    description: 'Browse projects, submit proposals and get paid from escrow per milestone.',
  },
]

/**
 * Sign-in plus a "register as…" chooser, routing into the **existing**
 * signup flows rather than duplicating them.
 *
 * `?next=` is honoured throughout so the guest → register → claim detour
 * (SRS §6.1) returns the user to where they were — most often the tracking
 * page for the booking they just placed.
 */
export function LoginPage() {
  usePageMeta({
    title: 'Sign in — KIC',
    description: 'Sign in to your KIC account, or create one as a customer, service provider or freelancer.',
  })

  const { isAuthenticated, isLoading } = useAuth()
  const [searchParams] = useSearchParams()
  const next = safeNextPath(searchParams.get('next'))

  if (!isLoading && isAuthenticated) {
    return <Navigate to={next} replace />
  }

  return (
    <div className="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6 sm:py-14">
      <h1 className="text-2xl font-bold tracking-tight text-gray-900">
        Sign in or create an account
      </h1>
      <p className="mt-2 max-w-xl text-sm text-gray-600">
        You don&apos;t need an account to book — an account just keeps your history, tracking and
        re-booking in one place.
      </p>

      <div className="mt-8 grid gap-6 md:grid-cols-2">
        <section aria-labelledby="signin-heading">
          <Card>
            <h2 id="signin-heading" className="mb-5 text-base font-semibold text-gray-900">
              Sign in
            </h2>
            <LoginForm redirectTo={next} />
            <div className="mt-5 text-sm">
              <Link
                to="/forgot-password"
                className="rounded font-medium text-blue-600 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
              >
                Forgot your password?
              </Link>
            </div>
          </Card>
        </section>

        <section aria-labelledby="register-heading">
          <h2 id="register-heading" className="mb-3 text-base font-semibold text-gray-900">
            Register as…
          </h2>
          <ul className="flex flex-col gap-3">
            {REGISTER_OPTIONS.map((option) => (
              <li key={option.to}>
                <Link
                  to={withNext(option.to, next)}
                  className="block rounded-lg border border-gray-200 p-4 transition hover:border-blue-400 hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                >
                  <span className="block font-medium text-gray-900">{option.title}</span>
                  <span className="mt-1 block text-sm text-gray-600">{option.description}</span>
                </Link>
              </li>
            ))}
          </ul>

          <p className="mt-4 text-sm text-gray-600">
            Booked as a guest?{' '}
            <Link
              to="/track"
              className="rounded font-medium text-blue-600 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
            >
              Track your booking
            </Link>{' '}
            without signing in.
          </p>
        </section>
      </div>
    </div>
  )
}
