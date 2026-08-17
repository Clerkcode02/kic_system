import { describe, expect, it } from 'vitest'
import { quotationBuilderSchema, quotationLineItemSchema } from '../schemas'

describe('quotationLineItemSchema', () => {
  it('accepts a valid line item and coerces numeric strings', () => {
    const result = quotationLineItemSchema.safeParse({
      description: 'Faucet part',
      quantity: '2',
      unit_price: '15.5',
    })

    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data).toEqual({ description: 'Faucet part', quantity: 2, unit_price: 15.5 })
    }
  })

  it('rejects a blank description', () => {
    const result = quotationLineItemSchema.safeParse({
      description: '',
      quantity: 1,
      unit_price: 10,
    })

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues[0].message).toBe('Description is required')
    }
  })

  it('rejects a zero or negative quantity', () => {
    const result = quotationLineItemSchema.safeParse({
      description: 'Part',
      quantity: 0,
      unit_price: 10,
    })

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues[0].message).toBe('Must be greater than 0')
    }
  })

  it('rejects a negative unit price', () => {
    const result = quotationLineItemSchema.safeParse({
      description: 'Part',
      quantity: 1,
      unit_price: -5,
    })

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues[0].message).toBe('Must be 0 or more')
    }
  })
})

describe('quotationBuilderSchema', () => {
  it('accepts a fully-valid form with line items', () => {
    const result = quotationBuilderSchema.safeParse({
      labor_cost: 100,
      materials_cost: 50,
      additional_fees: 10,
      discount_amount: 5,
      line_items: [{ description: 'Part', quantity: 1, unit_price: 20 }],
    })

    expect(result.success).toBe(true)
  })

  it('accepts an empty line_items array and an omitted discount', () => {
    const result = quotationBuilderSchema.safeParse({
      labor_cost: 0,
      materials_cost: 0,
      additional_fees: 0,
      line_items: [],
    })

    expect(result.success).toBe(true)
  })

  it('rejects a negative labor cost', () => {
    const result = quotationBuilderSchema.safeParse({
      labor_cost: -1,
      materials_cost: 0,
      additional_fees: 0,
    })

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues[0].path).toEqual(['labor_cost'])
      expect(result.error.issues[0].message).toBe('Must be 0 or more')
    }
  })

  it('never accepts a total/platform_fee/tax field — the server always recomputes those', () => {
    const result = quotationBuilderSchema.safeParse({
      labor_cost: 100,
      materials_cost: 50,
      additional_fees: 10,
      total_amount: 999,
      platform_fee: 999,
      tax_amount: 999,
    })

    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data).not.toHaveProperty('total_amount')
      expect(result.data).not.toHaveProperty('platform_fee')
      expect(result.data).not.toHaveProperty('tax_amount')
    }
  })
})
