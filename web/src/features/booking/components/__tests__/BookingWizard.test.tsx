import { forwardRef } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { renderWithProviders } from '@/test/render'
import { server } from '@/test/mocks/server'
import { API_BASE_URL, API_VERSION_PATH } from '@/lib/api/config'
import { BookingWizard } from '../BookingWizard'

const apiUrl = (path: string) => `${API_BASE_URL}${API_VERSION_PATH}${path}`

// The wizard defaults its date state to "today" (see todayIso() in
// BookingWizard.tsx) and only advances it if the user changes the calendar
// date, which these tests don't exercise — so the booking payload always
// carries today's date.
const todayIso = new Date().toISOString().slice(0, 10)

// react-hot-toast needs a mounted <Toaster/> (portal + animation) to show
// anything in the DOM; asserting directly on the toast calls is more
// reliable and is what the component actually contracts to do on
// success/failure.
const toastMock = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn() }))
vi.mock('react-hot-toast', () => ({ default: toastMock }))

// react-leaflet/leaflet mocked the same way as LocationPicker.test.tsx / ServiceMapView.test.tsx —
// the wizard only needs LocationStep's "add a new address" path to render, not real map tiles.
vi.mock('react-leaflet', () => ({
  MapContainer: (props: Record<string, unknown>) => (
    <div data-testid="map-container">{props.children as React.ReactNode}</div>
  ),
  TileLayer: () => null,
  Marker: () => null,
  useMap: () => ({ setView: vi.fn(), getZoom: () => 4 }),
  useMapEvents: () => null,
}))
vi.mock('leaflet', () => ({ default: { icon: () => ({}) } }))

// @fullcalendar/react renders real DOM/canvas machinery that isn't relevant to the wizard flow —
// stand in with a fake that exposes one button per bookable slot, driven by the same
// events/eventClick contract AvailabilityCalendar wires up.
vi.mock('@fullcalendar/react', () => ({
  default: forwardRef(function FakeFullCalendar(
    props: {
      events: { title: string; extendedProps: { slot: { start: string; end: string } } }[]
      eventClick: (arg: { event: { extendedProps: { slot: unknown } } }) => void
    },
    _ref: unknown,
  ) {
    return (
      <div>
        {props.events.map((event) => (
          <button
            key={event.extendedProps.slot.start}
            type="button"
            onClick={() => props.eventClick({ event: { extendedProps: event.extendedProps } })}
          >
            {event.extendedProps.slot.start} ({event.title})
          </button>
        ))}
      </div>
    )
  }),
}))
vi.mock('@fullcalendar/timegrid', () => ({ default: {} }))
vi.mock('@fullcalendar/interaction', () => ({ default: {} }))

const service = {
  id: 'svc-1',
  title: 'Kitchen faucet repair',
  pricing_type: 'fixed' as const,
  base_price: '89.00',
  currency: 'CAD',
  estimated_duration_minutes: 60,
  category: { id: 'cat1', name: 'Plumbing', slug: 'plumbing' },
  business: { id: 'biz-1', legal_name: 'Acme Plumbing', rating_avg: 4.5, location: null },
  description: 'Fix a leaky faucet.',
  is_active: true,
  pricing_tiers: [],
}

const addresses = [
  {
    id: 'addr-1',
    label: 'Home',
    street: '1 Main St',
    unit: null,
    city: 'Toronto',
    state_province: 'ON',
    postal_code: 'A1A 1A1',
    country: 'CA',
    lat: 43.6,
    lng: -79.4,
    is_default: true,
  },
]

const slots = [{ start: '2026-08-20T09:00:00-04:00', end: '2026-08-20T10:00:00-04:00' }]

function mockHappyPath() {
  server.use(
    http.get(apiUrl('/services/svc-1'), () => HttpResponse.json({ data: service })),
    http.get(apiUrl('/providers/biz-1/availability'), () =>
      HttpResponse.json({ data: { date: '2026-08-20', slots } }),
    ),
    http.get(apiUrl('/me/addresses'), () => HttpResponse.json({ data: addresses })),
  )
}

