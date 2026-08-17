import { describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { StripeCheckoutForm } from '../StripeCheckoutForm'

const confirmPaymentMock = vi.hoisted(() => vi.fn())
const useStripeMock = vi.hoisted(() => vi.fn())
const useElementsMock = vi.hoisted(() => vi.fn())

// StripeCheckoutForm talks to the Stripe.js SDK directly (not the app's
// apiClient), so this is mocked at the @stripe/react-stripe-js boundary
// rather than with MSW — there is no HTTP call to intercept from here.
vi.mock('@stripe/react-stripe-js', () => ({
  useStripe: useStripeMock,
  useElements: useElementsMock,
  PaymentElement: () => <div data-testid="payment-element" />,
}))

function setStripeReady() {
  useStripeMock.mockReturnValue({ confirmPayment: confirmPaymentMock })
  useElementsMock.mockReturnValue({})
}

describe('StripeCheckoutForm', () => {
  it('disables the submit button until Stripe.js and Elements are ready', () => {
    useStripeMock.mockReturnValue(null)
    useElementsMock.mockReturnValue(null)

    render(<StripeCheckoutForm amount="89.00" onSucceeded={vi.fn()} />)

    expect(screen.getByRole('button', { name: /Pay/ })).toBeDisabled()
  })

  it('calls onSucceeded when confirmPayment resolves with a succeeded PaymentIntent', async () => {
    setStripeReady()
    confirmPaymentMock.mockResolvedValue({ paymentIntent: { status: 'succeeded' } })
    const onSucceeded = vi.fn()
    const user = userEvent.setup()

    render(<StripeCheckoutForm amount="89.00" onSucceeded={onSucceeded} />)
    await user.click(screen.getByRole('button', { name: /Pay/ }))

    await waitFor(() => expect(onSucceeded).toHaveBeenCalled())
    expect(confirmPaymentMock).toHaveBeenCalledWith(
      expect.objectContaining({ redirect: 'if_required' }),
    )
  })

  it('also treats a "processing" PaymentIntent as success (3DS/async methods)', async () => {
    setStripeReady()
    confirmPaymentMock.mockResolvedValue({ paymentIntent: { status: 'processing' } })
    const onSucceeded = vi.fn()
    const user = userEvent.setup()

    render(<StripeCheckoutForm amount="89.00" onSucceeded={onSucceeded} />)
    await user.click(screen.getByRole('button', { name: /Pay/ }))

    await waitFor(() => expect(onSucceeded).toHaveBeenCalled())
  })

  it('shows the card error and keeps the form mounted so the payer can retry', async () => {
    setStripeReady()
    confirmPaymentMock.mockResolvedValue({
      error: { message: 'Your card was declined.' },
    })
    const onSucceeded = vi.fn()
    const user = userEvent.setup()

    render(<StripeCheckoutForm amount="89.00" onSucceeded={onSucceeded} />)
    await user.click(screen.getByRole('button', { name: /Pay/ }))

    await waitFor(() => expect(screen.getByText('Your card was declined.')).toBeInTheDocument())
    expect(onSucceeded).not.toHaveBeenCalled()
    expect(screen.getByTestId('payment-element')).toBeInTheDocument()
  })

  it('shows a fallback message when the intent needs further confirmation', async () => {
    setStripeReady()
    confirmPaymentMock.mockResolvedValue({ paymentIntent: { status: 'requires_action' } })
    const user = userEvent.setup()

    render(<StripeCheckoutForm amount="89.00" onSucceeded={vi.fn()} />)
    await user.click(screen.getByRole('button', { name: /Pay/ }))

    await waitFor(() =>
      expect(
        screen.getByText('Payment requires additional confirmation. Please try again.'),
      ).toBeInTheDocument(),
    )
  })

  it('renders the amount in the submit button label', () => {
    setStripeReady()
    render(<StripeCheckoutForm amount="123.45" onSucceeded={vi.fn()} />)
    expect(screen.getByRole('button', { name: /123\.45/ })).toBeInTheDocument()
  })
})
