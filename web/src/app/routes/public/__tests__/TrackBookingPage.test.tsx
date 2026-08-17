import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Routes, Route } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { TrackBookingPage } from '../TrackBookingPage'
import { clearBookingToken, getBookingToken } from '@/lib/api'
import type { GuestBooking } from '@/features/booking/types.guest'

const fetchGuestBooking = vi.hoisted(() => vi.fn())

vi.mock('@/features/booking/api/guestBookingApi', () => ({
  fetchGuestBooking,
  cancelGuestBooking: vi.fn(),
  acceptGuestQuotation: vi.fn(),
  rejectGuestQuotation: vi.fn(),
  requestGuestTrackingLink: vi.fn(),
}))

const TOKEN = 'a'.repeat(64)

const booking: GuestBooking = {
  booking_number: 'BK-250817ABC123',
  status: 'waiting_for_quotation',
  payment_status: 'unpaid',
  scheduled_date: '2026-09-01',
  time_slot_start: '09:00:00',
  time_slot_end: '11:00:00',
  notes: null,
  service: { title: 'Drain cleaning', pricing_type: 'quote', base_price: '0.00', currency: 'CAD' },
  provider: { display_name: 'Acme Plumbing', rating_avg: 4.8 },
  service_address: {
    line1: '55 Front St W',
    line2: null,
    city: 'Toronto',
    province: 'ON',
    postal_code: 'M5J 1E6',
  },
  quotation: null,
  timeline: [
    {
      from_status: null,
      to_status: 'waiting_for_quotation',
      note: 'Booking requested by customer.',
      occurred_at: '2026-08-17T10:00:00Z',
    },
  ],
  created_at: '2026-08-17T10:00:00Z',
}

function renderAt(path: string) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[path]}>
        <Routes>
          <Route path="/track" element={<TrackBookingPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeEach(() => {
  clearBookingToken()
  sessionStorage.clear()
  fetchGuestBooking.mockReset()
  window.history.replaceState({}, '', '/track?booking=BK-250817ABC123&token=' + TOKEN)
})

describe('TrackBookingPage', () => {
  it('exchanges ?token= into the API client and strips it from the URL', async () => {
    fetchGuestBooking.mockResolvedValue(booking)

    renderAt(`/track?booking=BK-250817ABC123&token=${TOKEN}`)

    await waitFor(() => {
      expect(screen.getByText(/Booking BK-250817ABC123/)).toBeInTheDocument()
    })

    // The token is held for API calls…
    expect(getBookingToken()).toBe(TOKEN)

    // …but is gone from the address bar, so it can't reach browser history,
    // a Referer header, or a screenshot.
    expect(window.location.search).not.toContain('token=')
    expect(window.location.search).toContain('booking=BK-250817ABC123')
  })

  it('never renders the token into the DOM', async () => {
    fetchGuestBooking.mockResolvedValue(booking)

    const { container } = renderAt(`/track?booking=BK-250817ABC123&token=${TOKEN}`)

    await waitFor(() => {
      expect(screen.getByText(/Booking BK-250817ABC123/)).toBeInTheDocument()
    })

    expect(container.innerHTML).not.toContain(TOKEN)
  })

  it('falls back to the lookup form — not /login — when the token is rejected', async () => {
    fetchGuestBooking.mockRejectedValue(
      Object.assign(new Error('Resource not found.'), { status: 404, kind: 'unknown' }),
    )

    renderAt(`/track?booking=BK-250817ABC123&token=${TOKEN}`)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /find your booking/i })).toBeInTheDocument()
    })

    // A guest has no account, so a dead token must never route to sign-in.
    expect(screen.queryByText(/sign in/i)).not.toBeInTheDocument()
  })

  it('shows the lookup form when no token is present at all', async () => {
    window.history.replaceState({}, '', '/track')

    renderAt('/track')

    expect(await screen.findByRole('heading', { name: /find your booking/i })).toBeInTheDocument()
    expect(fetchGuestBooking).not.toHaveBeenCalled()
  })
})
