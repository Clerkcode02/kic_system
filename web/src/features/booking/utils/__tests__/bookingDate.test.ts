import { describe, expect, it } from 'vitest'
import { formatBookingDate, formatBookingDateShort, todayIso } from '../bookingDate'

describe('todayIso', () => {
  it('returns a zero-padded local YYYY-MM-DD', () => {
    expect(todayIso()).toMatch(/^\d{4}-\d{2}-\d{2}$/)
  })
})

describe('formatBookingDate', () => {
  it('formats a calendar date with its weekday', () => {
    expect(formatBookingDate('2026-03-05')).toBe('Thursday, March 5, 2026')
  })

  it('does not shift the day through UTC parsing', () => {
    // new Date('2026-01-01') is UTC midnight, which is Dec 31 anywhere west
    // of Greenwich — the day the user picked must survive regardless.
    expect(formatBookingDate('2026-01-01')).toBe('Thursday, January 1, 2026')
  })

  it('returns the input unchanged when it is not a calendar date', () => {
    expect(formatBookingDate('')).toBe('')
  })
})

describe('formatBookingDateShort', () => {
  it('formats a calendar date without the weekday', () => {
    expect(formatBookingDateShort('2026-03-05')).toBe('Mar 5, 2026')
  })

  it('does not shift the day through UTC parsing', () => {
    expect(formatBookingDateShort('2026-01-01')).toBe('Jan 1, 2026')
  })

  it('returns the input unchanged when it is not a calendar date', () => {
    expect(formatBookingDateShort('')).toBe('')
  })
})
