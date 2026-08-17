import { forwardRef } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { renderWithProviders } from '@/test/render'
import { server } from '@/test/mocks/server'
import { authenticatedAs } from '@/test/mocks/handlers'
import { useBookingWizardStore } from '@/stores/bookingWizardStore'
import { API_BASE_URL, API_VERSION_PATH } from '@/lib/api/config'
import { BookingWizard } from '../BookingWizard'

const apiUrl = (path: string) => `${API_BASE_URL}${API_VERSION_PATH}${path}`

// The wizard defaults its date state to "today" (see the store's INITIAL)
// and only advances it if the user changes the calendar date, which these
// tests don't exercise — so the booking payload always carries today's date.
const todayIso = new Date().toISOString().slice(0, 10)

// Wizard progress is persisted to sessionStorage (SRS §6.1 — surviving a
// sign-in detour), which means it also survives between tests unless reset.
beforeEach(() => {
  sessionStorage.clear()
  useBookingWizardStore.getState().reset()
})

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

// Geocoding is a real network call made once on "Locate on map" (never per
// keystroke). The wizard refuses an inline address without coordinates, so
// the guest path has to go through it — same mocking pattern as
// LocationPicker.test.tsx.
const geocodeAddressMock = vi.hoisted(() => vi.fn())
vi.mock('@/lib/maps/geocoding', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/maps/geocoding')>()
  return { ...actual, geocodeAddress: geocodeAddressMock }
})

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

/** The registered-customer path: a session plus saved addresses. */
function mockHappyPath() {
  server.use(
    authenticatedAs(),
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
      route: '/book/svc-1',
      path: '/book/:serviceId',
    })

    const user = await goToReviewStep()

    await waitFor(() => expect(screen.getByText('Review and submit')).toBeInTheDocument())
    await user.click(screen.getByRole('button', { name: 'Submit request' }))

    await waitFor(() => expect(toastMock.success).toHaveBeenCalledWith('Booking requested.'))
  })

  it('cannot reach the review step without picking a slot and an address', async () => {
    mockHappyPath()
    renderWithProviders(<BookingWizard />, {
      route: '/book/svc-1',
      path: '/book/:serviceId',
    })

    await waitFor(() => expect(screen.getByText('Choose a date and time')).toBeInTheDocument())

    // Continue is disabled on the schedule step until a slot is chosen.
    const continueButton = await screen.findByRole('button', { name: 'Continue' })
    expect(continueButton).toBeDisabled()
  })

  it('supports going back through steps without losing prior selections', async () => {
    mockHappyPath()
    renderWithProviders(<BookingWizard />, {
      route: '/book/svc-1',
      path: '/book/:serviceId',
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
      route: '/book/svc-1',
      path: '/book/:serviceId',
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
      route: '/book/missing',
      path: '/book/:serviceId',
    })

    await waitFor(() => expect(screen.getByText('Service not found')).toBeInTheDocument())
  })
})

