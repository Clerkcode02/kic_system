import { describe, expect, it, vi } from 'vitest'

const apiClientMock = vi.hoisted(() => ({
  get: vi.fn(),
}))

vi.mock('@/lib/api', () => ({ apiClient: apiClientMock }))

const { fetchProviderDashboard } = await import('../dashboardApi')

describe('fetchProviderDashboard', () => {
  it('fetches the provider dashboard summary', async () => {
    const summary = {
      today_schedule: [],
      pending_quotations: [],
      upcoming_bookings: [],
      earnings: { total: '0.00', currency: 'CAD', recent_payouts: [] },
    }
    apiClientMock.get.mockResolvedValue({ data: { data: summary } })

    const result = await fetchProviderDashboard()

    expect(apiClientMock.get).toHaveBeenCalledWith('/provider/me/dashboard')
    expect(result).toEqual(summary)
  })
})
