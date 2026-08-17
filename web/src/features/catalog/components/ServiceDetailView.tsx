import { useParams, Link } from 'react-router-dom'
import { Badge, Card, EmptyState, Skeleton } from '@/components'
import { useService } from '../hooks/useServices'

export function ServiceDetailView() {
  const { serviceId } = useParams<{ serviceId: string }>()
  const { data: service, isLoading, isError } = useService(serviceId)

  if (isLoading) {
    return (
      <div className="p-6">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="mt-4 h-40 w-full" />
      </div>
    )
  }

  if (isError || !service) {
    return (
      <div className="p-6">
        <EmptyState
          title="Service not found"
          description="It may have been removed or deactivated."
        />
      </div>
    )
  }

  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-4 p-4 sm:p-6">
      <Card className="flex flex-col gap-3">
        <div className="flex items-start justify-between gap-2">
          <div>
            <h1 className="text-xl font-semibold text-gray-900">{service.title}</h1>
            <p className="text-sm text-gray-500">
              {service.category.name} · {service.business.legal_name} · ★{' '}
              {service.business.rating_avg.toFixed(1)}
            </p>
          </div>
          <Badge tone={service.pricing_type === 'fixed' ? 'success' : 'info'}>
            {service.pricing_type === 'fixed' ? 'Fixed price' : 'Quote required'}
          </Badge>
        </div>

        <p className="text-sm text-gray-700">{service.description}</p>

        <p className="text-lg font-semibold text-gray-900">
          {service.pricing_type === 'fixed'
            ? `$${service.base_price} ${service.currency}`
            : `Starting at $${service.base_price} ${service.currency}`}
          <span className="ml-2 text-sm font-normal text-gray-500">
            ~{service.estimated_duration_minutes} min
          </span>
        </p>

        {service.pricing_tiers.length > 0 && (
          <div className="flex flex-col gap-2">
            <h2 className="text-sm font-semibold text-gray-900">Pricing tiers</h2>
            {service.pricing_tiers.map((tier) => (
              <div
                key={tier.id}
                className="flex justify-between rounded-md border border-gray-200 p-3 text-sm"
              >
                <div>
                  <p className="font-medium text-gray-900">{tier.tier_name}</p>
                  {tier.description && <p className="text-gray-500">{tier.description}</p>}
                </div>
                <span className="font-semibold text-gray-900">
                  ${tier.price} {tier.currency}
                </span>
              </div>
            ))}
          </div>
        )}

        <Link
          to={`/book/${service.id}`}
          className="mt-2 inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
        >
          Book this service
        </Link>
      </Card>
    </div>
  )
}
