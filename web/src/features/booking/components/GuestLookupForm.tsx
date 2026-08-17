import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Button, Card, Input } from '@/components'
import { ApiError } from '@/lib/api'
import { useRequestTrackingLink } from '../hooks/useGuestBooking'

const lookupSchema = z.object({
  bookingNumber: z.string().trim().min(1, 'Enter your booking number').max(64),
  email: z.string().trim().email('Enter a valid email address').max(255),
})

type LookupFormValues = z.infer<typeof lookupSchema>

interface GuestLookupFormProps {
  defaultBookingNumber?: string
  /** Explains why the form is being shown, when it follows a dead token. */
  notice?: string | null
}

/**
 * The lookup form (SRS §6.1). The API always answers 202 with an identical
 * body whether or not anything matched, and this renders the **same**
 * confirmation either way — the two must agree, or the UI would reintroduce
 * the enumeration oracle the API was carefully built not to be.
 *
 * A rate-limit (429) is the one error worth surfacing, because it changes
 * what the user should do next.
 */
export function GuestLookupForm({ defaultBookingNumber, notice }: GuestLookupFormProps) {
  const [isSent, setIsSent] = useState(false)
  const { mutateAsync, isPending } = useRequestTrackingLink()

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<LookupFormValues>({
    resolver: zodResolver(lookupSchema),
    defaultValues: { bookingNumber: defaultBookingNumber ?? '', email: '' },
  })

  const onSubmit = async (values: LookupFormValues) => {
    try {
      await mutateAsync({ bookingNumber: values.bookingNumber, email: values.email })
    } catch (error) {
      if (error instanceof ApiError && error.status === 429) {
        setError('root', {
          message: 'Too many attempts. Please wait a few minutes and try again.',
        })
        return
      }
      if (error instanceof ApiError && error.kind === 'validation') {
        for (const [field, messages] of Object.entries(error.fieldErrors)) {
          const key = field === 'booking_number' ? 'bookingNumber' : 'email'
          setError(key as keyof LookupFormValues, { message: messages[0] })
        }
        return
      }
      // Any other failure still shows the neutral confirmation: telling the
      // user "that booking wasn't found" is exactly the signal the endpoint
      // withholds.
    }

    setIsSent(true)
  }

  if (isSent) {
    return (
      <Card className="flex flex-col gap-3">
        <h2 className="text-base font-semibold text-gray-900">Check your email</h2>
        <p className="text-sm text-gray-600">
          If a booking matches those details, we&apos;ve emailed a private tracking link to that
          address. It may take a minute to arrive — remember to check your spam folder.
        </p>
        <button
          type="button"
          onClick={() => setIsSent(false)}
          className="self-start rounded text-sm font-medium text-blue-600 underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
        >
          Try different details
        </button>
      </Card>
    )
  }

  return (
    <Card>
      <h2 className="text-base font-semibold text-gray-900">Find your booking</h2>
      <p className="mt-1 text-sm text-gray-600">
        Enter your booking number and the email address you booked with, and we&apos;ll send you a
        fresh tracking link.
      </p>

      {notice && (
        <p role="status" className="mt-3 rounded-md bg-amber-50 p-3 text-sm text-amber-900">
          {notice}
        </p>
      )}

      <form onSubmit={handleSubmit(onSubmit)} className="mt-4 flex flex-col gap-4" noValidate>
        <Input
          label="Booking number"
          placeholder="BK-000000ABCDEF"
          autoComplete="off"
          error={errors.bookingNumber?.message}
          {...register('bookingNumber')}
        />
        <Input
          label="Email address"
          type="email"
          autoComplete="email"
          error={errors.email?.message}
          {...register('email')}
        />

        {errors.root && (
          <p role="alert" className="text-sm text-red-600">
            {errors.root.message}
          </p>
        )}

        <Button type="submit" isLoading={isPending} className="w-full">
          Email me a tracking link
        </Button>
      </form>
    </Card>
  )
}
