export type ServicePricingType = 'hourly' | 'fixed' | 'package' | 'inspection'

export interface ServicePricingTier {
  id: string
  tier_name: string
  description: string | null
  price: string
  currency: string
  estimated_duration_minutes: number | null
  sort_order: number
}

export interface Service {
  id: string
  title: string
  description: string
  pricing_type: ServicePricingType
  base_price: string
  currency: string
  estimated_duration_minutes: number
  is_active: boolean
  category: { id: string; name: string; slug: string }
  business: { id: string; legal_name: string; rating_avg: string }
  pricing_tiers?: ServicePricingTier[]
}

export interface ServicePricingTierInput {
  tier_name?: string
  description?: string | null
  price?: number
  estimated_duration_minutes?: number | null
  sort_order?: number | null
}

export interface StoreServicePayload {
  category_id: string
  title: string
  description: string
  pricing_type: ServicePricingType
  base_price: number
  estimated_duration_minutes: number
  pricing_tiers?: ServicePricingTierInput[]
}

export interface UpdateServicePayload {
  category_id?: string
  title?: string
  description?: string
  pricing_type?: ServicePricingType
  base_price?: number
  estimated_duration_minutes?: number
  is_active?: boolean
  pricing_tiers?: ServicePricingTierInput[]
}

export interface CursorPage<T> {
  data: T[]
  links: { first: string | null; last: string | null; prev: string | null; next: string | null }
  meta: {
    path: string | null
    per_page: number
    next_cursor: string | null
    prev_cursor: string | null
  }
}
