import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { ServiceCard } from '../ServiceCard'
import type { ServiceListItem } from '../../types'

const baseService: ServiceListItem = {
  id: 'svc1',
  title: 'Kitchen faucet repair',
  pricing_type: 'fixed',
  base_price: '89.00',
  currency: 'CAD',
  estimated_duration_minutes: 60,
  category: { id: 'cat1', name: 'Plumbing', slug: 'plumbing' },
  business: { id: 'biz1', legal_name: 'Acme Plumbing', rating_avg: 4.5, location: null },
}

function renderCard(service: ServiceListItem) {
  return render(
    <MemoryRouter>
      <ServiceCard service={service} />
    </MemoryRouter>,
  )
}

describe('ServiceCard', () => {
  it('shows the fixed price and a "Fixed price" badge for a fixed-price service', () => {
    renderCard(baseService)

    expect(screen.getByText('Kitchen faucet repair')).toBeInTheDocument()
    expect(screen.getByText('Fixed price')).toBeInTheDocument()
    expect(screen.getByText('$89.00 CAD')).toBeInTheDocument()
  })

  it('shows a "From" price and "Quote required" badge for a quote-based service', () => {
    renderCard({ ...baseService, pricing_type: 'quote', base_price: '50.00' })

    expect(screen.getByText('Quote required')).toBeInTheDocument()
    expect(screen.getByText('From $50.00 CAD')).toBeInTheDocument()
  })

  it('links to the service detail route', () => {
    renderCard(baseService)

    expect(screen.getByRole('link')).toHaveAttribute('href', '/services/svc1')
  })
})
