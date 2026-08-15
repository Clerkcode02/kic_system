import { useMemo, useRef } from 'react'
import FullCalendar from '@fullcalendar/react'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import type { EventClickArg, DatesSetArg } from '@fullcalendar/core'
import { CALENDAR_SLOT_MAX_TIME, CALENDAR_SLOT_MIN_TIME, type AvailabilitySlot } from './config'
import { toLocalDateString } from './dateUtils'

interface AvailabilityCalendarProps {
  date: string
  slots: AvailabilitySlot[]
  selectedSlot: AvailabilitySlot | null
  onDateChange: (date: string) => void
  onSlotSelect: (slot: AvailabilitySlot) => void
  isLoading?: boolean
}

/**
 * The only place @fullcalendar/react is imported (CLAUDE.md §9.9 — keeps it
 * behind a lib/ wrapper so Phase 2 can swap it for a native calendar).
 * Renders exactly the bookable slots returned by
 * GET /providers/{id}/availability as clickable events for a single day —
 * there is deliberately nothing else on the grid to click.
 */
export function AvailabilityCalendar({
  date,
  slots,
  selectedSlot,
  onDateChange,
  onSlotSelect,
  isLoading = false,
}: AvailabilityCalendarProps) {
  const calendarRef = useRef<FullCalendar | null>(null)

  const events = useMemo(
    () =>
      slots.map((slot) => ({
        start: slot.start,
        end: slot.end,
        title: slot.start === selectedSlot?.start ? 'Selected' : 'Available',
        backgroundColor: slot.start === selectedSlot?.start ? '#2563eb' : '#16a34a',
        borderColor: slot.start === selectedSlot?.start ? '#2563eb' : '#16a34a',
        extendedProps: { slot },
      })),
    [slots, selectedSlot],
  )

  const handleDatesSet = (arg: DatesSetArg) => {
    const nextDate = toLocalDateString(arg.start)
    if (nextDate !== date) onDateChange(nextDate)
  }

  const handleEventClick = (arg: EventClickArg) => {
    const slot = arg.event.extendedProps.slot as AvailabilitySlot
    onSlotSelect(slot)
  }

  return (
    <div className="relative rounded-md border border-gray-200">
      {isLoading && (
        <div className="absolute inset-0 z-10 flex items-center justify-center bg-white/60 text-sm text-gray-500">
          Loading availability…
        </div>
      )}
      <FullCalendar
        ref={calendarRef}
        plugins={[timeGridPlugin, interactionPlugin]}
        initialView="timeGridDay"
        initialDate={date}
        headerToolbar={{ left: 'prev,next today', center: 'title', right: '' }}
        validRange={{ start: new Date().toISOString().slice(0, 10) }}
        slotMinTime={CALENDAR_SLOT_MIN_TIME}
        slotMaxTime={CALENDAR_SLOT_MAX_TIME}
        allDaySlot={false}
        height="auto"
        events={events}
        eventClick={handleEventClick}
        datesSet={handleDatesSet}
        eventDisplay="block"
      />
      {!isLoading && slots.length === 0 && (
        <p className="border-t border-gray-200 p-3 text-center text-sm text-gray-500">
          No bookable slots on this date. Try another day.
        </p>
      )}
    </div>
  )
}
