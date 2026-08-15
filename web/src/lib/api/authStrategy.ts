import type { InternalAxiosRequestConfig } from 'axios'

/**
 * Single seam for the credential strategy. Phase 1 (web SPA) is cookie-based
 * (Sanctum stateful auth). Phase 2 (mobile) swaps this module for a Bearer
 * token strategy without touching any call site — see CLAUDE.md §9.2.
 */
export const credentialStrategy = {
  mode: 'cookie' as const,

  /** Cookie mode sends credentials automatically; nothing to attach per-request. */
  applyAuth(config: InternalAxiosRequestConfig): InternalAxiosRequestConfig {
    return config
  },

  /** No client-side token to clear in cookie mode; the server invalidates the session cookie. */
  clear(): void {},
}
