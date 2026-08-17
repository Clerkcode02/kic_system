import { http, HttpResponse } from 'msw'
import { API_BASE_URL, API_VERSION_PATH } from '@/lib/api/config'

/**
 * Baseline "happy path" handlers for the endpoints exercised by the
 * component tests. Individual tests override these per-case with
 * `server.use(...)` for validation/error scenarios — see
 * src/test/mocks/server.ts.
 */
export const handlers = [
  // Sanctum SPA bootstrap — apiClient fires this before every stateful
  // (POST/PUT/PATCH/DELETE) request; it just needs to resolve.
  http.get(`${API_BASE_URL}/sanctum/csrf-cookie`, () => new HttpResponse(null, { status: 204 })),

  // Anonymous by default. A 401 here is the normal unauthenticated state,
  // not an error (SRS §6.1) — tests that need a session override this with
  // `server.use(authenticatedAs(user))`.
  http.get(`${API_BASE_URL}${API_VERSION_PATH}/auth/me`, () =>
    HttpResponse.json({ message: 'Unauthenticated.' }, { status: 401 }),
  ),
]

/** Test users, plus the handler override that signs one in. */
export const testCustomer = {
  id: 'user-1',
  name: 'Dana Okafor',
  email: 'dana@example.com',
  phone: '+14165550143',
  role: 'customer' as const,
  status: 'active' as const,
  email_verified_at: '2026-01-01T00:00:00Z',
  created_at: '2026-01-01T00:00:00Z',
}

export function authenticatedAs(user = testCustomer) {
  return http.get(`${API_BASE_URL}${API_VERSION_PATH}/auth/me`, () =>
    HttpResponse.json({ data: user }),
  )
}
