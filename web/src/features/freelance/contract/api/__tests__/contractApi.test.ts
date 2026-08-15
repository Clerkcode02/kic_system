import { describe, expect, it, vi } from 'vitest'

const apiClientMock = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
}))

vi.mock('@/lib/api', () => ({ apiClient: apiClientMock }))

const {
  confirmDeliverable,
  fetchContract,
  fetchMilestoneDeliverables,
  fetchMyContracts,
  requestDeliverableUploadUrl,
  submitMilestone,
} = await import('../contractApi')

describe('fetchMyContracts', () => {
  it('requests the caller-scoped contracts endpoint with cursor', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: [], meta: {} } })

    await fetchMyContracts('cursor1')

    expect(apiClientMock.get).toHaveBeenCalledWith('/freelancer/me/contracts', {
      params: { cursor: 'cursor1' },
    })
  })
})

describe('fetchContract', () => {
  it('requests the contract by id', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: { id: 'c1' } } })

    const contract = await fetchContract('c1')

    expect(apiClientMock.get).toHaveBeenCalledWith('/contracts/c1')
    expect(contract).toEqual({ id: 'c1' })
  })
})

describe('fetchMilestoneDeliverables', () => {
  it('requests deliverables for the milestone', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: [] } })

    await fetchMilestoneDeliverables('m1')

    expect(apiClientMock.get).toHaveBeenCalledWith('/milestones/m1/deliverables')
  })
})

describe('requestDeliverableUploadUrl', () => {
  it('posts the filename to the upload-url endpoint', async () => {
    apiClientMock.post.mockResolvedValue({ data: { data: { path: 'p', url: 'u', headers: {} } } })

    await requestDeliverableUploadUrl('m1', 'file.pdf')

    expect(apiClientMock.post).toHaveBeenCalledWith('/milestones/m1/deliverables/upload-url', {
      filename: 'file.pdf',
    })
  })
})

describe('confirmDeliverable', () => {
  it('posts the confirm payload', async () => {
    apiClientMock.post.mockResolvedValue({ data: { data: { id: 'd1' } } })

    const payload = { file_path: 'p', mime_type: 'application/pdf', size_bytes: 100 }
    await confirmDeliverable('m1', payload)

    expect(apiClientMock.post).toHaveBeenCalledWith('/milestones/m1/deliverables', payload)
  })
})

describe('submitMilestone', () => {
  it('posts the deliverable ids', async () => {
    apiClientMock.post.mockResolvedValue({ data: { data: { id: 'm1', status: 'submitted' } } })

    await submitMilestone('m1', ['d1', 'd2'])

    expect(apiClientMock.post).toHaveBeenCalledWith('/milestones/m1/submit', {
      deliverable_ids: ['d1', 'd2'],
    })
  })
})