describe('BookingWizard — guest path (SRS §6.1)', () => {
  /** Anonymous: no session, and therefore no saved addresses to offer. */
  function mockGuestHappyPath() {
    server.use(
      http.get(apiUrl('/services/svc-1'), () => HttpResponse.json({ data: service })),
      http.get(apiUrl('/providers/biz-1/availability'), () =>
        HttpResponse.json({ data: { date: '2026-08-20', slots } }),
      ),
    )
  }

  async function goToGuestReviewStep() {
    const user = userEvent.setup()

    await waitFor(() => expect(screen.getByText('Choose a date and time')).toBeInTheDocument())

    // Schedule
    await waitFor(() => expect(screen.getByRole('button', { name: /09:00/ })).toBeInTheDocument())
    await user.click(screen.getByRole('button', { name: /09:00/ }))
    await user.click(screen.getByRole('button', { name: 'Continue' }))

    // Location — a guest has no saved addresses, so the inline form is the
    // only option and is open from the start.
    await waitFor(() => expect(screen.getByTestId('map-container')).toBeInTheDocument())
    await user.type(screen.getByLabelText(/street/i), '55 Front St W')
    await user.type(screen.getByLabelText(/city/i), 'Toronto')
    await user.selectOptions(screen.getByLabelText(/province/i), 'ON')
    await user.type(screen.getByLabelText(/postal code/i), 'M5J 1E6')

    geocodeAddressMock.mockResolvedValue({ lat: 43.6426, lng: -79.3871, formattedAddress: 'Toronto, ON' })
    await user.click(screen.getByRole('button', { name: 'Locate on map' }))

    await user.click(screen.getByRole('button', { name: 'Continue' }))

    // Details
    await waitFor(() =>
      expect(screen.getByText('Anything the provider should know?')).toBeInTheDocument(),
    )
    await user.click(screen.getByRole('button', { name: 'Continue' }))

    // Contact — anonymous only.
    await waitFor(() => expect(screen.getByText('Your contact details')).toBeInTheDocument())
    await user.type(screen.getByLabelText(/full name/i), 'Dana Okafor')
    await user.type(screen.getByLabelText(/^email$/i), 'dana@example.com')
    await user.type(screen.getByLabelText(/phone/i), '+14165550143')
    await user.click(screen.getByRole('button', { name: 'Continue' }))

    return user
  }

  it('shows a Contact step to an anonymous visitor and never asks them to sign in', async () => {
    mockGuestHappyPath()

    renderWithProviders(<BookingWizard />, { route: '/book/svc-1', path: '/book/:serviceId' })

    await waitFor(() => expect(screen.getByText('Choose a date and time')).toBeInTheDocument())

    // The step list itself differs by actor kind — five steps, not four.
    expect(screen.getByText(/4\. Contact/)).toBeInTheDocument()
    expect(screen.getByText(/5\. Review/)).toBeInTheDocument()
  })

  it('submits guest contact details inline and stores the one-time token out of the DOM', async () => {
    mockGuestHappyPath()

    let submitted: Record<string, unknown> | null = null
    server.use(
      http.post(apiUrl('/bookings'), async ({ request }) => {
        expect(request.headers.get('Idempotency-Key')).toBeTruthy()
        submitted = (await request.json()) as Record<string, unknown>
        return HttpResponse.json(
          {
            data: {
              booking_number: 'BK-250817ABC123',
              status: 'waiting_for_quotation',
              payment_status: 'unpaid',
              scheduled_date: todayIso,
              time_slot_start: '09:00:00',
              time_slot_end: '10:00:00',
              notes: null,
              service: { title: service.title, pricing_type: 'quote', base_price: '0.00', currency: 'CAD' },
              provider: { display_name: 'Acme Plumbing', rating_avg: 4.5 },
              service_address: {
                line1: '55 Front St W', line2: null, city: 'Toronto',
                province: 'ON', postal_code: 'M5J 1E6',
              },
              quotation: null,
              timeline: [],
              created_at: '2026-08-17T10:00:00Z',
            },
            meta: { access_token: 'z'.repeat(64) },
          },
          { status: 201 },
        )
      }),
    )

    const { container } = renderWithProviders(<BookingWizard />, {
      route: '/book/svc-1',
      path: '/book/:serviceId',
    })

    const user = await goToGuestReviewStep()

    await waitFor(() => expect(screen.getByText('Review and submit')).toBeInTheDocument())
    await user.click(screen.getByRole('button', { name: 'Submit request' }))

    await waitFor(() => expect(screen.getByText('Booking request sent')).toBeInTheDocument())

    // Guest contact triple goes up; no address_id, since a guest has none.
    expect(submitted).toMatchObject({
      service_id: 'svc-1',
      guest_name: 'Dana Okafor',
      guest_email: 'dana@example.com',
      guest_phone: '+14165550143',
      service_address: { line1: '55 Front St W', city: 'Toronto', province: 'ON' },
    })
    expect(submitted).not.toHaveProperty('address_id')

    // The one-time token is held for API calls but must never be rendered —
    // it would otherwise land in screenshots and error-reporting snapshots.
    expect(container.innerHTML).not.toContain('z'.repeat(64))
    expect(sessionStorage.getItem('guest.booking-token')).toContain('z'.repeat(64))
  })

  it('keeps wizard progress across a remount, so a sign-in detour loses nothing', async () => {
    mockGuestHappyPath()

    const first = renderWithProviders(<BookingWizard />, {
      route: '/book/svc-1',
      path: '/book/:serviceId',
    })

    const user = userEvent.setup()
    await waitFor(() => expect(screen.getByRole('button', { name: /09:00/ })).toBeInTheDocument())
    await user.click(screen.getByRole('button', { name: /09:00/ }))
    await user.click(screen.getByRole('button', { name: 'Continue' }))
    await waitFor(() => expect(screen.getByTestId('map-container')).toBeInTheDocument())

    first.unmount()

    renderWithProviders(<BookingWizard />, { route: '/book/svc-1', path: '/book/:serviceId' })

    // Back on the location step, not reset to the beginning.
    await waitFor(() => expect(screen.getByTestId('map-container')).toBeInTheDocument())
  })
})
