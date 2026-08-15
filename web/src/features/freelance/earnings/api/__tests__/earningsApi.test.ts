import { describe, expect, it, vi } from 'vitest'

const apiClientMock = vi.hoisted(() => ({
  get: vi.fn(),
}))

vi.mock('@/lib/api', () => ({ apiClient: apiClientMock }))

const { fetchMyEarnings } = await import('../earningsApi')

describe('fetchMyEarnings', () => {
  it('requests the freelancer earnings endpoint with cursor', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: [], meta: {} } })

    await fetchMyEarnings('cursor1')

    expect(apiClientMock.get).toHaveBeenCalledWith('/freelancer/me/earnings', {
      params: { cursor: 'cursor1' },
    })
  })

  it('omits cursor when not provided', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: [], meta: {} } })

    await fetchMyEarnings()

    expect(apiClientMock.get).toHaveBeenCalledWith('/freelancer/me/earnings', {
      params: { cursor: undefined },
    })
  })
})
