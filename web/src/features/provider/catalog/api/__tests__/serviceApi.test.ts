import { describe, expect, it, vi } from 'vitest'

const apiClientMock = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
}))

vi.mock('@/lib/api', () => ({ apiClient: apiClientMock }))

const { fetchMyServices, createService, updateService, deactivateService } = await import(
  '../serviceApi'
)

describe('fetchMyServices', () => {
  it('passes the cursor through', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: [], meta: {} } })

    await fetchMyServices('cursor-1')

    expect(apiClientMock.get).toHaveBeenCalledWith('/provider/me/services', {
      params: { cursor: 'cursor-1' },
    })
  })
})

describe('createService', () => {
  it('posts the store payload including pricing tiers', async () => {
    apiClientMock.post.mockResolvedValue({ data: { data: { id: 's1' } } })

    const payload = {
      category_id: 'cat-1',
      title: 'Drain cleaning',
      description: 'Unclog it',
      pricing_type: 'fixed' as const,
      base_price: 100,
      estimated_duration_minutes: 60,
      pricing_tiers: [{ tier_name: 'Standard', price: 100 }],
    }

    await createService(payload)

    expect(apiClientMock.post).toHaveBeenCalledWith('/provider/services', payload)
  })
})

describe('updateService', () => {
  it('patches the given service', async () => {
    apiClientMock.patch.mockResolvedValue({ data: { data: { id: 's1' } } })

    await updateService('s1', { title: 'New title' })

    expect(apiClientMock.patch).toHaveBeenCalledWith('/provider/services/s1', {
      title: 'New title',
    })
  })
})

describe('deactivateService', () => {
  it('patches is_active to false', async () => {
    apiClientMock.patch.mockResolvedValue({ data: { data: { id: 's1', is_active: false } } })

    await deactivateService('s1')

    expect(apiClientMock.patch).toHaveBeenCalledWith('/provider/services/s1', {
      is_active: false,
    })
  })
})
