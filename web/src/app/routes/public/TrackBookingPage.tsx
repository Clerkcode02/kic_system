import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Skeleton } from '@/components'
import { GuestBookingTracker } from '@/features/booking/components/GuestBookingTracker'
import { GuestLookupForm } from '@/features/booking/components/GuestLookupForm'
import { useGuestBooking } from '@/features/booking/hooks/useGuestBooking'
import {
  getBookingToken,
  getTrackedBookingNumber,
  onGuestAccessLost,
  setBookingToken,
} from '@/lib/api'
import { usePageMeta } from '@/lib/meta/usePageMeta'

/**
 * /track — the guest's home for a booking (SRS §6.1).
 *
 * With `?token=`, the token is exchanged into the API client and **stripped
 * from the URL** via `history.replaceState` before anything renders. A
 * credential left in the address bar ends up in browser history, in the
 * Referer header of any outbound link, and in a screenshot of the page.
 *
 * Without one, the lookup form is shown — which always renders the same
 * "check your email" confirmation regardless of whether anything matched.
 */
export function TrackBookingPage() {
  usePageMeta({
    title: 'Track your booking — KIC',
    description:
      'Check the status of your booking, review and accept a quotation, pay, or cancel — no account required.',
  })

  const [searchParams] = useSearchParams()
  const [notice, setNotice] = useState<string | null>(null)

  // Read once, synchronously, before first paint: the token must not
  // survive into a render that could put it in the DOM.
  const [{ bookingNumber, hasToken }] = useState(() => {
    const urlToken = searchParams.get('token')
    const urlBooking = searchParams.get('booking')

    if (urlToken && urlBooking) {
      setBookingToken(urlBooking, urlToken)
    }

    const resolvedBooking = urlBooking ?? getTrackedBookingNumber()

    return {
      bookingNumber: resolvedBooking,
      hasToken: Boolean(urlToken) || getBookingToken() !== null,
    }
  })

  const [tokenLost, setTokenLost] = useState(false)

  // Strip the token from the address bar. `replaceState` rather than a
  // navigation so the credential never enters the history stack at all.
  useEffect(() => {
    if (!searchParams.has('token')) return

    const url = new URL(window.location.href)
    url.searchParams.delete('token')
    window.history.replaceState(window.history.state, '', url.toString())
  }, [searchParams])

  // A 401/404 on the guest path means the token stopped working — expired,
  // revoked by a claim, or wrong. Back to the lookup form, never to /login:
  // a guest has no account to sign in to.
  useEffect(() => {
    onGuestAccessLost(() => {
      setTokenLost(true)
      setNotice(
        'That tracking link has expired or is no longer valid. Enter your details below and we’ll email you a new one.',
      )
    })
  }, [])

  const shouldQuery = hasToken && !tokenLost
  const { data: booking, isLoading, isError } = useGuestBooking(bookingNumber, shouldQuery)

  return (
    <div className="mx-auto w-full max-w-2xl px-4 py-8 sm:px-6 sm:py-12">
      {shouldQuery && isLoading && (
        <div className="flex flex-col gap-4">
          <Skeleton className="h-32 w-full rounded-lg" />
          <Skeleton className="h-48 w-full rounded-lg" />
        </div>
      )}

      {shouldQuery && booking && <GuestBookingTracker booking={booking} />}

      {(!shouldQuery || (isError && !isLoading)) && (
        <>
          <h1 className="text-2xl font-bold tracking-tight text-gray-900">Track your booking</h1>
          <p className="mt-2 text-sm text-gray-600">
            No account needed — we&apos;ll email a private tracking link to the address you booked
            with.
          </p>
          <div className="mt-6">
            <GuestLookupForm
              defaultBookingNumber={bookingNumber ?? undefined}
              notice={notice}
            />
          </div>
        </>
      )}
    </div>
  )
}
