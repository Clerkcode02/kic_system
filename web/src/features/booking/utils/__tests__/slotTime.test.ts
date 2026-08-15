import { describe, expect, it } from 'vitest'
import { formatTime, timeOf } from '../slotTime'

describe('timeOf', () => {
  it('extracts HH:MM:SS from an ISO8601 string with an offset', () => {
    expect(timeOf('2026-03-05T09:30:00+00:00')).toBe('09:30:00')
  })

  it('extracts HH:MM:SS regardless of the offset sign', () => {
    expect(timeOf('2026-03-05T14:00:00-05:00')).toBe('14:00:00')
  })

  it('never converts through Date/local timezone', () => {
    // A naive Date-based extraction (new Date(iso).toTimeString()) would
    // shift this depending on the test runner's local timezone; slicing
    // the string must not.
    expect(timeOf('2026-03-05T23:45:00+00:00')).toBe('23:45:00')
  })
})

describe('formatTime', () => {
  it('formats midnight as 12:00 AM', () => {
    expect(formatTime('2026-03-05T00:00:00+00:00')).toBe('12:00 AM')
  })

  it('formats noon as 12:00 PM', () => {
    expect(formatTime('2026-03-05T12:00:00+00:00')).toBe('12:00 PM')
  })

  it('formats a morning time', () => {
    expect(formatTime('2026-03-05T09:05:00+00:00')).toBe('9:05 AM')
  })

  it('formats an afternoon time', () => {
    expect(formatTime('2026-03-05T17:30:00+00:00')).toBe('5:30 PM')
  })
})
