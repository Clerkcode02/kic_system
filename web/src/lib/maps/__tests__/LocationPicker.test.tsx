import { describe, expect, it, vi } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { LocationPicker } from '../LocationPicker'

const mapContainerProps = vi.fn()
const geocodeAddressMock = vi.hoisted(() => vi.fn())

vi.mock('../geocoding', async (importOriginal) => {
  const actual = await importOriginal<typeof import('../geocoding')>()
  return { ...actual, geocodeAddress: geocodeAddressMock }
})

vi.mock('react-leaflet', () => ({
  MapContainer: (props: Record<string, unknown>) => {
    mapContainerProps(props)
    return <div data-testid="map-container">{props.children as React.ReactNode}</div>
  },
  TileLayer: () => null,
  Marker: () => null,
  useMap: () => ({ setView: vi.fn(), getZoom: () => 4 }),
  useMapEvents: () => null,
}))

vi.mock('leaflet', () => ({
  default: {
    icon: () => ({}),
  },
}))

describe('LocationPicker', () => {
  it('locks the map to the Canada bounds and viscosity', () => {
    render(
      <LocationPicker
        address=""
        onAddressChange={() => {}}
        coords={null}
        onCoordsChange={() => {}}
      />,
    )

    const props = mapContainerProps.mock.calls.at(-1)?.[0] as {
      maxBounds: unknown
      maxBoundsViscosity: number
      minZoom: number
    }
    expect(props.maxBounds).toEqual([
      [41.6, -141.1],
      [83.2, -52.5],
    ])
    expect(props.maxBoundsViscosity).toBe(1.0)
    expect(props.minZoom).toBeGreaterThan(0)
  })

  it('geocodes exactly once when "Locate on map" is clicked, not on keystroke', async () => {
    geocodeAddressMock.mockResolvedValue({ lat: 43.6, lng: -79.4, formattedAddress: 'Toronto, ON' })

    const onCoordsChange = vi.fn()
    const user = userEvent.setup()

    render(
      <LocationPicker
        address="123 Main St"
        onAddressChange={() => {}}
        coords={null}
        onCoordsChange={onCoordsChange}
      />,
    )

    const addressInput = screen.getByLabelText('Address')
    fireEvent.change(addressInput, { target: { value: '123 Main St, Toronto' } })
    fireEvent.change(addressInput, { target: { value: '123 Main St, Toronto, ON' } })

    expect(geocodeAddressMock).not.toHaveBeenCalled()

    await user.click(screen.getByRole('button', { name: 'Locate on map' }))

    await waitFor(() => expect(onCoordsChange).toHaveBeenCalledWith({ lat: 43.6, lng: -79.4 }))
    expect(geocodeAddressMock).toHaveBeenCalledTimes(1)
  })
})
