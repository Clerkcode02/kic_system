import axios from 'axios'
import { API_BASE_URL } from './config'

let csrfBootstrapped: Promise<void> | null = null

/**
 * Sanctum's SPA cookie auth requires a GET to /sanctum/csrf-cookie before
 * the first stateful (cookie-authenticated) request. Cached as a single
 * in-flight/resolved promise so concurrent callers don't re-fetch it.
 */
export function ensureCsrfCookie(): Promise<void> {
  if (!csrfBootstrapped) {
    csrfBootstrapped = axios
      .get(`${API_BASE_URL}/sanctum/csrf-cookie`, { withCredentials: true })
      .then(() => undefined)
      .catch((error) => {
        csrfBootstrapped = null
        throw error
      })
  }
  return csrfBootstrapped
}

export function resetCsrfBootstrap(): void {
  csrfBootstrapped = null
}
