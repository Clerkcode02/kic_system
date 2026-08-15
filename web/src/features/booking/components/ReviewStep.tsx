import toast from 'react-hot-toast'
import { Button, Card } from '@/components'
import { ApiError } from '@/lib/api'
import type { AvailabilitySlot } from '@/lib/calendar'
import { useCreateBooking } from '../hooks/useCreateBooking'
import { useMyAddresses } from '../hooks/useAddresses'
import { formatTime, timeOf } from '../utils/slotTime'
import type { ServiceDetail } from '@/features/catalog/types'

interface ReviewStepProps {
  service: ServiceDetail
  date: string
  slot: AvailabilitySlot
  addressId: string
  notes: string
  idempotencyKey: string
  onBack: () => void
  onSubmitted: (bookingId: string) => void
}

export function ReviewStep({
  service,
  date,
  slot,
  addressId,
  notes,
  idempotencyKey,
  onBack,
  onSubmitted,
}: ReviewStepProps) {
  const { data: addresses } = useMyAddresses()
  const address = addresses?.find((a) => a.id === addressId)
  const { mutateAsync, isPending } = useCreateBooking()

  const handleSubmit = async () => {
    try {
      const booking = await mutateAsync({
        payload: {
          service_id: service.id,
          address_id: addressId,
          scheduled_date: date,
          time_slot_start: timeOf(slot.start),
          time_slot_end: timeOf(slot.end),
          notes: notes || undefined,
        },
        idempotencyKey,
      })
      toast.success('Booking requested.')
      onSubmitted(booking.id)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not submit this booking.')
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <h2 className="text-base font-semibold text-gray-900">Review and submit</h2>
      <Card className="flex flex-col gap-2 text-sm">
        <div className="flex justify-between">
          <span className="text-gray-500">Service</span>
          <span className="font-medium text-gray-900">{service.title}</span>
        </div>
        <div className="flex justify-between">
          <span className="text-gray-500">Provider</span>
          <span className="font-medium text-gray-900">{service.business.legal_name}</span>
        </div>
        <div className="flex justify-between">
          <span className="text-gray-500">Date</span>
          <span className="font-medium text-gray-900">{date}</span>
        </div>
        <div className="flex justify-between">
          <span className="text-gray-500">Time</span>
          <span className="font-medium text-gray-900">
            {formatTime(slot.start)} – {formatTime(slot.end)}
          </span>
        </div>
        {address && (
          <div className="flex justify-between">
            <span className="text-gray-500">Location</span>
            <span className="text-right font-medium text-gray-900">
              {address.street}, {address.city}
            </span>
          </div>
        )}
        {notes && (
          <div className="flex justify-between">
            <span className="text-gray-500">Notes</span>
            <span className="text-right font-medium text-gray-900">{notes}</span>
          </div>
        )}
        <div className="flex justify-between border-t border-gray-200 pt-2">
          <span className="text-gray-500">Price</span>
          <span className="font-semibold text-gray-900">
            {service.pricing_type === 'fixed'
              ? `$${service.base_price} ${service.currency}`
              : 'Provider will send a quotation'}
          </span>
        </div>
      </Card>
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
