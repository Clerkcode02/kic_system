const GEOAPIFY_API_KEY = import.meta.env.VITE_GEOAPIFY_API_KEY ?? ''
const GEOAPIFY_BASE_URL =
  import.meta.env.VITE_GEOAPIFY_BASE_URL ?? 'https://api.geoapify.com/v1/geocode/search'

export interface GeocodeResult {
  lat: number
  lng: number
  formattedAddress: string
}

export class GeocodingError extends Error {}

/**
 * One-shot address -> coordinates lookup, always scoped to Canada
 * (CLAUDE.md §5). Callers must only invoke this on explicit form submit,
 * never on keystroke — there is deliberately no debounce/cache built in
 * here that would make it safe to call more often.
 */
export async function geocodeAddress(address: string): Promise<GeocodeResult> {
  const trimmed = address.trim()
  if (trimmed === '') {
    throw new GeocodingError('Enter an address to locate.')
  }

  const url = new URL(GEOAPIFY_BASE_URL)
  url.searchParams.set('text', trimmed)
  url.searchParams.set('filter', 'countrycode:ca')
  url.searchParams.set('limit', '1')
  url.searchParams.set('apiKey', GEOAPIFY_API_KEY)

  const response = await fetch(url.toString())
  if (!response.ok) {
    throw new GeocodingError('Could not reach the geocoding service. Please try again.')
  }

  const body = (await response.json()) as {
    features?: { properties: { lat: number; lon: number; formatted: string } }[]
  }

  const feature = body.features?.[0]
  if (!feature) {
    throw new GeocodingError('No matching address found in Canada.')
  }

  return {
    lat: feature.properties.lat,
    lng: feature.properties.lon,
    formattedAddress: feature.properties.formatted,
  }
}
