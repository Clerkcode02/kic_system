import { useCallback, useState } from 'react'
import { CANADA_BOUNDS } from './constants'

export type GeolocationStatus = 'idle' | 'loading' | 'success' | 'error' | 'out-of-bounds'

export interface GeolocationResult {
  status: GeolocationStatus
  coords: { lat: number; lng: number } | null
  error: string | null
  requestLocation: () => void
}

function isWithinCanada(lat: number, lng: number): boolean {
  const [[south, west], [north, east]] = CANADA_BOUNDS as [[number, number], [number, number]]
  return lat >= south && lat <= north && lng >= west && lng <= east
}

/**
 * Wraps navigator.geolocation for the "near me" flow (CLAUDE.md §5.3 — no
 * geocoding call for this path). Devices outside Canada's bounds fall back
 * to the default Canada view instead of dropping a pin abroad.
 */
export function useBrowserGeolocation(): GeolocationResult {
  const [status, setStatus] = useState<GeolocationStatus>('idle')
  const [coords, setCoords] = useState<{ lat: number; lng: number } | null>(null)
  const [error, setError] = useState<string | null>(null)

  const requestLocation = useCallback(() => {
    if (!('geolocation' in navigator)) {
      setStatus('error')
      setError('Geolocation is not supported by this browser.')
      return
    }

    setStatus('loading')
    setError(null)

    navigator.geolocation.getCurrentPosition(
      (position) => {
        const { latitude: lat, longitude: lng } = position.coords
        if (!isWithinCanada(lat, lng)) {
          setStatus('out-of-bounds')
          setCoords(null)
          return
        }
        setCoords({ lat, lng })
        setStatus('success')
      },
      (geoError) => {
        setStatus('error')
        setError(geoError.message || 'Unable to determine your location.')
      },
      { enableHighAccuracy: true, timeout: 10_000 },
    )
  }, [])

  return { status, coords, error, requestLocation }
}
