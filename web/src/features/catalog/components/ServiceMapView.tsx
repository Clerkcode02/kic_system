import { MapContainer, TileLayer, Marker, Popup } from 'react-leaflet'
import L from 'leaflet'
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'
import { Link } from 'react-router-dom'
import {
  CANADA_BOUNDS,
  CANADA_DEFAULT_VIEW,
  CANADA_MAX_BOUNDS_VISCOSITY,
  CANADA_MAX_ZOOM,
  CANADA_MIN_ZOOM,
} from '@/lib/maps'
import type { ServiceListItem } from '../types'

const pinIcon = L.icon({
  iconUrl: markerIcon,
  iconRetinaUrl: markerIcon2x,
  shadowUrl: markerShadow,
  iconSize: [25, 41],
  iconAnchor: [12, 41],
})

interface ServiceMapViewProps {
  services: ServiceListItem[]
  center: { lat: number; lng: number } | null
}

/**
 * Canada-only market scope (CLAUDE.md §5): the map never opens on or pans
 * to anywhere outside CANADA_BOUNDS, and services without a known business
 * location are simply omitted rather than plotted at a guessed point.
 */
export function ServiceMapView({ services, center }: ServiceMapViewProps) {
  const plottable = services.filter(
    (
      service,
    ): service is ServiceListItem & { business: { location: { lat: number; lng: number } } } =>
      service.business.location !== null,
  )

  const mapCenter = center ?? {
    lat: CANADA_DEFAULT_VIEW.center[0],
    lng: CANADA_DEFAULT_VIEW.center[1],
  }

  return (
    <div className="h-[28rem] w-full overflow-hidden rounded-md border border-gray-300">
      <MapContainer
        key={`${mapCenter.lat}-${mapCenter.lng}`}
        center={[mapCenter.lat, mapCenter.lng]}
        zoom={center ? 11 : CANADA_DEFAULT_VIEW.zoom}
        minZoom={CANADA_MIN_ZOOM}
        maxZoom={CANADA_MAX_ZOOM}
        maxBounds={CANADA_BOUNDS}
        maxBoundsViscosity={CANADA_MAX_BOUNDS_VISCOSITY}
        style={{ height: '100%', width: '100%' }}
      >
        <TileLayer
          url={import.meta.env.VITE_MAP_TILE_URL ?? ''}
          attribution={import.meta.env.VITE_MAP_TILE_ATTRIBUTION ?? ''}
        />
        {plottable.map((service) => (
          <Marker
            key={service.id}
            position={[service.business.location.lat, service.business.location.lng]}
            icon={pinIcon}
          >
            <Popup>
              <div className="flex flex-col gap-1">
                <p className="font-semibold">{service.title}</p>
                <p className="text-xs text-gray-500">{service.business.legal_name}</p>
                <Link
                  to={`/services/${service.id}`}
                  className="text-sm text-blue-600 underline"
                >
                  View service
                </Link>
              </div>
            </Popup>
          </Marker>
        ))}
      </MapContainer>
    </div>
  )
}
