import { describe, expect, it, vi } from 'vitest'

const apiClientMock = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
}))

vi.mock('@/lib/api', () => ({ apiClient: apiClientMock }))

const { fetchPayouts, fetchStripeConnectStatus, createStripeOnboardingLink } = await import(
  '../earningsApi'
)

describe('fetchPayouts', () => {
  it('passes the cursor through', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: [], meta: {} } })

    await fetchPayouts('cursor-1')

    expect(apiClientMock.get).toHaveBeenCalledWith('/provider/me/earnings', {
      params: { cursor: 'cursor-1' },
    })
  })
})

describe('fetchStripeConnectStatus', () => {
  it('fetches the connect account status', async () => {
    apiClientMock.get.mockResolvedValue({
      data: { data: { charges_enabled: true, payouts_enabled: false } },
    })

    const result = await fetchStripeConnectStatus()

    expect(apiClientMock.get).toHaveBeenCalledWith('/provider/me/stripe/status')
    expect(result).toEqual({ charges_enabled: true, payouts_enabled: false })
  })
})

describe('createStripeOnboardingLink', () => {
  it('returns the onboarding url', async () => {
    apiClientMock.post.mockResolvedValue({ data: { data: { url: 'https://stripe.example/onboard' } } })

    const url = await createStripeOnboardingLink()

    expect(apiClientMock.post).toHaveBeenCalledWith('/provider/me/stripe/onboarding-link')
    expect(url).toBe('https://stripe.example/onboard')
  })
})
