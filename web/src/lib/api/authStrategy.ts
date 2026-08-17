import type { InternalAxiosRequestConfig } from 'axios'
import { clearBookingToken, getBookingToken } from './bookingToken'

/**
 * The single seam for the credential strategy. Three modes live here and
 * nowhere else (CLAUDE.md §9.2):
 *
 * - `cookie`   — Sanctum stateful auth for the logged-in web SPA (Phase 1).
 * - `bearer`   — a Sanctum personal access token; the Phase 2 mobile path,
 *                built now so only this module changes then.
 * - `booking-token` — a guest's booking access token, sent as
 *                `X-Booking-Token` on the `/guest/*` surface (SRS §6.1).
 *
 * Which one applies is decided per-request by the URL, not by global state:
 * a signed-in user reading their dashboard and a guest tracking a booking
 * can coexist in the same tab, and neither leaks its credential onto the
 * other's requests.
 */
export type CredentialMode = 'cookie' | 'bearer' | 'booking-token'

/** Routes authorized by a booking access token rather than a session. */
const GUEST_PATH_PREFIX = '/guest/'

let bearerToken: string | null = null

/** Phase 2 / native clients: hand the strategy a Sanctum token to use. */
export function setBearerToken(token: string | null): void {
  bearerToken = token
}

export function isGuestPath(url: string | undefined): boolean {
  if (!url) return false
  // Tolerates both relative ('/guest/...') and absolute URLs, since axios
  // call sites may pass either.
  const path = url.startsWith('http') ? new URL(url).pathname.replace(/^\/api\/v1/, '') : url
  return path.startsWith(GUEST_PATH_PREFIX)
}

interface CredentialStrategy {
  modeFor(url: string | undefined): CredentialMode
  readonly defaultMode: CredentialMode
  applyAuth(config: InternalAxiosRequestConfig): InternalAxiosRequestConfig
  clear(mode?: CredentialMode): void
}

export const credentialStrategy: CredentialStrategy = {
  /**
   * The mode a given request will use. `cookie` is the default because the
   * SPA is the only Phase 1 client; a request is only ever promoted off it
   * by an explicit signal (a guest URL, or an installed bearer token).
   */
  modeFor(url: string | undefined): CredentialMode {
    if (isGuestPath(url)) return 'booking-token'
    if (bearerToken !== null) return 'bearer'
    return 'cookie'
  },

  /**
   * Kept for the CSRF interceptor, which needs to know whether the *default*
   * transport is cookie-based at all.
   */
  get defaultMode(): CredentialMode {
    return bearerToken !== null ? 'bearer' : 'cookie'
  },

  applyAuth(config: InternalAxiosRequestConfig): InternalAxiosRequestConfig {
    const mode = credentialStrategy.modeFor(config.url)

    if (mode === 'booking-token') {
      const token = getBookingToken()
      if (token) {
        config.headers.set('X-Booking-Token', token)
      }
      // A guest request must never ride the session: without this, a
      // signed-in user's cookies would be attached to /guest/* calls and the
      // server would resolve them as that user instead of the token holder.
      config.withCredentials = false
      return config
    }

    if (mode === 'bearer' && bearerToken) {
      config.headers.set('Authorization', `Bearer ${bearerToken}`)
    }

    return config
  },

  /** Clears whatever credential the given request kind holds. */
  clear(mode: CredentialMode = credentialStrategy.defaultMode): void {
    if (mode === 'booking-token') {
      clearBookingToken()
      return
    }
    if (mode === 'bearer') {
      bearerToken = null
    }
    // Cookie mode has no client-side credential to clear — the server
    // invalidates the session cookie.
  },
}
