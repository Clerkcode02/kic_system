type Handler = () => void

let unauthorizedHandler: Handler | null = null
let guestAccessLostHandler: Handler | null = null

/** AuthProvider registers itself here so the axios interceptor can clear auth state on 401. */
export function onUnauthorized(nextHandler: Handler): void {
  unauthorizedHandler = nextHandler
}

export function triggerUnauthorized(): void {
  unauthorizedHandler?.()
}

/**
 * The guest equivalent (SRS §6.1): a booking access token stopped working —
 * expired, revoked by a claim, or simply wrong. /track registers here to
 * fall back to its lookup form. Kept separate from `onUnauthorized` because
 * the two must never share a destination: sending a guest to /login is a
 * dead end, they have no account.
 */
export function onGuestAccessLost(nextHandler: Handler): void {
  guestAccessLostHandler = nextHandler
}

export function triggerGuestAccessLost(): void {
  guestAccessLostHandler?.()
}
