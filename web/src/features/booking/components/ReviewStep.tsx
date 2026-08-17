import toast from 'react-hot-toast'
import { Button, Card } from '@/components'
import { ApiError } from '@/lib/api'
import type { ServiceDetail } from '@/features/catalog/types'
import type { WizardAddress, WizardContact, WizardSlot } from '@/stores/bookingWizardStore'
import { useCreateBooking, useCreateGuestBooking } from '../hooks/useCreateBooking'
import { useMyAddresses } from '../hooks/useAddresses'
import { formatTime, timeOf } from '../utils/slotTime'
import type { GuestBookingCreated } from '../types.guest'

interface ReviewStepProps {
  isAuthenticated: boolean
  service: ServiceDetail
  date: string
  slot: WizardSlot
  addressId: string | null
  address: WizardAddress
  contact: WizardContact
  notes: string
  idempotencyKey: string
  onBack: () => void
  onRegisteredSubmit: (bookingId: string) => void
  onGuestSubmit: (result: GuestBookingCreated) => void
}

/**
 * The final step for both actor kinds. The two mutations differ only in
 * their response shape — a registered booking returns the full resource, a
 * guest booking returns the reduced one plus the one-time access token —
 * so the *payload* assembly below is shared and the split happens at the
 * last possible moment.
 *
 * The idempotency key comes from the wizard store and is reused on every
 * retry, so a double-click or a network retry replays the original booking
 * rather than creating a second one (SRS §19).
 */
export function ReviewStep({
  isAuthenticated,
  service,
  date,
  slot,
  addressId,
  address,
  contact,
  notes,
  idempotencyKey,
  onBack,
  onRegisteredSubmit,
  onGuestSubmit,
}: ReviewStepProps) {
  const { data: addresses } = useMyAddresses(isAuthenticated)
  const savedAddress = addresses?.find((candidate) => candidate.id === addressId)

  const { mutateAsync: createRegistered, isPending: isCreatingRegistered } = useCreateBooking()
  const { mutateAsync: createGuest, isPending: isCreatingGuest } = useCreateGuestBooking()

  const isPending = isCreatingRegistered || isCreatingGuest

  const usingSavedAddress = isAuthenticated && Boolean(addressId)

  const displayAddress = usingSavedAddress
    ? `${savedAddress?.street ?? ''}, ${savedAddress?.city ?? ''}`
    : `${address.line1}, ${address.city}`

  const handleSubmit = async () => {
    const base = {
      service_id: service.id,
      scheduled_date: date,
      time_slot_start: timeOf(slot.start),
      time_slot_end: timeOf(slot.end),
      notes: notes || undefined,
    }

    const location = usingSavedAddress
      ? { address_id: addressId as string }
      : {
          service_address: {
            line1: address.line1,
            line2: address.line2 || null,
            city: address.city,
            province: address.province,
            postal_code: address.postal_code,
            lat: address.lat as number,
            lng: address.lng as number,
          },
        }

    try {
      if (isAuthenticated) {
        const booking = await createRegistered({
          payload: { ...base, ...location },
          idempotencyKey,
        })
        toast.success('Booking requested.')
        onRegisteredSubmit(booking.id)
        return
      }

      const result = await createGuest({
        payload: {
          ...base,
          ...location,
          guest_name: contact.name,
          guest_email: contact.email,
          guest_phone: contact.phone,
        },
        idempotencyKey,
      })
      onGuestSubmit(result)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not submit this booking.')
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <h2 className="text-base font-semibold text-gray-900">Review and submit</h2>
      <Card className="flex flex-col gap-2 text-sm">
        <div className="flex justify-between gap-4">
          <span className="text-gray-500">Service</span>
          <span className="text-right font-medium text-gray-900">{service.title}</span>
        </div>
        <div className="flex justify-between gap-4">
          <span className="text-gray-500">Provider</span>
          <span className="text-right font-medium text-gray-900">
            {service.business.legal_name}
          </span>
        </div>
        <div className="flex justify-between gap-4">
          <span className="text-gray-500">Date</span>
          <span className="font-medium text-gray-900">{date}</span>
        </div>
        <div className="flex justify-between gap-4">
          <span className="text-gray-500">Time</span>
          <span className="font-medium text-gray-900">
            {formatTime(slot.start)} – {formatTime(slot.end)}
          </span>
        </div>
        <div className="flex justify-between gap-4">
          <span className="text-gray-500">Location</span>
          <span className="text-right font-medium text-gray-900">{displayAddress}</span>
        </div>
        {!isAuthenticated && (
          <div className="flex justify-between gap-4">
            <span className="text-gray-500">Contact</span>
            <span className="text-right font-medium text-gray-900">
              {contact.name}
              <br />
              {contact.email}
            </span>
          </div>
        )}
        {notes && (
          <div className="flex justify-between gap-4">
            <span className="text-gray-500">Notes</span>
            <span className="text-right font-medium text-gray-900">{notes}</span>
          </div>
        )}
        <div className="flex justify-between gap-4 border-t border-gray-200 pt-2">
          <span className="text-gray-500">Price</span>
          <span className="text-right font-semibold text-gray-900">
            {service.pricing_type === 'fixed'
              ? `$${service.base_price} ${service.currency}`
              : 'Provider will send a quotation'}
          </span>
        </div>
      </Card>

      {service.pricing_type !== 'fixed' && (
        <p className="text-sm text-gray-600">
          You won&apos;t be charged now. The provider will send an itemised quote, and you only pay
          if you accept it.
        </p>
      )}

      <div className="flex gap-3">
        <Button
          type="button"
          variant="secondary"
          onClick={onBack}
          disabled={isPending}
          className="flex-1"
        >
          Back
        </Button>
        <Button type="button" isLoading={isPending} onClick={handleSubmit} className="flex-1">
          Submit request
        </Button>
      </div>
    </div>
  )
}
