import { describe, expect, it } from 'vitest'
import { toLocalDateString } from '../dateUtils'

describe('toLocalDateString', () => {
  it('formats a local Date as YYYY-MM-DD', () => {
    expect(toLocalDateString(new Date(2026, 0, 5))).toBe('2026-01-05')
  })

  it('pads single-digit months and days', () => {
    expect(toLocalDateString(new Date(2026, 8, 3))).toBe('2026-09-03')
  })

  it('does not shift the day based on the time-of-day component', () => {
    // Constructed from local components — midnight local, not a UTC instant
    // that could roll over to the previous/next day under toISOString().
    const midnightLocal = new Date(2026, 11, 31, 0, 0, 0)
    const lateNightLocal = new Date(2026, 11, 31, 23, 59, 59)
    expect(toLocalDateString(midnightLocal)).toBe('2026-12-31')
    expect(toLocalDateString(lateNightLocal)).toBe('2026-12-31')
  })
})
