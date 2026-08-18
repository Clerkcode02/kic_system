import { afterEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MapTileLayer } from '../MapTileLayer'
import { resolveTileUrl } from '../tileSource'

vi.mock('react-leaflet', () => ({
  TileLayer: (props: { url: string; attribution: string }) => (
    <div data-testid="tile-layer" data-url={props.url} data-attribution={props.attribution} />
  ),
}))

afterEach(() => {
  vi.unstubAllEnvs()
})

describe('resolveTileUrl', () => {
  it('returns null when no tile URL is configured', () => {
    vi.stubEnv('VITE_MAP_TILE_URL', '')
    expect(resolveTileUrl()).toBeNull()
  })

  it('returns null when the MapTiler placeholder has no key to fill it', () => {
    vi.stubEnv(
      'VITE_MAP_TILE_URL',
      'https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key=__MAPTILER_KEY__',
    )
    vi.stubEnv('VITE_MAPTILER_API_KEY', '')
    expect(resolveTileUrl()).toBeNull()
  })

  it('substitutes the MapTiler key into the placeholder', () => {
    vi.stubEnv(
      'VITE_MAP_TILE_URL',
      'https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key=__MAPTILER_KEY__',
    )
    vi.stubEnv('VITE_MAPTILER_API_KEY', 'abc123')
    expect(resolveTileUrl()).toBe(
      'https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key=abc123',
    )
  })

  it('passes through a URL that carries its own key', () => {
    vi.stubEnv('VITE_MAP_TILE_URL', 'https://tiles.stadiamaps.com/tiles/osm_bright/{z}/{x}/{y}.png')
    expect(resolveTileUrl()).toBe('https://tiles.stadiamaps.com/tiles/osm_bright/{z}/{x}/{y}.png')
  })
})

describe('MapTileLayer', () => {
  it('renders a visible notice instead of a silent grey box when unconfigured', () => {
    vi.stubEnv('VITE_MAP_TILE_URL', '')
    render(<MapTileLayer />)

    expect(screen.queryByTestId('tile-layer')).toBeNull()
    expect(screen.getByRole('status')).toHaveTextContent('Map tiles are not configured')
  })

  it('renders the tile layer with the resolved URL and attribution', () => {
    vi.stubEnv('VITE_MAP_TILE_URL', 'https://tiles.example.test/{z}/{x}/{y}.png')
    vi.stubEnv('VITE_MAP_TILE_ATTRIBUTION', '© OpenStreetMap contributors')
    render(<MapTileLayer />)

    const layer = screen.getByTestId('tile-layer')
    expect(layer).toHaveAttribute('data-url', 'https://tiles.example.test/{z}/{x}/{y}.png')
    expect(layer).toHaveAttribute('data-attribution', '© OpenStreetMap contributors')
  })
})
