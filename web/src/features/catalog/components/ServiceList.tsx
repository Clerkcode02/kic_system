import { useEffect, useRef } from 'react'
import { EmptyState, Skeleton } from '@/components'
import { useInfiniteServices } from '../hooks/useServices'
import type { ServiceListFilters } from '../types'
import { ServiceCard } from './ServiceCard'

interface ServiceListProps {
  filters: ServiceListFilters
}

/** Cursor-paginated infinite scroll — appends pages as the sentinel enters the viewport (CLAUDE.md §7: cursor pagination, never offset). */
export function ServiceList({ filters }: ServiceListProps) {
  const { data, isLoading, isError, fetchNextPage, hasNextPage, isFetchingNextPage } =
    useInfiniteServices(filters)
  const sentinelRef = useRef<HTMLDivElement | null>(null)

  useEffect(() => {
    const sentinel = sentinelRef.current
    if (!sentinel) return

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && hasNextPage && !isFetchingNextPage) {
          fetchNextPage()
        }
      },
      { rootMargin: '200px' },
    )
    observer.observe(sentinel)
    return () => observer.disconnect()
  }, [fetchNextPage, hasNextPage, isFetchingNextPage])

  if (isLoading) {
    return (
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {Array.from({ length: 6 }).map((_, index) => (
          <Skeleton key={index} className="h-40 rounded-lg" />
        ))}
      </div>
    )
  }

  if (isError) {
    return <EmptyState title="Couldn't load services" description="Please try again." />
  }

  const services = data?.pages.flatMap((page) => page.data) ?? []

  if (services.length === 0) {
    return (
      <EmptyState
        title="No services found"
        description="Try a different category, radius, or search location."
      />
    )
  }

  return (
    <div>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {services.map((service) => (
          <ServiceCard key={service.id} service={service} />
        ))}
      </div>
      <div ref={sentinelRef} className="h-4" />
      {isFetchingNextPage && (
        <p className="py-4 text-center text-sm text-gray-500">Loading more…</p>
      )}
    </div>
  )
}
