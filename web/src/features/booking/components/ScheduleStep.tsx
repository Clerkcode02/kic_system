import { useState } from 'react'
import { Button } from '@/components'
import { AvailabilityCalendar, type AvailabilitySlot } from '@/lib/calendar'
import { useProviderAvailability } from '../hooks/useAvailability'

interface ScheduleStepProps {
  businessId: string
  date: string
  slot: AvailabilitySlot | null
  onChange: (date: string, slot: AvailabilitySlot | null) => void
  onNext: () => void
}

function todayIso(): string {
  return new Date().toISOString().slice(0, 10)
}

export function ScheduleStep({ businessId, date, slot, onChange, onNext }: ScheduleStepProps) {
  // "Book again" prefills everything except the date (SRS §6.1 / re-booking):
  // the calendar still has to open *somewhere*, so it opens on today while
  // the wizard's own date stays blank until the user picks a slot. Continue
  // is gated on that slot, so a blank date can never be submitted.
  const [localDate, setLocalDate] = useState(date || todayIso())
  const { data: slots, isLoading } = useProviderAvailability(businessId, localDate)

  const handleDateChange = (nextDate: string) => {
    setLocalDate(nextDate)
    onChange(nextDate, null)
  }

  return (
    <div className="flex flex-col gap-4">
      <h2 className="text-base font-semibold text-gray-900">Choose a date and time</h2>
      <AvailabilityCalendar
        date={localDate}
        slots={slots ?? []}
        selectedSlot={slot}
        onDateChange={handleDateChange}
        onSlotSelect={(selected) => onChange(localDate, selected)}
        isLoading={isLoading}
      />
      <Button type="button" disabled={!slot} onClick={onNext} className="self-end">
        Continue
      </Button>
    </div>
  )
}
