import { http, HttpResponse } from 'msw'
import { API_BASE_URL } from '@/lib/api/config'

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
]