async function goToReviewStep() {
  const user = userEvent.setup()

  await waitFor(() => expect(screen.getByText('Choose a date and time')).toBeInTheDocument())

  // Step 1: schedule
  await waitFor(() => expect(screen.getByRole('button', { name: /09:00/ })).toBeInTheDocument())
  await user.click(screen.getByRole('button', { name: /09:00/ }))
  await user.click(screen.getByRole('button', { name: 'Continue' }))

  // Step 2: location
  await waitFor(() => expect(screen.getByText('Home')).toBeInTheDocument())
  await user.click(screen.getByRole('radio'))
  await user.click(screen.getByRole('button', { name: 'Continue' }))

  // Step 3: details
  await waitFor(() =>
    expect(screen.getByText('Anything the provider should know?')).toBeInTheDocument(),
  )
  await user.click(screen.getByRole('button', { name: 'Continue' }))

  return user
}

describe('BookingWizard', () => {
  it('walks schedule -> location -> details -> review and submits the booking', async () => {
    mockHappyPath()
    server.use(
      http.post(apiUrl('/bookings'), async ({ request }) => {
        expect(request.headers.get('Idempotency-Key')).toBeTruthy()
        const body = (await request.json()) as Record<string, unknown>
        expect(body).toMatchObject({
          service_id: 'svc-1',
          address_id: 'addr-1',
          scheduled_date: todayIso,
        })
        return HttpResponse.json({ data: { id: 'booking-1' } }, { status: 201 })
      }),
    )

    renderWithProviders(<BookingWizard />, {
      route: '/customer/book/svc-1',
      path: '/customer/book/:serviceId',
    })

    const user = await goToReviewStep()

    await waitFor(() => expect(screen.getByText('Review and submit')).toBeInTheDocument())
    await user.click(screen.getByRole('button', { name: 'Submit request' }))

    await waitFor(() => expect(toastMock.success).toHaveBeenCalledWith('Booking requested.'))
  })

  it('cannot reach the review step without picking a slot and an address', async () => {
    mockHappyPath()
    renderWithProviders(<BookingWizard />, {
      route: '/customer/book/svc-1',
      path: '/customer/book/:serviceId',
    })

    await waitFor(() => expect(screen.getByText('Choose a date and time')).toBeInTheDocument())

    // Continue is disabled on the schedule step until a slot is chosen.
    const continueButton = await screen.findByRole('button', { name: 'Continue' })
    expect(continueButton).toBeDisabled()
  })

  it('supports going back through steps without losing prior selections', async () => {
    mockHappyPath()
    renderWithProviders(<BookingWizard />, {
      route: '/customer/book/svc-1',
      path: '/customer/book/:serviceId',
    })

    const user = await goToReviewStep()

    await user.click(screen.getByRole('button', { name: 'Back' }))
    await waitFor(() =>
      expect(screen.getByText('Anything the provider should know?')).toBeInTheDocument(),
    )
    await user.click(screen.getByRole('button', { name: 'Back' }))

    // Back at the location step, the previously-picked address is still selected.
    await waitFor(() => expect(screen.getByText('Home')).toBeInTheDocument())
    const radio = screen.getByRole('radio') as HTMLInputElement
    expect(radio.checked).toBe(true)
  })

  it('shows an error toast when booking submission fails', async () => {
    mockHappyPath()
    server.use(
      http.post(apiUrl('/bookings'), () =>
        HttpResponse.json(
          { message: 'This slot was just taken.', errors: {} },
          { status: 409 },
        ),
      ),
    )

    renderWithProviders(<BookingWizard />, {
      route: '/customer/book/svc-1',
      path: '/customer/book/:serviceId',
    })

    const user = await goToReviewStep()
    await user.click(screen.getByRole('button', { name: 'Submit request' }))

    await waitFor(() =>
      expect(toastMock.error).toHaveBeenCalledWith('This slot was just taken.'),
    )
  })

  it('shows an empty state when the service cannot be found', async () => {
    server.use(
      http.get(apiUrl('/services/missing'), () => HttpResponse.json({ message: 'Not found' }, { status: 404 })),
    )

    renderWithProviders(<BookingWizard />, {
      route: '/customer/book/missing',
      path: '/customer/book/:serviceId',
    })

    await waitFor(() => expect(screen.getByText('Service not found')).toBeInTheDocument())
  })
})
