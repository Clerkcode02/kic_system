import { describe, expect, it, vi } from 'vitest'

const apiClientMock = vi.hoisted(() => ({
  get: vi.fn(),
}))

vi.mock('@/lib/api', () => ({ apiClient: apiClientMock }))

const { fetchCategories, fetchService, fetchServices } = await import('../catalogApi')

describe('fetchCategories', () => {
  it('requests the category tree', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: [] } })

    await fetchCategories()

    expect(apiClientMock.get).toHaveBeenCalledWith('/categories')
  })
})

describe('fetchServices', () => {
  it('forwards filters and cursor as query params', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: [], meta: {} } })

    await fetchServices(
      { category: 'plumbing', lat: 43.6, lng: -79.4, radius: 25_000, sort: 'newest' },
      'cursor1',
    )

    expect(apiClientMock.get).toHaveBeenCalledWith('/services', {
      params: {
        category: 'plumbing',
        lat: 43.6,
        lng: -79.4,
        radius: 25_000,
        sort: 'newest',
        cursor: 'cursor1',
      },
    })
  })

  it('omits lat/lng/radius when no location filter is set', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: [], meta: {} } })

    await fetchServices({ sort: 'price_low' }, undefined)

    expect(apiClientMock.get).toHaveBeenCalledWith('/services', {
      params: { sort: 'price_low', cursor: undefined },
    })
  })
})

describe('fetchService', () => {
  it('requests a single service by id', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: { id: 's1' } } })

    const service = await fetchService('s1')

    expect(apiClientMock.get).toHaveBeenCalledWith('/services/s1')
    expect(service).toEqual({ id: 's1' })
  })
})
