import { beforeEach, describe, expect, it } from 'vitest'
import { clearPendingCheckout, loadPendingCheckout, savePendingCheckout } from '../pendingCheckout'

describe('pendingCheckout', () => {
  beforeEach(() => {
    sessionStorage.clear()
  })

  it('round-trips a saved checkout by payable type and id', () => {
    savePendingCheckout('booking', 'b1', {
      paymentId: 'pay1',
      clientSecret: 'secret_1',
      amount: '50.00',
    })

    expect(loadPendingCheckout('booking', 'b1')).toEqual({
      paymentId: 'pay1',
      clientSecret: 'secret_1',
      amount: '50.00',
    })
  })

  it('keeps bookings and milestones with the same id separate', () => {
    savePendingCheckout('booking', 'same-id', { paymentId: 'p1', clientSecret: 's1', amount: '10.00' })
    savePendingCheckout('milestone', 'same-id', { paymentId: 'p2', clientSecret: 's2', amount: '20.00' })

    expect(loadPendingCheckout('booking', 'same-id')?.paymentId).toBe('p1')
    expect(loadPendingCheckout('milestone', 'same-id')?.paymentId).toBe('p2')
  })

  it('returns null when nothing is stored', () => {
    expect(loadPendingCheckout('booking', 'missing')).toBeNull()
  })

  it('clears a stored checkout', () => {
    savePendingCheckout('milestone', 'm1', { paymentId: 'p1', clientSecret: 's1', amount: '30.00' })
    clearPendingCheckout('milestone', 'm1')

    expect(loadPendingCheckout('milestone', 'm1')).toBeNull()
  })
})
