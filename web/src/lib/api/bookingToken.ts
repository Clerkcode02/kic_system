/**
 * Storage for a guest booking access token (SRS §6.1).
 *
 * Memory first, `sessionStorage` as the backing store — deliberately **not**
 * `localStorage`: the token grants full control of a booking (accept a
 * quotation, pay, cancel), so it must not outlive the browser tab or sit on
 * disk for the next person to use the machine. Losing it on tab close is the
 * intended trade-off; /track's lookup form re-issues one by email.
 *
 * The value is never rendered into the DOM. Anything that needs to hand it
 * to the API asks for it here; nothing puts it in a data attribute, a link,
 * or React state that ends up in the markup.
 */
const STORAGE_KEY = 'guest.booking-token'

interface StoredToken {
  bookingNumber: string
  token: string
}

let inMemory: StoredToken | null = null

function readSession(): StoredToken | null {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    if (!raw) return null
    const parsed: unknown = JSON.parse(raw)
    if (
      typeof parsed === 'object' &&
      parsed !== null &&
      typeof (parsed as StoredToken).token === 'string' &&
      typeof (parsed as StoredToken).bookingNumber === 'string'
    ) {
      return parsed as StoredToken
    }
    return null
  } catch {
    // Private-mode Safari and hardened browser settings can throw on
    // sessionStorage access. The in-memory copy still carries the flow
    // through the current page load.
    return null
  }
}

export function setBookingToken(bookingNumber: string, token: string): void {
  inMemory = { bookingNumber, token }
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(inMemory))
  } catch {
    // Non-fatal: see readSession.
  }
}

export function getBookingToken(): string | null {
  inMemory ??= readSession()
  return inMemory?.token ?? null
}

export function getTrackedBookingNumber(): string | null {
  inMemory ??= readSession()
  return inMemory?.bookingNumber ?? null
}

export function clearBookingToken(): void {
  inMemory = null
  try {
    sessionStorage.removeItem(STORAGE_KEY)
  } catch {
    // Non-fatal: see readSession.
  }
}
