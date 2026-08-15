import { useEffect, useRef } from 'react'
import { Link } from 'react-router-dom'
import toast from 'react-hot-toast'
import { Badge, Button, Card, EmptyState, Skeleton } from '@/components'
import { ApiError } from '@/lib/api'
import { useInfiniteServices } from '../hooks/useServices'
import { useDeactivateService } from '../hooks/useServiceMutations'

export function ServiceListPage() {
  const { data, isLoading, isError, fetchNextPage, hasNextPage, isFetchingNextPage } =
    useInfiniteServices()
  const { mutateAsync: deactivate, isPending: isDeactivating } = useDeactivateService()
  const sentinelRef = useRef<HTMLDivElement | null>(null)

  useEffect(() => {
    const sentinel = sentinelRef.current
    if (!sentinel) return
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && hasNextPage && !isFetchingNextPage) fetchNextPage()
      },
      { rootMargin: '200px' },
    )
    observer.observe(sentinel)
    return () => observer.disconnect()
  }, [fetchNextPage, hasNextPage, isFetchingNextPage])

  const services = data?.pages.flatMap((page) => page.data) ?? []

  const handleDeactivate = async (serviceId: string) => {
    try {
      await deactivate(serviceId)
      toast.success('Service deactivated.')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not deactivate this service.')
    }
  }

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <div className="flex items-center justify-between">
        <h1 className="text-lg font-semibold text-gray-900">Your services</h1>
        <Link to="/provider/services/new">
          <Button type="button">Add service</Button>
        </Link>
      </div>

      {isLoading && (
        <div className="flex flex-col gap-3">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-20 rounded-lg" />
          ))}
        </div>
      )}

      {isError && <EmptyState title="Couldn't load services" description="Please try again." />}

      {!isLoading && !isError && services.length === 0 && (
        <EmptyState
          title="No services yet"
          description="Add your first service so customers can book you."
          action={
            <Link to="/provider/services/new" className="text-sm font-medium text-blue-600 underline">
              Add a service
            </Link>
          }
        />
      )}

      <div className="flex flex-col gap-3">
        {services.map((service) => (
          <Card key={service.id} className="flex items-center justify-between gap-4">
            <div>
              <div className="flex items-center gap-2">
                <p className="font-medium text-gray-900">{service.title}</p>
                {!service.is_active && <Badge tone="neutral">Inactive</Badge>}
              </div>
              <p className="text-sm text-gray-500">
                {service.category.name} · ${service.base_price} {service.currency} ·{' '}
                {service.pricing_type}
              </p>
            </div>
            <div className="flex gap-2">
              <Link to={`/provider/services/${service.id}/edit`}>
                <Button type="button" variant="secondary" size="sm">
                  Edit
                </Button>
              </Link>
              {service.is_active && (
                <Button
                  type="button"
                  variant="danger"
                  size="sm"
                  isLoading={isDeactivating}
                  onClick={() => handleDeactivate(service.id)}
                >
                  Deactivate
                </Button>
              )}
            </div>
          </Card>
        ))}
      </div>
      <div ref={sentinelRef} className="h-4" />
      {isFetchingNextPage && (
        <p className="py-2 text-center text-sm text-gray-500">Loading more…</p>
      )}
    </div>
  )
}
