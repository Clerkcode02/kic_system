import { describe, expect, it, vi } from 'vitest'

const apiClientMock = vi.hoisted(() => ({
  post: vi.fn(),
}))

vi.mock('@/lib/api', () => ({ apiClient: apiClientMock }))

const { acceptQuotation, rejectQuotation, sendQuotation, reviseQuotation } = await import(
  '../quotationApi'
)

describe('sendQuotation', () => {
  it('posts raw cost inputs to the booking quotations endpoint', async () => {
    apiClientMock.post.mockResolvedValue({ data: { data: { id: 'q1' } } })

    await sendQuotation('booking-1', {
      labor_cost: 100,
      materials_cost: 50,
      additional_fees: 10,
      discount_amount: 5,
      line_items: [{ description: 'Part', quantity: 1, unit_price: 20 }],
    })

    expect(apiClientMock.post).toHaveBeenCalledWith('/bookings/booking-1/quotations', {
      labor_cost: 100,
      materials_cost: 50,
      additional_fees: 10,
      discount_amount: 5,
      line_items: [{ description: 'Part', quantity: 1, unit_price: 20 }],
    })
  })
})

describe('reviseQuotation', () => {
  it('posts to the revise endpoint for the given quotation', async () => {
    apiClientMock.post.mockResolvedValue({ data: { data: { id: 'q2' } } })

    await reviseQuotation('q1', {
      labor_cost: 120,
      materials_cost: 50,
      additional_fees: 10,
    })

    expect(apiClientMock.post).toHaveBeenCalledWith('/quotations/q1/revise', {
      labor_cost: 120,
      materials_cost: 50,
      additional_fees: 10,
    })
  })
})

describe('acceptQuotation', () => {
  it('sends the Idempotency-Key header and returns the payment from meta', async () => {
    apiClientMock.post.mockResolvedValue({
      data: {
        data: { id: 'q1' },
        meta: { payment: { id: 'pay1', client_secret: 'secret_abc', amount: '100.00' } },
      },
    })

    const result = await acceptQuotation('q1', 'key-abc')

    expect(apiClientMock.post).toHaveBeenCalledWith(
      '/quotations/q1/accept',
      {},
      { headers: { 'Idempotency-Key': 'key-abc' } },
    )
    expect(result.quotation).toEqual({ id: 'q1' })
    expect(result.payment).toEqual({ id: 'pay1', client_secret: 'secret_abc', amount: '100.00' })
  })
})

describe('rejectQuotation', () => {
  it('sends an optional reason in the body', async () => {
    apiClientMock.post.mockResolvedValue({ data: { data: { id: 'q1' } } })

    await rejectQuotation('q1', 'too expensive')

    expect(apiClientMock.post).toHaveBeenCalledWith('/quotations/q1/reject', {
      reason: 'too expensive',
    })
  })

  it('omits the reason when none is given', async () => {
    apiClientMock.post.mockResolvedValue({ data: { data: { id: 'q1' } } })

    await rejectQuotation('q1')

    expect(apiClientMock.post).toHaveBeenCalledWith('/quotations/q1/reject', {
      reason: undefined,
    })
  })
})
