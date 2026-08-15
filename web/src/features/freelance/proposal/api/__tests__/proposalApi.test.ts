import { describe, expect, it, vi } from 'vitest'

const apiClientMock = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
}))

vi.mock('@/lib/api', () => ({ apiClient: apiClientMock }))

const { fetchMyProposals, submitProposal, withdrawProposal } = await import('../proposalApi')

describe('submitProposal', () => {
  it('posts the proposal payload to the project proposals endpoint', async () => {
    apiClientMock.post.mockResolvedValue({ data: { data: { id: 'prop1' } } })

    const payload = { proposed_amount: 500, cover_letter: 'I can do this.', delivery_days: 7 }
    const proposal = await submitProposal('proj1', payload)

    expect(apiClientMock.post).toHaveBeenCalledWith('/projects/proj1/proposals', payload)
    expect(proposal).toEqual({ id: 'prop1' })
  })
})

describe('fetchMyProposals', () => {
  it('requests the caller-scoped proposals endpoint with cursor', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: [], meta: {} } })

    await fetchMyProposals('cursor1')

    expect(apiClientMock.get).toHaveBeenCalledWith('/freelancers/me/proposals', {
      params: { cursor: 'cursor1' },
    })
  })
})

describe('withdrawProposal', () => {
  it('posts to the withdraw endpoint', async () => {
    apiClientMock.post.mockResolvedValue({ data: { data: { id: 'prop1', status: 'withdrawn' } } })

    const proposal = await withdrawProposal('prop1')

    expect(apiClientMock.post).toHaveBeenCalledWith('/proposals/prop1/withdraw')
    expect(proposal).toEqual({ id: 'prop1', status: 'withdrawn' })
  })
})
