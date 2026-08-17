import { describe, expect, it } from 'vitest'
import { formatMoney } from '../money'

describe('formatMoney', () => {
  it('formats a decimal string as CAD', () => {
    expect(formatMoney('1234.50')).toBe('$1,234.50')
  })

  it('formats a number', () => {
    expect(formatMoney(9.9)).toBe('$9.90')
  })

  it('falls back to zero for a non-numeric value', () => {
    expect(formatMoney('not-a-number')).toBe('$0.00')
  })
})
