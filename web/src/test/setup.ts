import { afterAll, afterEach, beforeAll, vi } from 'vitest'
import { cleanup } from '@testing-library/react'
import '@testing-library/jest-dom/vitest'
import { server } from './mocks/server'

/**
 * jsdom has no IntersectionObserver, and every infinite-scroll list in the
 * app builds one on mount (the cursor-pagination sentinel). Without this,
 * rendering any of those components throws before a single assertion runs.
 *
 * Deliberately inert: it never fires a callback, so tests see the first page
 * only. A test that needs to assert paging should drive `fetchNextPage`
 * through the hook rather than faking an intersection.
 */
vi.stubGlobal(
  'IntersectionObserver',
  class {
    observe = vi.fn()
    unobserve = vi.fn()
    disconnect = vi.fn()
    takeRecords = vi.fn(() => [])
    readonly root = null
    readonly rootMargin = ''
    readonly thresholds: readonly number[] = []
  },
)

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))

afterEach(() => {
  cleanup()
  server.resetHandlers()
})

afterAll(() => server.close())
