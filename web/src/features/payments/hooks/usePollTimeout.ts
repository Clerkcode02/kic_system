import { useEffect, useRef, useState } from 'react'

/**
 * Flags when a "waiting for the webhook" phase has run past a reasonable
 * bound, so the UI can stop spinning silently and tell the payer their
 * money was taken but confirmation is delayed, instead of looking stuck.
 */
export function usePollTimeout(isWaiting: boolean, timeoutMs = 30000): boolean {
  const [timedOut, setTimedOut] = useState(false)
  const startRef = useRef<number | null>(null)

  useEffect(() => {
    if (!isWaiting) {
      startRef.current = null
      setTimedOut(false)
      return
    }
    startRef.current ??= Date.now()
    const id = window.setInterval(() => {
      if (startRef.current !== null && Date.now() - startRef.current > timeoutMs) {
        setTimedOut(true)
      }
    }, 1000)
    return () => window.clearInterval(id)
  }, [isWaiting, timeoutMs])

  return timedOut
}
