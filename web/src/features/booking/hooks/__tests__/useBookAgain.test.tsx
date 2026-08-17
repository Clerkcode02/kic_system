import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHook, act } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { useBookAgain } from '../useBookAgain'
import { useBookingWizardStore } from '@/stores/bookingWizardStore'
import type { BookingDetail } from '../../types'

const navigate = vi.hoisted(() => vi.fn())

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
  return { ...actual, useNavigate: () => navigate }
})

const completedBooking = {
  id: 'booking-1',
  booking_number: 'BK-250817ABC123',
  scheduled_date: '2026-07-01',
  time_slot_start: '09:00:00',
  time_slot_end: '11:00:00',
  status: 'completed',
  payment_status: 'paid',
  customer: { id: 'user-1', name: 'Dana', is_guest: false },
  service: {
    id: 'service-9',
    title: 'Drain cleaning',
    pricing_type: 'quote',
    base_price: '0.00',
    currency: 'CAD',
  },
  provider: { id: 'business-3', legal_name: 'Acme Plumbing', rating_avg: 4.8 },
  address: {
    id: 'address-2',
    label: 'Home',
    street: '55 Front St W',
    unit: null,
    city: 'Toronto',
    state_province: 'ON',
    postal_code: 'M5J 1E6',
    country: 'CA',
    lat: 43.6426,
    lng: -79.3871,
    is_default: true,
  },
  service_address: {
    line1: '55 Front St W',
    line2: null,
    city: 'Toronto',
    province: 'ON',
    postal_code: 'M5J 1E6',
  },
  notes: null,
  provider_completed_at: '2026-07-01T12:00:00Z',
  status_history: [],
  attachments: [],
  quotations: [],
  created_at: '2026-06-20T10:00:00Z',
  updated_at: '2026-07-01T12:00:00Z',
} as unknown as BookingDetail

function renderBookAgain() {
  return renderHook(() => useBookAgain(), { wrapper: MemoryRouter })
}

beforeEach(() => {
  navigate.mockReset()
  sessionStorage.clear()
  useBookingWizardStore.getState().reset()
})

describe('useBookAgain', () => {
  it('prefills service, provider and address, and navigates into the wizard', () => {
    const { result } = renderBookAgain()

    act(() => result.current(completedBooking))

    const wizard = useBookingWizardStore.getState()

    expect(wizard.serviceId).toBe('service-9')
    expect(wizard.address.line1).toBe('55 Front St W')
    expect(wizard.address.city).toBe('Toronto')
    expect(wizard.address.province).toBe('ON')
    expect(wizard.address.postal_code).toBe('M5J 1E6')
    expect(wizard.addressId).toBe('address-2')

    // Navigating to the service's wizard is what pins the provider — a
    // service belongs to exactly one business.
    expect(navigate).toHaveBeenCalledWith('/book/service-9')
  })

  it('leaves the date empty rather than reusing the old appointment', () => {
    const { result } = renderBookAgain()

    act(() => result.current(completedBooking))

    const wizard = useBookingWizardStore.getState()

    expect(wizard.date).toBe('')
    expect(wizard.slot).toBeNull()
  })

  it('issues a fresh idempotency key so the new booking cannot replay the old response', () => {
    const before = useBookingWizardStore.getState().idempotencyKey
    const { result } = renderBookAgain()

    act(() => result.current(completedBooking))

    expect(useBookingWizardStore.getState().idempotencyKey).not.toBe(before)
  })

  it('works for a claimed guest booking that never had a saved address row', () => {
    const { result } = renderBookAgain()

    act(() =>
      result.current({ ...completedBooking, address: null } as unknown as BookingDetail),
    )

    const wizard = useBookingWizardStore.getState()

    // The denormalized snapshot is what makes this work — a guest booking
    // has no `addresses` row to fall back to (SRS §6.1).
    expect(wizard.address.line1).toBe('55 Front St W')
    expect(wizard.addressId).toBeNull()
  })
})
