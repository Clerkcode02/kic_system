import { describe, expect, it, vi } from 'vitest'
import { render } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { ServiceMapView } from '../ServiceMapView'
import type { ServiceListItem } from '../../types'

const mapContainerProps = vi.fn()
const markerPositions = vi.fn()

vi.mock('react-leaflet', () => ({
  MapContainer: (props: Record<string, unknown>) => {
    mapContainerProps(props)
    return <div data-testid="map-container">{props.children as React.ReactNode}</div>
  },
  TileLayer: () => null,
  Marker: (props: { position: unknown; children?: React.ReactNode }) => {
    markerPositions(props.position)
    return <div data-testid="marker">{props.children}</div>
  },
  Popup: (props: { children?: React.ReactNode }) => <div>{props.children}</div>,
}))

vi.mock('leaflet', () => ({
  default: { icon: () => ({}) },
}))

function service(overrides: Partial<ServiceListItem>): ServiceListItem {
  return {
    id: 'svc1',
    title: 'Kitchen faucet repair',
    pricing_type: 'fixed',
    base_price: '89.00',
    currency: 'CAD',
    estimated_duration_minutes: 60,
    category: { id: 'cat1', name: 'Plumbing', slug: 'plumbing' },
    business: { id: 'biz1', legal_name: 'Acme Plumbing', rating_avg: 4.5, location: null },
    ...overrides,
  }
}

describe('ServiceMapView', () => {
  it('locks the map to the Canada bounds', () => {
    render(
      <MemoryRouter>
        <ServiceMapView services={[]} center={null} />
      </MemoryRouter>,
    )

    const props = mapContainerProps.mock.calls.at(-1)?.[0] as { maxBounds: unknown }
    expect(props.maxBounds).toEqual([
      [41.6, -141.1],
      [83.2, -52.5],
    ])
  })

  it('plots only services whose business has a known location', () => {
    mapContainerProps.mockClear()
    markerPositions.mockClear()

    const services = [
      service({
        id: 'no-location',
        business: { id: 'b1', legal_name: 'No Location Co', rating_avg: 4, location: null },
      }),
      service({
        id: 'has-location',
        business: {
          id: 'b2',
          legal_name: 'Located Co',
          rating_avg: 4,
          location: { lat: 43.6, lng: -79.4 },
        },
      }),
    ]

    render(
      <MemoryRouter>
        <ServiceMapView services={services} center={null} />
      </MemoryRouter>,
    )

    expect(markerPositions).toHaveBeenCalledTimes(1)
    expect(markerPositions).toHaveBeenCalledWith([43.6, -79.4])
  })
})
