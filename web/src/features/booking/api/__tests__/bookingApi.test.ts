import { describe, expect, it, vi } from 'vitest'

const apiClientMock = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  patch: vi.fn(),
}))

vi.mock('@/lib/api', () => ({ apiClient: apiClientMock }))

const { cancelBooking, createBooking, fetchBooking, fetchBookings } = await import('../bookingApi')

describe('fetchBookings', () => {
  it('always scopes the request to the customer role', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: [], meta: {} } })

    await fetchBookings(undefined, undefined)

    expect(apiClientMock.get).toHaveBeenCalledWith('/bookings', {
      params: { role: 'customer', status: undefined, cursor: undefined },
    })
  })

  it('forwards status filter and cursor when provided', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: [], meta: {} } })

    await fetchBookings('scheduled', 'abc123')

    expect(apiClientMock.get).toHaveBeenCalledWith('/bookings', {
      params: { role: 'customer', status: 'scheduled', cursor: 'abc123' },
    })
  })
})

describe('fetchBooking', () => {
  it('requests the booking by id', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: { id: 'b1' } } })

    const booking = await fetchBooking('b1')

    expect(apiClientMock.get).toHaveBeenCalledWith('/bookings/b1')
    expect(booking).toEqual({ id: 'b1' })
  })
})

describe('createBooking', () => {
  it('sends the Idempotency-Key header on the create request', async () => {
    apiClientMock.post.mockResolvedValue({ data: { data: { id: 'b1' } } })

    const payload = {
      service_id: 's1',
      address_id: 'a1',
      scheduled_date: '2026-03-05',
      time_slot_start: '09:00:00',
      time_slot_end: '09:30:00',
    }

    await createBooking(payload, 'key-123')

    expect(apiClientMock.post).toHaveBeenCalledWith('/bookings', payload, {
      headers: { 'Idempotency-Key': 'key-123' },
    })
  })
})

describe('cancelBooking', () => {
  it('sends the reason in the request body', async () => {
    apiClientMock.patch.mockResolvedValue({ data: { data: { id: 'b1' } } })

    await cancelBooking('b1', 'change of plans')

    expect(apiClientMock.patch).toHaveBeenCalledWith('/bookings/b1/cancel', {
      reason: 'change of plans',
    })
  })
})
