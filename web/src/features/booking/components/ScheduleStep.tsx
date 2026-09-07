import { useEffect, useMemo } from 'react'
import { Button, Input } from '@/components'
import { AvailabilityCalendar, type AvailabilitySlot } from '@/lib/calendar'
import { useProviderAvailability } from '../hooks/useAvailability'
import { formatBookingDate, todayIso } from '../utils/bookingDate'
import { formatTime } from '../utils/slotTime'

interface ScheduleStepProps {
  businessId: string
  date: string
  slot: AvailabilitySlot | null
  onChange: (date: string, slot: AvailabilitySlot | null) => void
  onNext: () => void
}

/**
 * Date *and* time in one step: the date field and the calendar are two ways
 * of setting the same value, and the chosen day drives the availability
 * query that fills the grid.
 *
 * There is deliberately no local copy of the date here. A mirrored
 * `useState` alongside the wizard store meant every day change wrote to two
 * places, and the calendar's `datesSet` wrote back into the second one —
 * the feedback path behind "Maximum update depth exceeded". The wizard
 * store is the single source of truth; this component only renders it.
 */
export function ScheduleStep({ businessId, date, slot, onChange, onNext }: ScheduleStepProps) {
  // Fixed for the life of the step: recomputing "today" on every render
  // would hand the calendar a fresh `min` and invite pointless re-renders.
  const minDate = useMemo(() => todayIso(), [])
  const activeDate = date || minDate

  const { data: slots, isLoading } = useProviderAvailability(businessId, activeDate)

  // "Book again" prefills everything except the date (SRS §6.1 / re-booking),
  // so the step can open with a blank one. Commit the day actually being
  // shown, so what's on screen is what gets submitted. The `!date` guard
  // makes this a one-shot: after it runs, `date` is set and it can't re-enter.
  useEffect(() => {
    if (!date) onChange(minDate, null)
  }, [date, minDate, onChange])

  // Changing the day always clears the slot: the times on screen were
  // fetched for the previous day, and carrying one across would submit a
  // slot the provider never offered on the new date. Re-selecting the day
  // already shown is a no-op rather than a state write, so clicking around
  // the calendar can't churn the wizard.
  const handleDateChange = (nextDate: string) => {
    if (nextDate === activeDate) return
    onChange(nextDate, null)
  }

  const handleDateInput = (value: string) => {
    // A native date input reports '' mid-edit and can report a past date
    // when typed rather than picked; neither is a day we can look up.
    if (!value || value < minDate) return
    handleDateChange(value)
  }

  // Picking a time keeps the day exactly as it is — only the slot moves.
  const handleSlotSelect = (selected: AvailabilitySlot) => {
    if (selected.start === slot?.start && selected.end === slot?.end) return
    onChange(activeDate, selected)
  }

  return (
    <div className="flex flex-col gap-4">
      <h2 className="text-base font-semibold text-gray-900">Choose a date and time</h2>

      {/* The calendar's prev/next arrows move the day too, but only one day
          at a time — this is the direct way to reach a date further out, and
          both write through the same handler. */}
      <Input
        type="date"
        name="scheduled_date"
        label="Date"
        value={activeDate}
        min={minDate}
        onChange={(event) => handleDateInput(event.target.value)}
      />

      <AvailabilityCalendar
        date={activeDate}
        slots={slots ?? []}
        selectedSlot={slot}
        onDateChange={handleDateChange}
        onSlotSelect={handleSlotSelect}
        isLoading={isLoading}
      />

      <p className="text-sm text-gray-600" aria-live="polite">
        {slot ? (
          <>
            Selected:{' '}
            <span className="font-medium text-gray-900">
              {formatBookingDate(activeDate)} at {formatTime(slot.start)} – {formatTime(slot.end)}
            </span>
          </>
        ) : (
          <>
            Pick an available time on{' '}
            <span className="font-medium">{formatBookingDate(activeDate)}</span>.
          </>
        )}
      </p>

      <Button type="button" disabled={!slot} onClick={onNext} className="self-end">
        Continue
      </Button>
    </div>
  )
}
