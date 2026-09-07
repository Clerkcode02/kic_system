import { useEffect } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { renderWithProviders } from '@/test/render'
import { server } from '@/test/mocks/server'
import { API_BASE_URL, API_VERSION_PATH } from '@/lib/api/config'
import { useBookingWizardStore } from '@/stores/bookingWizardStore'
import type { AvailabilitySlot } from '@/lib/calendar'
import { todayIso } from '../../utils/bookingDate'
import { ScheduleStep } from '../ScheduleStep'

const apiUrl = (path: string) => `${API_BASE_URL}${API_VERSION_PATH}${path}`

/**
 * Stands in for the FullCalendar-backed calendar, and deliberately keeps the
 * one behaviour that caused "Maximum update depth exceeded": FullCalendar's
 * `datesSet` fires on every view build, so the wrapper reports the displayed
 * day back to its parent on every render — including the render caused by
 * the parent reacting to the previous report. If the step ever writes state
 * on a report of the day it already has, this mock turns that into a runaway
 * loop and React throws, failing these tests.
 */
vi.mock('@/lib/calendar', () => ({
  AvailabilityCalendar: (props: {
    date: string
    slots: AvailabilitySlot[]
    selectedSlot: AvailabilitySlot | null
    onDateChange: (date: string) => void
    onSlotSelect: (slot: AvailabilitySlot) => void
  }) => {
    const { date, onDateChange } = props
    useEffect(() => {
      onDateChange(date)
    })
    return (
      <div data-testid="calendar" data-date={date}>
        {props.slots.map((slot) => (
          <button key={slot.start} type="button" onClick={() => props.onSlotSelect(slot)}>
            slot {slot.start}
          </button>
        ))}
      </div>
    )
  },
}))

const today = todayIso()
const laterDate = '2027-04-15'

const slotsByDate: Record<string, AvailabilitySlot[]> = {
  [today]: [{ start: `${today}T09:00:00-04:00`, end: `${today}T10:00:00-04:00` }],
  [laterDate]: [{ start: `${laterDate}T13:00:00-04:00`, end: `${laterDate}T14:00:00-04:00` }],
}

const requestedDates: string[] = []

beforeEach(() => {
  sessionStorage.clear()
  useBookingWizardStore.getState().reset()
  requestedDates.length = 0
  server.use(
    http.get(apiUrl('/providers/biz-1/availability'), ({ request }) => {
      const date = new URL(request.url).searchParams.get('date') ?? ''
      requestedDates.push(date)
      return HttpResponse.json({ data: { date, slots: slotsByDate[date] ?? [] } })
    }),
  )
})

/** Wired to the real wizard store, the way BookingWizard wires it. */
function Harness() {
  const date = useBookingWizardStore((state) => state.date)
  const slot = useBookingWizardStore((state) => state.slot)
  const setSchedule = useBookingWizardStore((state) => state.setSchedule)

  return (
    <ScheduleStep
      businessId="biz-1"
      date={date}
      slot={slot}
      onChange={setSchedule}
      onNext={() => undefined}
    />
  )
}

describe('ScheduleStep', () => {
  it('settles on a date without an update loop, even when the calendar echoes it back', async () => {
    renderWithProviders(<Harness />)

    await waitFor(() => expect(screen.getByRole('button', { name: /slot/ })).toBeInTheDocument())

    expect(screen.getByTestId('calendar')).toHaveAttribute('data-date', today)
    expect(useBookingWizardStore.getState().date).toBe(today)
    // One fetch for the day on screen — the echo must not re-trigger it.
    expect(requestedDates).toEqual([today])
  })

  it('loads the slots for a newly picked date and clears the previous slot', async () => {
    const user = userEvent.setup()
    renderWithProviders(<Harness />)

    await user.click(
      await screen.findByRole('button', { name: `slot ${slotsByDate[today][0].start}` }),
    )
    expect(useBookingWizardStore.getState().slot?.start).toBe(slotsByDate[today][0].start)

    fireEvent.change(screen.getByLabelText('Date'), { target: { value: laterDate } })

    await waitFor(() =>
      expect(
        screen.getByRole('button', { name: `slot ${slotsByDate[laterDate][0].start}` }),
      ).toBeInTheDocument(),
    )

    const state = useBookingWizardStore.getState()
    expect(state.date).toBe(laterDate)
    // The slot belonged to the previous day's availability.
    expect(state.slot).toBeNull()
    expect(requestedDates).toEqual([today, laterDate])
  })

  it('keeps the chosen date when a time is selected', async () => {
    const user = userEvent.setup()
    renderWithProviders(<Harness />)

    fireEvent.change(await screen.findByLabelText('Date'), { target: { value: laterDate } })

    const slotButton = await screen.findByRole('button', {
      name: `slot ${slotsByDate[laterDate][0].start}`,
    })
    await user.click(slotButton)

    const state = useBookingWizardStore.getState()
    expect(state.date).toBe(laterDate)
    expect(state.slot).toEqual(slotsByDate[laterDate][0])
    expect(screen.getByRole('button', { name: 'Continue' })).toBeEnabled()
    // Picking a time must not send the step back for another day's slots.
    expect(requestedDates).toEqual([today, laterDate])
  })

  it('ignores a date before today typed into the date field', async () => {
    renderWithProviders(<Harness />)

    await screen.findByTestId('calendar')
    fireEvent.change(screen.getByLabelText('Date'), { target: { value: '2020-01-01' } })

    expect(useBookingWizardStore.getState().date).toBe(today)
    expect(requestedDates).toEqual([today])
  })

  it('gates Continue until a time is chosen', async () => {
    renderWithProviders(<Harness />)

    await screen.findByTestId('calendar')
    expect(screen.getByRole('button', { name: 'Continue' })).toBeDisabled()
  })
})
