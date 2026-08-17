import type { ReactElement, ReactNode } from 'react'
import { render, type RenderOptions } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter, Routes, Route } from 'react-router-dom'
import { AuthProvider } from '@/app/providers/AuthProvider'

/**
 * AuthProvider is part of the stack because components branch on actor kind
 * (SRS §6.1) — the booking wizard, for one, renders a different set of steps
 * for a guest than for a signed-in customer. Auth state comes from the
 * mocked `/auth/me`: the default handler 401s (the normal anonymous state),
 * and a test opts into a session by overriding that handler.
 */

/**
 * A fresh QueryClient per render — retries/caching would otherwise leak
 * between tests and turn a mocked 500 into a silent retry loop.
 */
export function createTestQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: { retry: false, gcTime: 0 },
      mutations: { retry: false },
    },
  })
}

interface RenderWithProvidersOptions extends Omit<RenderOptions, 'wrapper'> {
  queryClient?: QueryClient
  /** Initial router entries; defaults to a single root path. */
  route?: string
  /** Route pattern the element is mounted at, e.g. "/customer/book/:serviceId". */
  path?: string
}

export function renderWithProviders(
  ui: ReactElement,
  { queryClient = createTestQueryClient(), route = '/', path = '/', ...options }: RenderWithProvidersOptions = {},
) {
  function Wrapper({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <MemoryRouter initialEntries={[route]}>
            <Routes>
              <Route path={path} element={children} />
            </Routes>
          </MemoryRouter>
        </AuthProvider>
      </QueryClientProvider>
    )
  }

  return { queryClient, ...render(ui, { wrapper: Wrapper, ...options }) }
}
