import { useEffect, useState } from 'react'
import toast from 'react-hot-toast'
import { Button, Select } from '@/components'
import { useBrowserGeolocation } from '@/lib/maps'
import { useInfiniteServices } from '../hooks/useServices'
import type { ServiceListFilters, SortOption } from '../types'
import { CategoryFilterList } from './CategoryFilterList'
import { ServiceList } from './ServiceList'
import { ServiceMapView } from './ServiceMapView'

const SORT_OPTIONS: { value: SortOption; label: string }[] = [
  { value: 'newest', label: 'Newest' },
  { value: 'price_low', label: 'Price: low to high' },
  { value: 'price_high', label: 'Price: high to low' },
]

const NEAR_ME_RADIUS_METERS = 25_000

type ViewMode = 'list' | 'map'

export function CatalogBrowsePage() {
  const [category, setCategory] = useState<string | null>(null)
  const [sort, setSort] = useState<SortOption>('newest')
  const [nearMe, setNearMe] = useState<{ lat: number; lng: number } | null>(null)
  const [viewMode, setViewMode] = useState<ViewMode>('list')
  const geolocation = useBrowserGeolocation()

  const filters: ServiceListFilters = {
    ...(category ? { category } : {}),
    sort,
    ...(nearMe ? { lat: nearMe.lat, lng: nearMe.lng, radius: NEAR_ME_RADIUS_METERS } : {}),
  }

  // Map view shares the same query key as the list, so switching tabs is instant.
  const { data } = useInfiniteServices(filters)
  const services = data?.pages.flatMap((page) => page.data) ?? []

  const handleNearMe = () => {
    geolocation.requestLocation()
  }

  useEffect(() => {
    if (geolocation.status === 'success' && geolocation.coords) {
      setNearMe(geolocation.coords)
    } else if (geolocation.status === 'out-of-bounds') {
      setNearMe(null)
    } else if (geolocation.status === 'error' && geolocation.error) {
      toast.error(geolocation.error)
    }
  }, [geolocation.status, geolocation.coords, geolocation.error])

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-lg font-semibold text-gray-900">Browse services</h1>
        <div className="flex flex-wrap items-center gap-2">
          <Button
            type="button"
            variant={nearMe ? 'primary' : 'secondary'}
            size="sm"
            isLoading={geolocation.status === 'loading'}
            onClick={handleNearMe}
          >
            Near me
          </Button>
          {nearMe && (
            <Button type="button" variant="ghost" size="sm" onClick={() => setNearMe(null)}>
              Clear location
            </Button>
          )}
          <Select
            aria-label="Sort"
            options={SORT_OPTIONS}
            value={sort}
            onChange={(event) => setSort(event.target.value as SortOption)}
          />
          <div className="flex overflow-hidden rounded-md border border-gray-300">
            <button
              type="button"
              onClick={() => setViewMode('list')}
              className={`px-3 py-1.5 text-sm font-medium ${viewMode === 'list' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700'}`}
            >
              List
            </button>
            <button
              type="button"
              onClick={() => setViewMode('map')}
              className={`px-3 py-1.5 text-sm font-medium ${viewMode === 'map' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700'}`}
            >
              Map
            </button>
          </div>
        </div>
      </div>

      <CategoryFilterList selectedSlug={category} onSelect={setCategory} />

      {geolocation.status === 'out-of-bounds' && (
        <p className="text-sm text-amber-600">
          Your device location is outside Canada. Showing all Canadian results instead.
        </p>
      )}

      {viewMode === 'list' ? (
        <ServiceList filters={filters} />
      ) : (
        <ServiceMapView services={services} center={nearMe} />
      )}
    </div>
  )
}
