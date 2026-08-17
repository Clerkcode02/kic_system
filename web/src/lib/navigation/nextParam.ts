/**
 * `?next=` handling for the guest → register → claim detour (SRS §6.1): a
 * guest who decides to create an account mid-flow must land back where they
 * were, not on a generic dashboard.
 *
 * Only same-origin, path-relative destinations are honoured. An open
 * redirect here would be a phishing primitive on the one page users arrive
 * at expecting to type a password.
 */
export function safeNextPath(raw: string | null | undefined, fallback = '/'): string {
  if (!raw) return fallback

  let candidate: string
  try {
    candidate = decodeURIComponent(raw)
  } catch {
    return fallback
  }

  // Must be a single-slash-prefixed path. This rejects absolute URLs
  // ("https://evil.test"), protocol-relative ones ("//evil.test"), and
  // backslash variants that some browsers normalise into them.
  if (!candidate.startsWith('/') || candidate.startsWith('//') || candidate.startsWith('/\\')) {
    return fallback
  }

  return candidate
}

/** Builds a `?next=` query string for links into the auth flow. */
export function withNext(path: string, next: string): string {
  return `${path}?next=${encodeURIComponent(next)}`
}
