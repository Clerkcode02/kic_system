import { z } from 'zod'

/**
 * Mirrors StoreQuotationRequest/ReviseQuotationRequest field-for-field —
 * both share the same shape (api/app/Http/Requests/Quotation/*). No
 * total/platform_fee/tax field exists here on purpose: the server always
 * recomputes those (CLAUDE.md §2/§5).
 */
export const quotationLineItemSchema = z.object({
  description: z.string().min(1, 'Description is required').max(255),
  quantity: z.coerce.number().positive('Must be greater than 0'),
  unit_price: z.coerce.number().min(0, 'Must be 0 or more'),
})

export const quotationBuilderSchema = z.object({
  labor_cost: z.coerce.number().min(0, 'Must be 0 or more'),
  materials_cost: z.coerce.number().min(0, 'Must be 0 or more'),
  additional_fees: z.coerce.number().min(0, 'Must be 0 or more'),
  discount_amount: z.coerce.number().min(0, 'Must be 0 or more').optional(),
  line_items: z.array(quotationLineItemSchema).optional(),
})

export type QuotationBuilderFormValues = z.infer<typeof quotationBuilderSchema>
