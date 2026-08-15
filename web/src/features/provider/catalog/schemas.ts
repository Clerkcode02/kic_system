import { z } from 'zod'

/** Mirrors StoreServiceRequest/UpdateServiceRequest (api/app/Http/Requests/Provider/Catalog/*). */
export const servicePricingTierSchema = z.object({
  tier_name: z.string().min(1, 'Tier name is required').max(255),
  description: z.string().max(1000).optional(),
  price: z.coerce.number().min(0, 'Must be 0 or more'),
  estimated_duration_minutes: z.coerce.number().int().min(1).optional(),
})

export const serviceFormSchema = z.object({
  category_id: z.string().min(1, 'Category is required'),
  title: z.string().min(1, 'Title is required').max(255),
  description: z.string().min(1, 'Description is required'),
  pricing_type: z.enum(['hourly', 'fixed', 'package', 'inspection']),
  base_price: z.coerce.number().min(0, 'Must be 0 or more'),
  estimated_duration_minutes: z.coerce.number().int().min(1, 'Must be at least 1 minute'),
  pricing_tiers: z.array(servicePricingTierSchema).optional(),
})

export type ServiceFormValues = z.infer<typeof serviceFormSchema>
