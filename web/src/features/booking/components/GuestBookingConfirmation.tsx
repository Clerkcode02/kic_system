import { Link, useNavigate } from 'react-router-dom'
import { Button, Card } from '@/components'
import type { GuestBookingCreated } from '../types.guest'

interface GuestBookingConfirmationProps {
  result: GuestBookingCreated
  /**
   * The address the guest entered. Deliberately passed in rather than read
   * off the booking: the guest resource does not echo contact details back
   * (SRS §6.1), so this comes from the wizard's own state.
   */
  email: string
  onDone: () => void
}

/**
 * The guest confirmation screen (SRS §6.1).
 *
 * The access token is **not** rendered anywhere on this page — not in a
 * link href, not in a data attribute, not in copyable text. It already went
 * into the API client and sessionStorage when the booking was created; the
 * Track button simply navigates to /track, which reads it from there. A
 * token in the DOM would end up in screenshots, in browser history via a
 * copied URL, and in any error-reporting tool that snapshots the page.
 */
export function GuestBookingConfirmation({ result, email, onDone }: GuestBookingConfirmationProps) {
  const navigate = useNavigate()
  const { booking } = result

  const trackPath = `/track?booking=${encodeURIComponent(booking.booking_number)}`

  return (
    <div className="mx-auto flex max-w-xl flex-col gap-4 p-4 sm:p-6">
      <Card className="flex flex-col gap-4">
        <div className="flex items-start gap-3">
          <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-100">
            <svg aria-hidden="true" viewBox="0 0 20 20" className="h-5 w-5 text-green-700" fill="currentColor">
              <path
                fillRule="evenodd"
                d="M16.7 5.3a1 1 0 010 1.4l-7.4 7.4a1 1 0 01-1.4 0L3.3 9.5a1 1 0 111.4-1.4l3.9 3.9 6.7-6.7a1 1 0 011.4 0z"
                clipRule="evenodd"
              />
            </svg>
          </span>
          <div>
            <h1 className="text-xl font-bold tracking-tight text-gray-900">
              Booking request sent
            </h1>
            <p className="mt-1 text-sm text-gray-600">
              {booking.provider.display_name} has received your request.
            </p>
          </div>
        </div>

        <dl className="rounded-lg bg-gray-50 p-4 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-gray-500">Booking number</dt>
            <dd className="font-mono font-semibold text-gray-900">{booking.booking_number}</dd>
          </div>
          <div className="mt-2 flex justify-between gap-4">
            <dt className="text-gray-500">Service</dt>
            <dd className="text-right font-medium text-gray-900">{booking.service.title}</dd>
          </div>
          <div className="mt-2 flex justify-between gap-4">
            <dt className="text-gray-500">When</dt>
            <dd className="text-right font-medium text-gray-900">
              {booking.scheduled_date}, {booking.time_slot_start.slice(0, 5)}–
              {booking.time_slot_end.slice(0, 5)}
            </dd>
          </div>
        </dl>

        <section aria-labelledby="next-steps">
          <h2 id="next-steps" className="text-base font-semibold text-gray-900">
            What happens next
          </h2>
          <ol className="mt-2 flex flex-col gap-2 text-sm text-gray-600">
            <li>
              1. The provider reviews your request and{' '}
              {booking.service.pricing_type === 'fixed'
                ? 'confirms your appointment.'
                : 'sends you an itemised quotation.'}
            </li>
            <li>2. You review it and accept or decline — nothing is charged until you accept.</li>
            <li>3. Once you accept and pay, your appointment is confirmed.</li>
          </ol>
        </section>

        <p className="rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
          We&apos;ve emailed a private tracking link to <strong>your email address</strong>. Use it
          any time to check status, accept a quote, pay or cancel. Keep it to yourself — anyone
          with the link can manage this booking.
        </p>

        <Button type="button" onClick={() => navigate(trackPath)} className="w-full">
          Track my booking
        </Button>
      </Card>

      <Card className="flex flex-col gap-3 border-blue-200 bg-blue-50/50">
        <h2 className="text-base font-semibold text-gray-900">Want your bookings in one place?</h2>
        <p className="text-sm text-gray-700">
          Create a free account with the same email address you used here. Once you verify that
          address, <strong>this booking is attached to your account automatically</strong> — along
          with your history, status tracking and one-click re-booking. You&apos;ll also be able to
          leave a review when the job is done.
        </p>
        <Link
          to={`/register/customer?email=${encodeURIComponent(email)}&next=${encodeURIComponent(trackPath)}`}
          className="inline-block rounded-md bg-blue-600 px-5 py-2.5 text-center text-sm font-semibold text-white hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
        >
          Create my account
        </Link>
      </Card>

      <button
        type="button"
        onClick={onDone}
        className="self-center rounded text-sm font-medium text-gray-600 underline hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
      >
        Book something else
      </button>
    </div>
  )
}
