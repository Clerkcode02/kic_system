import { useState } from 'react'

/**
 * One key per wizard submission, generated on first render and reused on
 * every retry so a resubmit (double-click, network retry) replays the same
 * Idempotency-Key instead of creating a duplicate booking. Call `renew()`
 * only after a flow completes and a fresh submission is about to start —
 * it's state, not a ref, so the new key actually propagates to callers on
 * the next render.
 */
export function useIdempotencyKey() {
  const [key, setKey] = useState<string>(() => crypto.randomUUID())

  const renew = () => {
    setKey(crypto.randomUUID())
  }

  return { key, renew }
}
