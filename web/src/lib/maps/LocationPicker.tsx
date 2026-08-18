import { useEffect, useState } from 'react'
import { MapContainer, Marker, useMap, useMapEvents } from 'react-leaflet'
import L from 'leaflet'
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'
import { Button, Input } from '@/components'
import {
  CANADA_BOUNDS,
  CANADA_DEFAULT_VIEW,
  CANADA_MAX_BOUNDS_VISCOSITY,
  CANADA_MAX_ZOOM,
  CANADA_MIN_ZOOM,
} from './constants'
import { MapTileLayer } from './MapTileLayer'
import { geocodeAddress, GeocodingError } from './geocoding'
import { useBrowserGeolocation } from './useBrowserGeolocation'

const pinIcon = L.icon({
  iconUrl: markerIcon,
  iconRetinaUrl: markerIcon2x,
  shadowUrl: markerShadow,
  iconSize: [25, 41],
  iconAnchor: [12, 41],
})

export interface LatLng {
  lat: number
  lng: number
}

interface LocationPickerProps {
  address: string
  onAddressChange: (address: string) => void
  coords: LatLng | null
  onCoordsChange: (coords: LatLng) => void
  error?: string
  /** Set when the address text is composed elsewhere (e.g. from separate street/city/province fields). */
  addressReadOnly?: boolean
}

function RecenterOnChange({ coords }: { coords: LatLng | null }) {
  const map = useMap()
  useEffect(() => {
    if (coords) {
      map.setView([coords.lat, coords.lng], Math.max(map.getZoom(), 12))
    }
  }, [coords, map])
  return null
}

function MapClickHandler({ onCoordsChange }: { onCoordsChange: (coords: LatLng) => void }) {
  useMapEvents({
    click(event) {
      onCoordsChange({ lat: event.latlng.lat, lng: event.latlng.lng })
    },
  })
  return null
}

function DraggablePin({
  coords,
  onCoordsChange,
}: {
  coords: LatLng
  onCoordsChange: (coords: LatLng) => void
}) {
  return (
    <Marker
      position={[coords.lat, coords.lng]}
      icon={pinIcon}
      draggable
      eventHandlers={{
        dragend: (event) => {
          const marker = event.target as L.Marker
          const position = marker.getLatLng()
          onCoordsChange({ lat: position.lat, lng: position.lng })
        },
      }}
    />
  )
}

/**
 * The only place react-leaflet is imported (CLAUDE.md §9.9 — keeps Leaflet
 * DOM behind this wrapper so Phase 2 can swap it for a native map). Renders
 * a draggable pin over a paired text address field; geocoding runs once,
 * only when "Locate" is pressed, never on keystroke.
 */
export function LocationPicker({
  address,
  onAddressChange,
  coords,
  onCoordsChange,
  error,
  addressReadOnly = false,
}: LocationPickerProps) {
  const [isLocating, setIsLocating] = useState(false)
  const [locateError, setLocateError] = useState<string | null>(null)
  const geolocation = useBrowserGeolocation()

  useEffect(() => {
    if (geolocation.status === 'success' && geolocation.coords) {
      onCoordsChange(geolocation.coords)
    }
  }, [geolocation.status, geolocation.coords, onCoordsChange])

  const handleLocate = async () => {
    setLocateError(null)
    setIsLocating(true)
    try {
      const result = await geocodeAddress(address)
      onCoordsChange({ lat: result.lat, lng: result.lng })
    } catch (geocodeError) {
      setLocateError(
        geocodeError instanceof GeocodingError
          ? geocodeError.message
          : 'Could not locate that address.',
      )
    } finally {
      setIsLocating(false)
    }
  }

  const center = coords ?? {
    lat: CANADA_DEFAULT_VIEW.center[0],
    lng: CANADA_DEFAULT_VIEW.center[1],
  }

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
        <div className="flex-1">
          <Input
            label="Address"
            name="address"
            value={address}
            readOnly={addressReadOnly}
            error={error ?? locateError ?? undefined}
            onChange={(event) => onAddressChange(event.target.value)}
          />
        </div>
        <Button type="button" variant="secondary" isLoading={isLocating} onClick={handleLocate}>
          Locate on map
        </Button>
      </div>

      <Button
        type="button"
        variant="ghost"
        size="sm"
        isLoading={geolocation.status === 'loading'}
        onClick={geolocation.requestLocation}
        className="self-start"
      >
        Use my current location
      </Button>
      {geolocation.status === 'out-of-bounds' && (
        <p className="text-sm text-amber-600">
          Your device location is outside Canada. Showing the default map view instead.
        </p>
      )}
      {geolocation.status === 'error' && geolocation.error && (
        <p className="text-sm text-red-600">{geolocation.error}</p>
      )}

      <div className="relative h-64 w-full overflow-hidden rounded-md border border-gray-300 sm:h-80">
        <MapContainer
          center={[center.lat, center.lng]}
          zoom={coords ? 12 : CANADA_DEFAULT_VIEW.zoom}
          minZoom={CANADA_MIN_ZOOM}
          maxZoom={CANADA_MAX_ZOOM}
          maxBounds={CANADA_BOUNDS}
          maxBoundsViscosity={CANADA_MAX_BOUNDS_VISCOSITY}
          style={{ height: '100%', width: '100%' }}
        >
          <MapTileLayer />
          {coords && <DraggablePin coords={coords} onCoordsChange={onCoordsChange} />}
          <MapClickHandler onCoordsChange={onCoordsChange} />
          <RecenterOnChange coords={coords} />
        </MapContainer>
      </div>
      <p className="text-xs text-gray-500">
        Drag the pin or click the map to set the exact location. Only the pin&apos;s coordinates are
        saved.
      </p>
    </div>
  )
}
