import { describe, expect, it } from 'vitest'
import { renderHook, act } from '@testing-library/react'
import { useIdempotencyKey } from '../useIdempotencyKey'

describe('useIdempotencyKey', () => {
  it('keeps the same key across re-renders, so a retry replays it', () => {
    const { result, rerender } = renderHook(() => useIdempotencyKey())

    const firstKey = result.current.key
    rerender()

    expect(result.current.key).toBe(firstKey)
  })

  it('generates a new key only after renew() is called', () => {
    const { result } = renderHook(() => useIdempotencyKey())

    const firstKey = result.current.key

    act(() => {
      result.current.renew()
    })

    expect(result.current.key).not.toBe(firstKey)
  })
})
