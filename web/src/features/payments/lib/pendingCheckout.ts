export type PayableType = 'booking' | 'milestone'

export interface PendingCheckout {
  paymentId: string
  clientSecret: string
  amount: string
}

function storageKey(payableType: PayableType, payableId: string): string {
  return `kic:pending-checkout:${payableType}:${payableId}`
}

/**
 * The client_secret for a created PaymentIntent is only ever returned once,
 * from the create response. Stashing it here lets a refresh mid-payment
 * resume the same PaymentIntent instead of calling create again — the
 * server-side guards (CLAUDE.md §5 "no duplicate charge") reject a second
 * create for an already-pending payable anyway, so recreating isn't just
 * redundant, it fails loudly. This is the recovery path.
 */
export function savePendingCheckout(
  payableType: PayableType,
  payableId: string,
  checkout: PendingCheckout,
): void {
  try {
    sessionStorage.setItem(storageKey(payableType, payableId), JSON.stringify(checkout))
  } catch {
    // Storage unavailable (private mode, quota) — payment still works,
    // just without refresh recovery.
  }
}

export function loadPendingCheckout(
  payableType: PayableType,
  payableId: string,
): PendingCheckout | null {
  try {
    const raw = sessionStorage.getItem(storageKey(payableType, payableId))
    if (!raw) return null
    return JSON.parse(raw) as PendingCheckout
  } catch {
    return null
  }
}

export function clearPendingCheckout(payableType: PayableType, payableId: string): void {
  try {
    sessionStorage.removeItem(storageKey(payableType, payableId))
  } catch {
    // Nothing to clean up if storage isn't available.
  }
}
