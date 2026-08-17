import axios from 'axios'
import { API_BASE_URL, API_VERSION_PATH } from './config'
import { credentialStrategy, isGuestPath } from './authStrategy'
import { ensureCsrfCookie } from './csrf'
import { normalizeApiError } from './errors'
import { triggerGuestAccessLost, triggerUnauthorized } from './unauthorized'

const STATEFUL_METHODS = new Set(['post', 'put', 'patch', 'delete'])

export const apiClient = axios.create({
  baseURL: `${API_BASE_URL}${API_VERSION_PATH}`,
  withCredentials: true,
  // axios only auto-attaches the XSRF-TOKEN cookie as X-XSRF-TOKEN for
  // same-origin requests by default; the SPA (:5173) calling the API
  // (:8000) is cross-origin, so this must be opted into explicitly or
  // every stateful request 419s with "CSRF token mismatch".
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
  },
})

apiClient.interceptors.request.use(async (config) => {
  const method = config.method?.toLowerCase()
  const mode = credentialStrategy.modeFor(config.url)

  // The guest surface is header-authenticated and cookieless by design
  // (SRS §6.1) — bootstrapping a CSRF cookie for it would both be pointless
  // and start a session the flow is specified not to need.
  if (mode === 'cookie' && method && STATEFUL_METHODS.has(method)) {
    await ensureCsrfCookie()
  }

  return credentialStrategy.applyAuth(config)
})

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    const apiError = normalizeApiError(error)
    const url: string | undefined = error?.config?.url

    // On the guest path, both 401 and 404 mean "this token no longer opens
    // this booking" — the API returns 404 for expired, revoked and unknown
    // tokens alike so it leaks no existence signal. Either way the guest is
    // sent back to the lookup form, never to /login: they have no account
    // to sign in to (CLAUDE.md §5 "Guest booking").
    if (isGuestPath(url) && (apiError.status === 401 || apiError.status === 404)) {
      credentialStrategy.clear('booking-token')
      triggerGuestAccessLost()
      return Promise.reject(apiError)
    }

    if (apiError.status === 401) {
      credentialStrategy.clear()
      triggerUnauthorized()
    }

    return Promise.reject(apiError)
  },
)
