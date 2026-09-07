import { useEffect, useMemo, useRef } from 'react'
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

const HEADER_TOOLBAR = { left: 'prev,next today', center: 'title', right: '' } as const

/**
 * The only place @fullcalendar/react is imported (CLAUDE.md §9.9 — keeps it
 * behind a lib/ wrapper so Phase 2 can swap it for a native calendar).
 * Renders exactly the bookable slots returned by
 * GET /providers/{id}/availability as clickable events for a single day —
 * there is deliberately nothing else on the grid to click.
 *
 * The displayed day is owned by the caller. Two things could otherwise turn
 * that into an update loop, and both are handled below:
 *
 *  1. `datesSet` fires whenever the view is (re)built, including when *we*
 *     navigate it — so a naive handler re-reports the day back to the caller,
 *     the caller re-renders, and the cycle repeats ("Maximum update depth
 *     exceeded"). `displayedDateRef` records the day the calendar is known to
 *     be showing, and both directions of the sync early-return on it, so each
 *     day change propagates exactly once no matter which side started it.
 *  2. Object/array props rebuilt inline on every render (`validRange`,
 *     `headerToolbar`) make FullCalendar tear down and rebuild the view on
 *     each pass, re-firing `datesSet` each time. They are hoisted/memoised.
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
  const displayedDateRef = useRef(date)

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

  // Local "today", not toISOString() — the UTC conversion can bar today or
  // admit yesterday depending on the browser's offset.
  const validRange = useMemo(() => ({ start: toLocalDateString(new Date()) }), [])

  // `initialDate` is read once, so a day picked outside the calendar (the
  // date field in the Schedule step) has to be pushed onto the API.
  useEffect(() => {
    const api = calendarRef.current?.getApi()
    if (!api || !date || displayedDateRef.current === date) return
    displayedDateRef.current = date
    api.gotoDate(date)
  }, [date])

  const handleDatesSet = (arg: DatesSetArg) => {
    const nextDate = toLocalDateString(arg.start)
    // Either our own gotoDate echoing back, or a rebuild of the same view.
    if (nextDate === displayedDateRef.current) return
    displayedDateRef.current = nextDate
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
        headerToolbar={HEADER_TOOLBAR}
        validRange={validRange}
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
