import { Link } from 'react-router-dom'
import { Badge, Card } from '@/components'
import type { ServiceListItem } from '../types'

export function ServiceCard({ service }: { service: ServiceListItem }) {
  return (
    <Link to={`/customer/services/${service.id}`}>
      <Card className="flex h-full flex-col gap-2 transition hover:shadow-md">
        <div className="flex items-start justify-between gap-2">
          <h3 className="font-semibold text-gray-900">{service.title}</h3>
          <Badge tone={service.pricing_type === 'fixed' ? 'success' : 'info'}>
            {service.pricing_type === 'fixed' ? 'Fixed price' : 'Quote required'}
          </Badge>
        </div>
        <p className="text-sm text-gray-500">{service.category.name}</p>
        <p className="text-sm text-gray-700">{service.business.legal_name}</p>
        <div className="mt-auto flex items-center justify-between pt-2 text-sm">
          <span className="text-gray-500">★ {service.business.rating_avg.toFixed(1)}</span>
          <span className="font-semibold text-gray-900">
            {service.pricing_type === 'fixed'
              ? `$${service.base_price} ${service.currency}`
              : `From $${service.base_price} ${service.currency}`}
          </span>
        </div>
      </Card>
    </Link>
  )
}
