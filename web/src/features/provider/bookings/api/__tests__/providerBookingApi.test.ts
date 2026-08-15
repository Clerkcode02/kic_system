import { describe, expect, it, vi } from 'vitest'

const apiClientMock = vi.hoisted(() => ({
  get: vi.fn(),
}))

vi.mock('@/lib/api', () => ({ apiClient: apiClientMock }))

const { fetchProviderBookings, fetchProviderBooking } = await import('../providerBookingApi')

describe('fetchProviderBookings', () => {
  it('requests the provider role and passes through status/cursor', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: [], meta: {} } })

    await fetchProviderBookings('scheduled', 'cursor-1')

    expect(apiClientMock.get).toHaveBeenCalledWith('/bookings', {
      params: { role: 'provider', status: 'scheduled', cursor: 'cursor-1' },
    })
  })
})

describe('fetchProviderBooking', () => {
  it('fetches a single booking by id', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: { id: 'b1' } } })

    const result = await fetchProviderBooking('b1')

    expect(apiClientMock.get).toHaveBeenCalledWith('/bookings/b1')
    expect(result).toEqual({ id: 'b1' })
  })
})
