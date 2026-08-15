import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { geocodeAddress, GeocodingError } from '../geocoding'

function mockFetchOnce(body: unknown, ok = true) {
  return vi.fn().mockResolvedValue({
    ok,
    json: () => Promise.resolve(body),
  })
}

describe('geocodeAddress', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', mockFetchOnce({ features: [] }))
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('always sends the Canada country filter', async () => {
    const fetchMock = mockFetchOnce({
      features: [{ properties: { lat: 43.6, lon: -79.4, formatted: '123 Main St, Toronto, ON' } }],
    })
    vi.stubGlobal('fetch', fetchMock)

    await geocodeAddress('123 Main St, Toronto')

    const requestedUrl = new URL(fetchMock.mock.calls[0][0] as string)
    expect(requestedUrl.searchParams.get('filter')).toBe('countrycode:ca')
  })

  it('calls the geocoder exactly once per invocation', async () => {
    const fetchMock = mockFetchOnce({
      features: [{ properties: { lat: 43.6, lon: -79.4, formatted: '123 Main St, Toronto, ON' } }],
    })
    vi.stubGlobal('fetch', fetchMock)

    await geocodeAddress('123 Main St, Toronto')

    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('throws a GeocodingError when no address is provided', async () => {
    await expect(geocodeAddress('  ')).rejects.toBeInstanceOf(GeocodingError)
  })

  it('throws a GeocodingError when no match is found', async () => {
    await expect(geocodeAddress('nowhere')).rejects.toBeInstanceOf(GeocodingError)
  })
})
