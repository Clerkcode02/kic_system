import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Skeleton } from '@/components'
import { useCategories } from '@/features/catalog/hooks/useCategories'
import { useFeaturedProviders } from '@/features/catalog/hooks/useFeaturedProviders'
import { CANADA_POSTAL_CODE_REGEX } from '@/lib/maps'
import { usePageMeta } from '@/lib/meta/usePageMeta'

const HOW_IT_WORKS = [
  {
    title: 'Tell us what you need',
    body: 'Pick a service and a time that suits you. No account required to start — or to finish.',
  },
  {
    title: 'Get a quote from a verified provider',
    body: 'Licensed, background-checked local businesses respond with an itemised quotation.',
  },
  {
    title: 'Pay only when you accept a quote',
    body: 'Nothing is charged until you accept. Payments are processed securely by Stripe.',
  },
]

const TRUST_POINTS = [
  { title: 'Every provider is verified', body: 'Business registration and documents are reviewed by our team before a provider can accept a single booking.' },
  { title: 'Your money is protected', body: 'Card details never touch our servers. Refunds and disputes are handled by our support team.' },
  { title: 'Transparent, itemised pricing', body: 'Labour, materials, fees and tax are broken out on every quotation. No surprises on the day.' },
  { title: 'Real reviews, after the job', body: 'Only customers with a completed booking can leave a review, so ratings reflect actual work.' },
]

const FAQS = [
  {
    q: 'Do I need an account to book?',
    a: 'No. You can book, receive a quotation, pay and track your job entirely as a guest. We email you a private tracking link so you can manage the booking at any time.',
  },
  {
    q: 'When am I charged?',
    a: 'Only after you accept a quotation. Browsing, requesting a booking and receiving a quote are all free, and you can decline or cancel before accepting at no cost.',
  },
  {
    q: 'What if I want an account later?',
    a: 'Register with the same email address and verify it. Any bookings you placed as a guest with that address are attached to your new account automatically.',
  },
  {
    q: 'Where do you operate?',
    a: 'Across Canada. Enter your postal code or city to see verified providers near you.',
  },
  {
    q: 'Can I cancel?',
    a: 'Yes. Cancelling before you accept a quotation is always free. After acceptance a cancellation fee may apply, and we always show you the amount before you confirm.',
  },
]

