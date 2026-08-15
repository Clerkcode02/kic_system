import { describe, expect, it, vi } from 'vitest'

const apiClientMock = vi.hoisted(() => ({
  get: vi.fn(),
}))

vi.mock('@/lib/api', () => ({ apiClient: apiClientMock }))

const { fetchProject, fetchProjectCategories, fetchProjects } = await import('../projectApi')

describe('fetchProjectCategories', () => {
  it('requests the shared categories endpoint', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: [] } })

    await fetchProjectCategories()

    expect(apiClientMock.get).toHaveBeenCalledWith('/categories')
  })
})

describe('fetchProjects', () => {
  it('forwards filters and cursor as query params', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: [], meta: {} } })

    await fetchProjects({ category: 'c1', budget_min: 100, budget_max: 500 }, 'abc123')

    expect(apiClientMock.get).toHaveBeenCalledWith('/projects', {
      params: { category: 'c1', budget_min: 100, budget_max: 500, cursor: 'abc123' },
    })
  })

  it('omits cursor when not provided', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: [], meta: {} } })

    await fetchProjects({})

    expect(apiClientMock.get).toHaveBeenCalledWith('/projects', {
      params: { cursor: undefined },
    })
  })
})

describe('fetchProject', () => {
  it('requests the project by id', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: { id: 'p1' } } })

    const project = await fetchProject('p1')

    expect(apiClientMock.get).toHaveBeenCalledWith('/projects/p1')
    expect(project).toEqual({ id: 'p1' })
  })
})
