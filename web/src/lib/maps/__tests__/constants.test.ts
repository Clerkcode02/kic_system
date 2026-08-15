import { describe, expect, it } from 'vitest'
import { CANADA_BOUNDS, CANADA_MAX_BOUNDS_VISCOSITY, CANADIAN_PROVINCES } from '../constants'

describe('CANADA_BOUNDS', () => {
  it('matches the approximate SW/NE bounding box from CLAUDE.md', () => {
    expect(CANADA_BOUNDS).toEqual([
      [41.6, -141.1],
      [83.2, -52.5],
    ])
  })

  it('locks the viewport to Canada with full bounds viscosity', () => {
    expect(CANADA_MAX_BOUNDS_VISCOSITY).toBe(1.0)
  })
})

describe('CANADIAN_PROVINCES', () => {
  it('lists exactly the 13 provinces and territories', () => {
    expect(CANADIAN_PROVINCES).toHaveLength(13)
  })
})
