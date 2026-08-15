import { describe, expect, it, vi } from 'vitest'

const apiClientMock = vi.hoisted(() => ({
  get: vi.fn(),
}))

vi.mock('@/lib/api', () => ({ apiClient: apiClientMock }))

const { fetchFreelancerDashboard } = await import('../dashboardApi')

describe('fetchFreelancerDashboard', () => {
  it('requests the freelancer dashboard summary endpoint', async () => {
    apiClientMock.get.mockResolvedValue({
      data: {
        data: {
          open_proposal_count: 2,
          active_contract_count: 1,
          attention_milestones: [],
          earnings: { total: '0.00', currency: 'CAD' },
        },
      },
    })

    const summary = await fetchFreelancerDashboard()

    expect(apiClientMock.get).toHaveBeenCalledWith('/freelancer/me/dashboard')
    expect(summary.open_proposal_count).toBe(2)
  })
})
