import { describe, expect, it, vi } from 'vitest'

const apiClientMock = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
}))

const uploadsMock = vi.hoisted(() => ({
  requestUploadUrl: vi.fn(),
  confirmUpload: vi.fn(),
  uploadFileToS3: vi.fn(),
}))

vi.mock('@/lib/api', () => ({ apiClient: apiClientMock }))
vi.mock('@/lib/uploads', () => uploadsMock)

const {
  cancelProject,
  createProject,
  fetchMyProjects,
  fetchProject,
  fetchProjectCategories,
  fetchProjects,
  updateProject,
  uploadProjectAttachment,
} = await import('../projectApi')

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

describe('fetchMyProjects', () => {
  it('hits the client-scoped endpoint, not the public browse list', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: [], meta: {} } })

    await fetchMyProjects({ status: 'in_progress' }, 'cur1')

    // /projects would only ever return open projects, which is exactly what
    // this endpoint exists to avoid.
    expect(apiClientMock.get).toHaveBeenCalledWith('/me/projects', {
      params: { status: 'in_progress', cursor: 'cur1' },
    })
  })
})

describe('fetchProjects', () => {
  it('forwards the skills filter', async () => {
    apiClientMock.get.mockResolvedValue({ data: { data: [], meta: {} } })

    await fetchProjects({ skills: ['react', 'vue'] })

    expect(apiClientMock.get).toHaveBeenCalledWith('/projects', {
      params: { skills: ['react', 'vue'], cursor: undefined },
    })
  })
})

describe('createProject', () => {
  it('posts the payload and unwraps the resource envelope', async () => {
    apiClientMock.post.mockResolvedValue({ data: { data: { id: 'p1' } } })

    const result = await createProject({
      category_id: 'c1',
      title: 'Title',
      description: 'Description',
      budget_min: 500,
      budget_max: 1000,
      deadline: '2027-01-01',
      required_skills: ['react'],
    })

    expect(apiClientMock.post).toHaveBeenCalledWith('/projects', {
      category_id: 'c1',
      title: 'Title',
      description: 'Description',
      budget_min: 500,
      budget_max: 1000,
      deadline: '2027-01-01',
      required_skills: ['react'],
    })
    expect(result).toEqual({ id: 'p1' })
  })
})

describe('updateProject', () => {
  it('patches only the supplied fields', async () => {
    apiClientMock.patch.mockResolvedValue({ data: { data: { id: 'p1' } } })

    await updateProject('p1', { title: 'New title' })

    expect(apiClientMock.patch).toHaveBeenCalledWith('/projects/p1', { title: 'New title' })
  })
})

describe('cancelProject', () => {
  it('deletes the project', async () => {
    apiClientMock.delete.mockResolvedValue({ data: {} })

    await cancelProject('p1')

    expect(apiClientMock.delete).toHaveBeenCalledWith('/projects/p1')
  })
})

describe('uploadProjectAttachment', () => {
  it('presigns, PUTs to storage, then confirms against the project', async () => {
    const uploadUrl = {
      url: 'https://s3/put',
      path: 'attachments/project/p1/brief.pdf',
      headers: {},
    }
    uploadsMock.requestUploadUrl.mockResolvedValue(uploadUrl)
    uploadsMock.uploadFileToS3.mockResolvedValue(undefined)
    uploadsMock.confirmUpload.mockResolvedValue({ id: 'att1' })

    const file = new File(['brief'], 'brief.pdf', { type: 'application/pdf' })
    const result = await uploadProjectAttachment('p1', file, () => {})

    expect(uploadsMock.requestUploadUrl).toHaveBeenCalledWith('project', 'p1', 'brief.pdf')
    expect(uploadsMock.uploadFileToS3).toHaveBeenCalled()
    expect(uploadsMock.confirmUpload).toHaveBeenCalledWith('project', 'p1', {
      path: uploadUrl.path,
      mimeType: 'application/pdf',
      sizeBytes: file.size,
    })
    expect(result).toEqual({ id: 'att1' })
  })

  it('does not confirm when the storage PUT fails, so no orphan row is created', async () => {
    uploadsMock.requestUploadUrl.mockResolvedValue({ url: 'u', path: 'p', headers: {} })
    uploadsMock.uploadFileToS3.mockRejectedValue(new Error('network'))
    uploadsMock.confirmUpload.mockClear()

    const file = new File(['brief'], 'brief.pdf', { type: 'application/pdf' })

    await expect(uploadProjectAttachment('p1', file, () => {})).rejects.toThrow('network')
    expect(uploadsMock.confirmUpload).not.toHaveBeenCalled()
  })
})
