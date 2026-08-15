import { describe, expect, it, vi } from 'vitest'

const apiClientMock = vi.hoisted(() => ({
  post: vi.fn(),
}))

vi.mock('@/lib/api', () => ({ apiClient: apiClientMock }))

const { acceptQuotation, rejectQuotation } = await import('../quotationApi')

describe('acceptQuotation', () => {
  it('sends the Idempotency-Key header', async () => {
    apiClientMock.post.mockResolvedValue({ data: { data: { id: 'q1' } } })

    await acceptQuotation('q1', 'key-abc')

    expect(apiClientMock.post).toHaveBeenCalledWith(
      '/quotations/q1/accept',
      {},
      { headers: { 'Idempotency-Key': 'key-abc' } },
    )
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
