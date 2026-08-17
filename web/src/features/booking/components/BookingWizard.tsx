import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { Card, EmptyState, Skeleton } from '@/components'
import { useAuth } from '@/app/providers/useAuth'
import { useService } from '@/features/catalog/hooks/useServices'
import { setBookingToken } from '@/lib/api'
import { useBookingWizardStore } from '@/stores/bookingWizardStore'
import { ScheduleStep } from './ScheduleStep'
import { LocationStep } from './LocationStep'
import { DetailsStep } from './DetailsStep'
import { ContactStep } from './ContactStep'
import { ReviewStep } from './ReviewStep'
import { GuestBookingConfirmation } from './GuestBookingConfirmation'
import type { GuestBookingCreated } from '../types.guest'

type WizardStep = 'Schedule' | 'Location' | 'Details' | 'Contact' | 'Review'

/**
 * ONE wizard for both actor kinds (SRS §6.1) — not a guest fork. The step
 * list is computed from whether there's a session: an anonymous visitor
 * gets a Contact step, an authenticated one doesn't (and sees their saved
 * addresses in the Location step instead).
 *
 * Progress lives in a sessionStorage-backed zustand store so a detour to
 * sign in or register mid-flow doesn't lose the form. That store holds
 * client state only — nothing fetched from the API is mirrored into it
 * (CLAUDE.md §6).
 */
export function BookingWizard() {
  const { serviceId } = useParams<{ serviceId: string }>()
  const navigate = useNavigate()
  const { isAuthenticated, isLoading: isLoadingAuth } = useAuth()
  const { data: service, isLoading, isError } = useService(serviceId)

  const wizard = useBookingWizardStore()
  const [guestResult, setGuestResult] = useState<GuestBookingCreated | null>(null)

  // Switching services resets the flow — including the idempotency key, so
  // the new submission can't replay the previous service's response.
  useEffect(() => {
    if (serviceId) wizard.startFor(serviceId)
  }, [serviceId, wizard])

  // An authenticated user has no Contact step — their details come from
  // their account, and sending guest fields while signed in is a 422 by
  // design (SRS §6.1).
  const steps: WizardStep[] = isAuthenticated
    ? ['Schedule', 'Location', 'Details', 'Review']
    : ['Schedule', 'Location', 'Details', 'Contact', 'Review']

  const stepIndex = Math.min(wizard.stepIndex, steps.length - 1)
  const currentStep = steps[stepIndex]
  const returnTo = `/book/${serviceId ?? ''}`

  if (isLoading || isLoadingAuth) {
    return (
      <div className="mx-auto max-w-xl p-4 sm:p-6">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="mt-4 h-64 w-full" />
      </div>
    )
  }

  if (isError || !service) {
    return (
      <div className="p-6">
        <EmptyState title="Service not found" description="Go back and pick a service to book." />
      </div>
    )
  }

  if (guestResult) {
    return (
      <GuestBookingConfirmation
        result={guestResult}
        email={wizard.contact.email}
        onDone={() => {
          wizard.reset()
          setGuestResult(null)
        }}
      />
    )
  }

  const goTo = (index: number) => wizard.setStep(index)
  const indexOf = (label: WizardStep) => steps.indexOf(label)

  return (
    <div className="mx-auto flex max-w-xl flex-col gap-4 p-4 sm:p-6">
      <h1 className="text-xl font-bold tracking-tight text-gray-900">Book {service.title}</h1>

      <Card>
        <ol className="mb-6 flex flex-wrap justify-between gap-x-3 gap-y-1 text-xs font-medium text-gray-500">
          {steps.map((label, index) => (
            <li
              key={label}
              aria-current={index === stepIndex ? 'step' : undefined}
              className={index === stepIndex ? 'text-blue-600' : undefined}
            >
              {index + 1}. {label}
            </li>
          ))}
        </ol>

        {currentStep === 'Schedule' && (
          <ScheduleStep
            businessId={service.business.id}
            date={wizard.date}
            slot={wizard.slot}
            onChange={(nextDate, nextSlot) => wizard.setSchedule(nextDate, nextSlot)}
            onNext={() => goTo(indexOf('Location'))}
          />
        )}

        {currentStep === 'Location' && (
          <LocationStep
            isAuthenticated={isAuthenticated}
            addressId={wizard.addressId}
            address={wizard.address}
            onAddressIdChange={wizard.setAddressId}
            onAddressChange={wizard.setAddress}
            onNext={() => goTo(indexOf('Details'))}
            onBack={() => goTo(indexOf('Schedule'))}
          />
        )}

        {currentStep === 'Details' && (
          <DetailsStep
            notes={wizard.notes}
            onChange={wizard.setNotes}
            onNext={() => goTo(indexOf(isAuthenticated ? 'Review' : 'Contact'))}
            onBack={() => goTo(indexOf('Location'))}
          />
        )}

        {currentStep === 'Contact' && (
          <ContactStep
            contact={wizard.contact}
            onChange={wizard.setContact}
            onNext={() => goTo(indexOf('Review'))}
            onBack={() => goTo(indexOf('Details'))}
            returnTo={returnTo}
          />
        )}

        {currentStep === 'Review' && wizard.slot && (
          <ReviewStep
            isAuthenticated={isAuthenticated}
            service={service}
            date={wizard.date}
            slot={wizard.slot}
            addressId={wizard.addressId}
            address={wizard.address}
            contact={wizard.contact}
            notes={wizard.notes}
            idempotencyKey={wizard.idempotencyKey}
            onBack={() => goTo(indexOf(isAuthenticated ? 'Details' : 'Contact'))}
            onRegisteredSubmit={(bookingId) => {
              wizard.reset()
              navigate(`/customer/bookings/${bookingId}`)
            }}
            onGuestSubmit={(result) => {
              // Straight into the API client and sessionStorage — this is
              // the only moment the plaintext token exists client-side, and
              // it is never rendered into the DOM (SRS §6.1).
              setBookingToken(result.booking.booking_number, result.accessToken)
              setGuestResult(result)
            }}
          />
        )}

        {currentStep === 'Review' && !wizard.slot && (
          <EmptyState
            title="Pick a time first"
            description="Go back to the schedule step and choose an available slot."
          />
        )}
      </Card>
    </div>
  )
}