export function LandingPage() {
  usePageMeta({
    title: 'KIC — Book a verified local service provider, no account needed',
    description:
      'Book trusted, verified home and business service providers across Canada. Get an itemised quote, pay only when you accept, and track your booking without creating an account.',
  })

  const navigate = useNavigate()
  const { data: categories, isLoading: isLoadingCategories } = useCategories()
  const { data: providers, isLoading: isLoadingProviders } = useFeaturedProviders()

  const [categoryId, setCategoryId] = useState('')
  const [location, setLocation] = useState('')
  const [locationError, setLocationError] = useState<string | null>(null)

  const topCategories = (categories ?? []).slice(0, 8)

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    const trimmed = location.trim()

    // A postal code or a city name are both fine; anything shaped like a
    // half-typed postal code is the common mistake worth catching here.
    if (trimmed.length > 0 && /^[A-Za-z]\d/.test(trimmed) && !CANADA_POSTAL_CODE_REGEX.test(trimmed)) {
      setLocationError('Enter a full Canadian postal code (for example M5V 2T6) or a city name.')
      return
    }

    setLocationError(null)

    const params = new URLSearchParams()
    if (categoryId) params.set('category', categoryId)
    if (trimmed) params.set('near', trimmed)

    navigate({ pathname: '/services', search: params.toString() })
  }

  return (
    <>
      {/* Hero — the primary booking entry point. */}
      <section className="border-b border-gray-200 bg-gradient-to-b from-blue-50 to-white">
        <div className="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6 sm:py-16 lg:py-20">
          <div className="max-w-2xl">
            <h1 className="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl lg:text-5xl">
              Book a verified local pro, without creating an account
            </h1>
            <p className="mt-4 text-base text-gray-600 sm:text-lg">
              Tell us what you need and where. Verified Canadian providers send you an itemised
              quote — and you only pay once you accept it.
            </p>
          </div>

          <form
            onSubmit={handleSubmit}
            className="mt-8 flex w-full max-w-3xl flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end"
            aria-labelledby="hero-form-heading"
          >
            <h2 id="hero-form-heading" className="sr-only">
              Find a service near you
            </h2>

            <div className="flex-1">
              <label htmlFor="hero-category" className="block text-sm font-medium text-gray-700">
                What do you need?
              </label>
              {isLoadingCategories ? (
                <Skeleton className="mt-1 h-10 w-full" />
              ) : (
                <select
                  id="hero-category"
                  value={categoryId}
                  onChange={(event) => setCategoryId(event.target.value)}
                  className="mt-1 h-10 w-full rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                  <option value="">Any service</option>
                  {(categories ?? []).map((category) => (
                    <option key={category.id} value={category.slug}>
                      {category.name}
                    </option>
                  ))}
                </select>
              )}
            </div>

            <div className="flex-1">
              <label htmlFor="hero-location" className="block text-sm font-medium text-gray-700">
                Where?
              </label>
              <input
                id="hero-location"
                value={location}
                onChange={(event) => setLocation(event.target.value)}
                placeholder="Postal code or city"
                autoComplete="postal-code"
                aria-invalid={locationError ? true : undefined}
                aria-describedby={locationError ? 'hero-location-error' : undefined}
                className="mt-1 h-10 w-full rounded-md border border-gray-300 px-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
            </div>

            <button
              type="submit"
              className="h-10 shrink-0 rounded-md bg-blue-600 px-6 text-sm font-semibold text-white hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
            >
              Find providers
            </button>
          </form>

          {locationError && (
            <p id="hero-location-error" role="alert" className="mt-2 text-sm text-red-600">
              {locationError}
            </p>
          )}

          <p className="mt-3 text-sm font-medium text-gray-600">
            No account needed — we email you a private link to track your booking.
          </p>
        </div>
      </section>

      {/* Popular categories */}
      <section aria-labelledby="categories-heading" className="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6">
        <h2 id="categories-heading" className="text-2xl font-bold tracking-tight text-gray-900">
          Popular services
        </h2>
        <ul className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          {isLoadingCategories
            ? Array.from({ length: 8 }).map((_, index) => (
                <li key={index}>
                  <Skeleton className="h-20 w-full rounded-lg" />
                </li>
              ))
            : topCategories.map((category) => (
                <li key={category.id}>
                  <Link
                    to={`/services?category=${encodeURIComponent(category.slug)}`}
                    className="flex h-20 items-center justify-center rounded-lg border border-gray-200 px-3 text-center text-sm font-medium text-gray-800 transition hover:border-blue-400 hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                  >
                    {category.name}
                  </Link>
                </li>
              ))}
        </ul>
      </section>

      {/* How it works */}
      <section aria-labelledby="how-heading" className="border-y border-gray-200 bg-gray-50">
        <div className="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6">
          <h2 id="how-heading" className="text-2xl font-bold tracking-tight text-gray-900">
            How it works
          </h2>
          <ol className="mt-6 grid gap-6 md:grid-cols-3">
            {HOW_IT_WORKS.map((step, index) => (
              <li key={step.title} className="rounded-lg border border-gray-200 bg-white p-5">
                <span className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">
                  {index + 1}
                </span>
                <h3 className="mt-3 text-base font-semibold text-gray-900">{step.title}</h3>
                <p className="mt-1 text-sm text-gray-600">{step.body}</p>
              </li>
            ))}
          </ol>
        </div>
      </section>

      {/* Featured verified providers */}
      <section aria-labelledby="providers-heading" className="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6">
        <h2 id="providers-heading" className="text-2xl font-bold tracking-tight text-gray-900">
          Verified providers near you
        </h2>
        <ul className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {isLoadingProviders
            ? Array.from({ length: 3 }).map((_, index) => (
                <li key={index}>
                  <Skeleton className="h-28 w-full rounded-lg" />
                </li>
              ))
            : (providers ?? []).map((provider) => (
                <li key={provider.id}>
                  <Link
                    to={`/services?provider=${encodeURIComponent(provider.id)}`}
                    className="block h-full rounded-lg border border-gray-200 p-5 transition hover:border-blue-400 hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                  >
                    <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">
                      <svg aria-hidden="true" viewBox="0 0 20 20" className="h-3 w-3" fill="currentColor">
                        <path
                          fillRule="evenodd"
                          d="M16.7 5.3a1 1 0 010 1.4l-7.4 7.4a1 1 0 01-1.4 0L3.3 9.5a1 1 0 111.4-1.4l3.9 3.9 6.7-6.7a1 1 0 011.4 0z"
                          clipRule="evenodd"
                        />
                      </svg>
                      Verified
                    </span>
                    <h3 className="mt-2 text-base font-semibold text-gray-900">
                      {provider.legal_name}
                    </h3>
                    <p className="mt-1 text-sm text-gray-600">
                      {provider.rating_avg > 0
                        ? `${provider.rating_avg.toFixed(1)} average rating`
                        : 'New to the platform'}
                    </p>
                  </Link>
                </li>
              ))}
        </ul>
        {!isLoadingProviders && (providers ?? []).length === 0 && (
          <p className="mt-4 text-sm text-gray-600">
            <Link to="/services" className="font-medium text-blue-600 hover:underline">
              Browse all services
            </Link>{' '}
            to see who is available in your area.
          </p>
        )}
      </section>

      {/* Trust */}
      <section aria-labelledby="trust-heading" className="border-y border-gray-200 bg-gray-50">
        <div className="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6">
          <h2 id="trust-heading" className="text-2xl font-bold tracking-tight text-gray-900">
            Why people book with us
          </h2>
          <ul className="mt-6 grid gap-6 sm:grid-cols-2">
            {TRUST_POINTS.map((point) => (
              <li key={point.title} className="rounded-lg border border-gray-200 bg-white p-5">
                <h3 className="text-base font-semibold text-gray-900">{point.title}</h3>
                <p className="mt-1 text-sm text-gray-600">{point.body}</p>
              </li>
            ))}
          </ul>
        </div>
      </section>

      {/* Provider / freelancer CTAs */}
      <section aria-labelledby="join-heading" className="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6">
        <h2 id="join-heading" className="sr-only">
          Join the platform
        </h2>
        <div className="grid gap-4 md:grid-cols-2">
          <div className="rounded-xl border border-gray-200 bg-white p-6">
            <h3 className="text-lg font-semibold text-gray-900">Run a service business?</h3>
            <p className="mt-2 text-sm text-gray-600">
              Get matched with customers in your area, send quotes from your phone, and get paid
              straight to your bank account.
            </p>
            <Link
              to="/register/business"
              className="mt-4 inline-block rounded-md bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
            >
              List your business
            </Link>
          </div>
          <div className="rounded-xl border border-gray-200 bg-white p-6">
            <h3 className="text-lg font-semibold text-gray-900">Freelancing?</h3>
            <p className="mt-2 text-sm text-gray-600">
              Browse posted projects, submit proposals, and get paid from escrow as you complete
              each milestone.
            </p>
            <Link
              to="/register/freelancer"
              className="mt-4 inline-block rounded-md bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
            >
              Join as a freelancer
            </Link>
          </div>
        </div>
      </section>

      {/* FAQ */}
      <section aria-labelledby="faq-heading" className="border-t border-gray-200 bg-gray-50">
        <div className="mx-auto w-full max-w-3xl px-4 py-12 sm:px-6">
          <h2 id="faq-heading" className="text-2xl font-bold tracking-tight text-gray-900">
            Frequently asked questions
          </h2>
          <dl className="mt-6 divide-y divide-gray-200">
            {FAQS.map((faq) => (
              <div key={faq.q} className="py-4">
                <dt>
                  <details className="group">
                    <summary className="flex cursor-pointer list-none items-center justify-between rounded text-base font-medium text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                      {faq.q}
                      <svg
                        aria-hidden="true"
                        viewBox="0 0 20 20"
                        className="h-5 w-5 shrink-0 text-gray-400 transition-transform group-open:rotate-180 motion-reduce:transition-none"
                        fill="currentColor"
                      >
                        <path
                          fillRule="evenodd"
                          d="M5.3 7.3a1 1 0 011.4 0L10 10.6l3.3-3.3a1 1 0 111.4 1.4l-4 4a1 1 0 01-1.4 0l-4-4a1 1 0 010-1.4z"
                          clipRule="evenodd"
                        />
                      </svg>
                    </summary>
                    <dd className="mt-2 text-sm text-gray-600">{faq.a}</dd>
                  </details>
                </dt>
              </div>
            ))}
          </dl>
        </div>
      </section>
    </>
  )
}
