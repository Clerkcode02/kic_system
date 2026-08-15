import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { Card, EmptyState, Skeleton } from '@/components'
import { useService } from '@/features/catalog/hooks/useServices'
import type { AvailabilitySlot } from '@/lib/calendar'
import { useIdempotencyKey } from '../hooks/useIdempotencyKey'
import { ScheduleStep } from './ScheduleStep'
import { LocationStep } from './LocationStep'
import { DetailsStep } from './DetailsStep'
import { ReviewStep } from './ReviewStep'

const STEPS = ['Schedule', 'Location', 'Details', 'Review'] as const

function todayIso(): string {
  return new Date().toISOString().slice(0, 10)
}

export function BookingWizard() {
  const { serviceId } = useParams<{ serviceId: string }>()
  const navigate = useNavigate()
  const { data: service, isLoading, isError } = useService(serviceId)
  const { key: idempotencyKey } = useIdempotencyKey()

  const [stepIndex, setStepIndex] = useState(0)
  const [date, setDate] = useState(todayIso())
  const [slot, setSlot] = useState<AvailabilitySlot | null>(null)
  const [addressId, setAddressId] = useState<string | null>(null)
  const [notes, setNotes] = useState('')

  if (isLoading) {
    return (
      <div className="p-6">
        <Skeleton className="h-8 w-64" />
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

  return (
    <div className="mx-auto flex max-w-xl flex-col gap-4 p-4 sm:p-6">
      <Card>
        <ol className="mb-6 flex justify-between text-xs font-medium text-gray-500">
          {STEPS.map((label, index) => (
            <li key={label} className={index === stepIndex ? 'text-blue-600' : undefined}>
              {index + 1}. {label}
            </li>
          ))}
        </ol>

        {stepIndex === 0 && (
          <ScheduleStep
            businessId={service.business.id}
            date={date}
            slot={slot}
            onChange={(nextDate, nextSlot) => {
              setDate(nextDate)
              setSlot(nextSlot)
            }}
            onNext={() => setStepIndex(1)}
          />
        )}

        {stepIndex === 1 && (
          <LocationStep
            addressId={addressId}
            onChange={setAddressId}
            onNext={() => setStepIndex(2)}
            onBack={() => setStepIndex(0)}
          />
        )}

        {stepIndex === 2 && (
          <DetailsStep
            notes={notes}
            onChange={setNotes}
            onNext={() => setStepIndex(3)}
            onBack={() => setStepIndex(1)}
          />
        )}

        {stepIndex === 3 && slot && addressId && (
          <ReviewStep
            service={service}
            date={date}
            slot={slot}
            addressId={addressId}
            notes={notes}
            idempotencyKey={idempotencyKey}
            onBack={() => setStepIndex(2)}
            onSubmitted={(bookingId) => navigate(`/customer/bookings/${bookingId}`)}
          />
        )}
      </Card>
    </div>
  )
}
