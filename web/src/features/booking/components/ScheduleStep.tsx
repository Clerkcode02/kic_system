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

export function ScheduleStep({ businessId, date, slot, onChange, onNext }: ScheduleStepProps) {
  const [localDate, setLocalDate] = useState(date)
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
