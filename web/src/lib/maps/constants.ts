import type { LatLngBoundsExpression, LatLngTuple } from 'leaflet'

/** SW/NE corners of Canada's bounding box (CLAUDE.md §5 — Canada-only market scope). */
export const CANADA_BOUNDS: LatLngBoundsExpression = [
  [41.6, -141.1],
  [83.2, -52.5],
]

export const CANADA_DEFAULT_CENTER: LatLngTuple = [56.1304, -106.3468]

export const CANADA_DEFAULT_VIEW = {
  center: CANADA_DEFAULT_CENTER,
  zoom: 4,
} as const

/** Keeps the viewport from zooming out past the bounds and revealing the rest of the world. */
export const CANADA_MIN_ZOOM = 4
export const CANADA_MAX_ZOOM = 18
export const CANADA_MAX_BOUNDS_VISCOSITY = 1.0

export interface Province {
  code: string
  name: string
}

export const CANADIAN_PROVINCES: Province[] = [
  { code: 'AB', name: 'Alberta' },
  { code: 'BC', name: 'British Columbia' },
  { code: 'MB', name: 'Manitoba' },
  { code: 'NB', name: 'New Brunswick' },
  { code: 'NL', name: 'Newfoundland and Labrador' },
  { code: 'NS', name: 'Nova Scotia' },
  { code: 'NT', name: 'Northwest Territories' },
  { code: 'NU', name: 'Nunavut' },
  { code: 'ON', name: 'Ontario' },
  { code: 'PE', name: 'Prince Edward Island' },
  { code: 'QC', name: 'Quebec' },
  { code: 'SK', name: 'Saskatchewan' },
  { code: 'YT', name: 'Yukon' },
]

/** Matches backend `postal_code` regex in UpdateBusinessProfileRequest: A1A 1A1 or A1A1A1. */
export const CANADA_POSTAL_CODE_REGEX = /^[A-Za-z]\d[A-Za-z][ -]?\d[A-Za-z]\d$/

export function formatCanadianPostalCode(value: string): string {
  const cleaned = value
    .toUpperCase()
    .replace(/[^A-Z0-9]/g, '')
    .slice(0, 6)
  if (cleaned.length <= 3) return cleaned
  return `${cleaned.slice(0, 3)} ${cleaned.slice(3)}`
}
