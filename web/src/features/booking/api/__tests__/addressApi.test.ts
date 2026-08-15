import { describe, expect, it, vi } from 'vitest'

const apiClientMock = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
}))

vi.mock('@/lib/api', () => ({ apiClient: apiClientMock }))

const { createAddress, fetchMyAddresses } = await import('../addressApi')

describe('fetchMyAddresses', () => {
  it("requests the caller's saved addresses", async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: [{ id: 'addr1' }] } })

    const addresses = await fetchMyAddresses()

    expect(apiClientMock.get).toHaveBeenCalledWith('/me/addresses')
    expect(addresses).toEqual([{ id: 'addr1' }])
  })
})

describe('createAddress', () => {
  it('posts the address payload as-is, using the backend field name state_province', async () => {
    apiClientMock.post.mockResolvedValue({ data: { data: { id: 'addr1' } } })

    const payload = {
      street: '123 Main St',
      city: 'Toronto',
      state_province: 'ON',
      postal_code: 'M5V 2T6',
      lat: 43.65,
      lng: -79.38,
    }

    await createAddress(payload)

    expect(apiClientMock.post).toHaveBeenCalledWith('/me/addresses', payload)
  })
})
