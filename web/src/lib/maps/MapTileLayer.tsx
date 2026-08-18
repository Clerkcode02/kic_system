import { TileLayer } from 'react-leaflet'
import { resolveTileAttribution, resolveTileUrl } from './tileSource'

/**
 * The single place tile configuration is turned into a rendered layer. When
 * tiles are unconfigured it shows a visible notice instead of an unexplained
 * grey rectangle, so a missing `web/.env` is diagnosable without opening the
 * network tab.
 */
export function MapTileLayer() {
  const url = resolveTileUrl()

  if (url === null) {
    if (import.meta.env.DEV) {
      console.warn(
        '[maps] No tile source configured. Set VITE_MAP_TILE_URL (and VITE_MAPTILER_API_KEY ' +
          'if the URL contains __MAPTILER_KEY__) in web/.env — see web/.env.example.',
      )
    }

    return (
      <div
        role="status"
        className="pointer-events-none absolute inset-0 z-[500] flex items-center justify-center bg-gray-100 p-4 text-center text-sm text-gray-600"
      >
        Map tiles are not configured. Set <code className="mx-1">VITE_MAP_TILE_URL</code> in{' '}
        <code className="mx-1">web/.env</code>.
      </div>
    )
  }

  return <TileLayer url={url} attribution={resolveTileAttribution()} />
}
