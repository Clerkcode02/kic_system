import { describe, expect, it, vi } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { renderWithProviders } from '@/test/render'
import { server } from '@/test/mocks/server'
import { API_BASE_URL, API_VERSION_PATH } from '@/lib/api/config'
import { QuotationBuilderForm } from '../QuotationBuilderForm'

const apiUrl = (path: string) => `${API_BASE_URL}${API_VERSION_PATH}${path}`

// react-hot-toast needs a mounted <Toaster/> to show anything in the DOM —
// asserting the calls directly is the reliable way to check what the
// component contracts to show the user.
const toastMock = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn() }))
vi.mock('react-hot-toast', () => ({ default: toastMock }))

const quotation = {
  id: 'quo-1',
  booking_id: 'booking-1',
  previous_quotation_id: null,
  labor_cost: '100.00',
  materials_cost: '50.00',
  additional_fees: '10.00',
  platform_fee: '8.00',
  tax_amount: '15.00',
  discount_amount: '0.00',
  total_amount: '183.00',
  currency: 'CAD',
  valid_until: '2026-08-24T00:00:00Z',
  revision_number: 1,
  status: 'sent' as const,
  line_items: [],
  created_at: '2026-08-17T00:00:00Z',
  updated_at: '2026-08-17T00:00:00Z',
}

async function fillRequiredCosts(user: ReturnType<typeof userEvent.setup>) {
  await user.clear(screen.getByLabelText('Labor cost (CAD)'))
  await user.type(screen.getByLabelText('Labor cost (CAD)'), '100')
  await user.clear(screen.getByLabelText('Materials cost (CAD)'))
  await user.type(screen.getByLabelText('Materials cost (CAD)'), '50')
  await user.clear(screen.getByLabelText('Additional fees (CAD)'))
  await user.type(screen.getByLabelText('Additional fees (CAD)'), '10')
}

describe('QuotationBuilderForm', () => {
  it('sends a new quotation with the entered cost breakdown', async () => {
    const onSuccess = vi.fn()
    server.use(
      http.post(apiUrl('/bookings/booking-1/quotations'), async ({ request }) => {
        const body = (await request.json()) as Record<string, unknown>
        expect(body).toMatchObject({ labor_cost: 100, materials_cost: 50, additional_fees: 10 })
        return HttpResponse.json({ data: quotation }, { status: 201 })
      }),
    )

    const user = userEvent.setup()
    renderWithProviders(<QuotationBuilderForm bookingId="booking-1" onSuccess={onSuccess} />)

    await fillRequiredCosts(user)
    await user.click(screen.getByRole('button', { name: 'Send quotation' }))

    await waitFor(() => expect(toastMock.success).toHaveBeenCalledWith('Quotation sent.'))
    expect(onSuccess).toHaveBeenCalled()
  })

  it('sends a revision to the revise endpoint when revisingQuotationId is set', async () => {
    server.use(
      http.post(apiUrl('/quotations/quo-1/revise'), async () =>
        HttpResponse.json({ data: { ...quotation, revision_number: 2 } }, { status: 200 }),
      ),
    )

    const user = userEvent.setup()
    renderWithProviders(<QuotationBuilderForm bookingId="booking-1" revisingQuotationId="quo-1" />)

    expect(screen.getByRole('heading', { name: 'Revise quotation' })).toBeInTheDocument()

    await fillRequiredCosts(user)
    await user.click(screen.getByRole('button', { name: 'Send revision' }))

    await waitFor(() => expect(toastMock.success).toHaveBeenCalledWith('Revised quotation sent.'))
  })

  it('adds and fills a line item and includes it in the payload', async () => {
    server.use(
      http.post(apiUrl('/bookings/booking-1/quotations'), async ({ request }) => {
        const body = (await request.json()) as Record<string, unknown>
        expect(body.line_items).toEqual([{ description: 'Faucet part', quantity: 2, unit_price: 15 }])
        return HttpResponse.json({ data: quotation }, { status: 201 })
      }),
    )

    const user = userEvent.setup()
    renderWithProviders(<QuotationBuilderForm bookingId="booking-1" />)

    await fillRequiredCosts(user)
    await user.click(screen.getByRole('button', { name: 'Add line item' }))

    await user.type(screen.getByLabelText('Description'), 'Faucet part')
    const qtyInput = screen.getByLabelText('Qty')
    await user.clear(qtyInput)
    await user.type(qtyInput, '2')
    const priceInput = screen.getByLabelText('Unit price')
    await user.clear(priceInput)
    await user.type(priceInput, '15')

    await user.click(screen.getByRole('button', { name: 'Send quotation' }))

    await waitFor(() => expect(toastMock.success).toHaveBeenCalledWith('Quotation sent.'))
  })

  it('shows a validation error and never calls the API when a line item description is blank', async () => {
    const postSpy = vi.fn()
    server.use(
      http.post(apiUrl('/bookings/booking-1/quotations'), async () => {
        postSpy()
        return HttpResponse.json({ data: quotation }, { status: 201 })
      }),
    )

    const user = userEvent.setup()
    renderWithProviders(<QuotationBuilderForm bookingId="booking-1" />)

    await fillRequiredCosts(user)
    await user.click(screen.getByRole('button', { name: 'Add line item' }))
    // Leave description blank, but fill quantity/unit price so only the
    // description fails zod's min(1) check.
    const qtyInput = screen.getByLabelText('Qty')
    await user.clear(qtyInput)
    await user.type(qtyInput, '1')

    await user.click(screen.getByRole('button', { name: 'Send quotation' }))

    await waitFor(() =>
      expect(screen.getByText('Description is required')).toBeInTheDocument(),
    )
    expect(postSpy).not.toHaveBeenCalled()
  })

  it('surfaces field-level 422 errors from the server on the matching inputs', async () => {
    server.use(
      http.post(apiUrl('/bookings/booking-1/quotations'), () =>
        HttpResponse.json(
          {
            message: 'The given data was invalid.',
            errors: { labor_cost: ['Labor cost exceeds the service cap.'] },
          },
          { status: 422 },
        ),
      ),
    )

    const user = userEvent.setup()
    renderWithProviders(<QuotationBuilderForm bookingId="booking-1" />)

    await fillRequiredCosts(user)
    await user.click(screen.getByRole('button', { name: 'Send quotation' }))

    await waitFor(() =>
      expect(screen.getByText('Labor cost exceeds the service cap.')).toBeInTheDocument(),
    )
    expect(toastMock.error).toHaveBeenCalledWith('The given data was invalid.')
  })

  it('shows a generic error toast on a server failure', async () => {
    server.use(
      http.post(apiUrl('/bookings/booking-1/quotations'), () =>
        HttpResponse.json({ message: 'Something went wrong.' }, { status: 500 }),
      ),
    )

    const user = userEvent.setup()
    renderWithProviders(<QuotationBuilderForm bookingId="booking-1" />)

    await fillRequiredCosts(user)
    await user.click(screen.getByRole('button', { name: 'Send quotation' }))

    await waitFor(() => expect(toastMock.error).toHaveBeenCalledWith('Something went wrong.'))
  })

  it('calls onCancel when Cancel is clicked', async () => {
    const onCancel = vi.fn()
    const user = userEvent.setup()
    renderWithProviders(<QuotationBuilderForm bookingId="booking-1" onCancel={onCancel} />)

    await user.click(screen.getByRole('button', { name: 'Cancel' }))
    expect(onCancel).toHaveBeenCalled()
  })
})
