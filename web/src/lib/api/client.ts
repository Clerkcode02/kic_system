import axios from 'axios'
import { API_BASE_URL, API_VERSION_PATH } from './config'
import { credentialStrategy } from './authStrategy'
import { ensureCsrfCookie } from './csrf'
import { normalizeApiError } from './errors'
import { triggerUnauthorized } from './unauthorized'

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
  if (credentialStrategy.mode === 'cookie' && method && STATEFUL_METHODS.has(method)) {
    await ensureCsrfCookie()
  }
  return credentialStrategy.applyAuth(config)
})

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    const apiError = normalizeApiError(error)
    if (apiError.status === 401) {
      credentialStrategy.clear()
      triggerUnauthorized()
    }
    return Promise.reject(apiError)
  },
)
