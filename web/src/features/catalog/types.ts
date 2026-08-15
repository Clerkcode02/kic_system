export type ServicePricingType = 'fixed' | 'quote'

export interface CategorySummary {
  id: string
  name: string
  slug: string
}

export interface Category extends CategorySummary {
  icon: string | null
  is_active: boolean
  sort_order: number
  children: Category[]
}

export interface BusinessSummary {
  id: string
  legal_name: string
  rating_avg: number
  location: { lat: number; lng: number } | null
}

export interface ServiceListItem {
  id: string
  title: string
  pricing_type: ServicePricingType
  base_price: string
  currency: string
  estimated_duration_minutes: number
  category: CategorySummary
  business: BusinessSummary
}

export interface ServicePricingTier {
  id: string
  tier_name: string
  description: string | null
  price: string
  currency: string
  estimated_duration_minutes: number
  sort_order: number
}

export interface ServiceDetail extends ServiceListItem {
  description: string
  is_active: boolean
  pricing_tiers: ServicePricingTier[]
}

export type SortOption = 'newest' | 'price_low' | 'price_high'

export interface ServiceListFilters {
  category?: string
  lat?: number
  lng?: number
  radius?: number
  sort?: SortOption
}

export interface CursorPage<T> {
  data: T[]
  meta: {
    next_cursor: string | null
    prev_cursor: string | null
  }
}
