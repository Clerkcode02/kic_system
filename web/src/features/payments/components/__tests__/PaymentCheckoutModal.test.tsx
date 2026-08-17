import { afterEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { PaymentCheckoutModal } from '../PaymentCheckoutModal'
import { loadPendingCheckout, clearPendingCheckout } from '../../lib/pendingCheckout'

// The modal only cares that StripeElementsProvider mounts its children and
// that StripeCheckoutForm calls onSucceeded — the actual Stripe.js wiring is
// covered by StripeCheckoutForm's own tests. Stubbing both here keeps this
// file focused on the modal's phase machine (form -> processing -> confirmed)
// and its pending-checkout bookkeeping.
vi.mock('../StripeElementsProvider', () => ({
  StripeElementsProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}))

const onSucceededSpy = vi.hoisted(() => vi.fn())
vi.mock('../StripeCheckoutForm', () => ({
  StripeCheckoutForm: ({ onSucceeded }: { amount: string; onSucceeded: () => void }) => {
    onSucceededSpy.mockImplementation(onSucceeded)
    return (
      <button type="button" onClick={onSucceeded}>
        Fake pay
      </button>
    )
  },
}))

afterEach(() => {
  sessionStorage.clear()
  vi.clearAllMocks()
})

const baseProps = {
  isOpen: true,
  onClose: vi.fn(),
  title: 'Pay for your booking',
  payableType: 'booking' as const,
  payableId: 'booking-1',
  paymentId: 'payment-1',
  clientSecret: 'secret_abc',
  amount: '89.00',
}

describe('PaymentCheckoutModal', () => {
  it('renders the Stripe checkout form when open', () => {
    render(<PaymentCheckoutModal {...baseProps} isConfirmed={false} />)
    expect(screen.getByRole('dialog', { name: 'Pay for your booking' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Fake pay' })).toBeInTheDocument()
  })

  it('persists a pending checkout to sessionStorage while open', () => {
    render(<PaymentCheckoutModal {...baseProps} isConfirmed={false} />)
    expect(loadPendingCheckout('booking', 'booking-1')).toEqual({
      paymentId: 'payment-1',
      clientSecret: 'secret_abc',
      amount: '89.00',
    })
  })

  it('shows the deposit breakdown when depositInfo is provided', () => {
    render(
      <PaymentCheckoutModal
        {...baseProps}
        isConfirmed={false}
        depositInfo={{ totalAmount: '300.00', remainingAmount: '211.00' }}
      />,
    )

    expect(screen.getByText('Deposit due now')).toBeInTheDocument()
    expect(screen.getByText('$211.00')).toBeInTheDocument()
    expect(screen.getByText('$300.00')).toBeInTheDocument()
  })

  it('moves to the processing phase once Stripe confirms, then to confirmed once the caller sees the webhook-driven change', async () => {
    const onConfirmed = vi.fn()
    const user = userEvent.setup()
    const { rerender } = render(
      <PaymentCheckoutModal {...baseProps} isConfirmed={false} onConfirmed={onConfirmed} />,
    )

    await user.click(screen.getByRole('button', { name: 'Fake pay' }))
    expect(await screen.findByText('Confirming your payment…')).toBeInTheDocument()

    // Still processing until the caller's own poll (isConfirmed) flips true —
    // the modal never infers success from Stripe's client-side result alone.
    expect(onConfirmed).not.toHaveBeenCalled()

    rerender(<PaymentCheckoutModal {...baseProps} isConfirmed onConfirmed={onConfirmed} />)

    await waitFor(() => expect(screen.getByText('Payment confirmed')).toBeInTheDocument())
    expect(onConfirmed).toHaveBeenCalledTimes(1)
  })

  it('clears the pending checkout once confirmed', async () => {
    const user = userEvent.setup()
    const { rerender } = render(<PaymentCheckoutModal {...baseProps} isConfirmed={false} />)

    expect(loadPendingCheckout('booking', 'booking-1')).not.toBeNull()

    await user.click(screen.getByRole('button', { name: 'Fake pay' }))
    rerender(<PaymentCheckoutModal {...baseProps} isConfirmed />)

    await waitFor(() => expect(screen.getByText('Payment confirmed')).toBeInTheDocument())
    expect(loadPendingCheckout('booking', 'booking-1')).toBeNull()
  })

  it('resets to the form phase and does not close the modal state when reopened after being closed mid-payment', () => {
    clearPendingCheckout('booking', 'booking-1')
    const { rerender } = render(<PaymentCheckoutModal {...baseProps} isOpen={false} isConfirmed={false} />)

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()

    rerender(<PaymentCheckoutModal {...baseProps} isOpen isConfirmed={false} />)
    expect(screen.getByText('Fake pay')).toBeInTheDocument()
  })
})
