import { describe, expect, it, vi } from 'vitest'

const apiClientMock = vi.hoisted(() => ({
  get: vi.fn(),
  put: vi.fn(),
}))

vi.mock('@/lib/api', () => ({ apiClient: apiClientMock }))

const { fetchAvailability, replaceAvailability } = await import('../availabilityApi')

describe('fetchAvailability', () => {
  it('defaults weekly/overrides to empty arrays when absent', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: {} } })

    const result = await fetchAvailability()

    expect(apiClientMock.get).toHaveBeenCalledWith('/provider/me/availability')
    expect(result).toEqual({ weekly: [], overrides: [] })
  })
})

describe('replaceAvailability', () => {
  it('sends the weekly and override payload via PUT', async () => {
    apiClientMock.put.mockResolvedValue({ data: { data: { weekly: [], overrides: [] } } })

    const payload = {
      weekly: [{ day_of_week: 1, start_time: '09:00', end_time: '17:00', is_active: true }],
      overrides: [{ date: '2026-12-25', is_blackout: true }],
    }

    await replaceAvailability(payload)

    expect(apiClientMock.put).toHaveBeenCalledWith('/provider/me/availability', payload)
  })
})
