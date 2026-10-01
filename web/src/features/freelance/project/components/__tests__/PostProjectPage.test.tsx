import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ApiError } from '@/lib/api'

const navigateMock = vi.hoisted(() => vi.fn())
const createProjectMock = vi.hoisted(() => vi.fn())
const uploadAttachmentMock = vi.hoisted(() => vi.fn())

vi.mock('react-router-dom', async () => ({
  ...(await vi.importActual<typeof import('react-router-dom')>('react-router-dom')),
  useNavigate: () => navigateMock,
}))

vi.mock('../../api/projectApi', () => ({
  createProject: createProjectMock,
  updateProject: vi.fn(),
  cancelProject: vi.fn(),
  fetchMyProjects: vi.fn(),
  fetchProject: vi.fn(),
  fetchProjects: vi.fn(),
  fetchProjectCategories: vi
    .fn()
    .mockResolvedValue([{ id: 'cat-1', name: 'Web development', slug: 'web-development' }]),
  uploadProjectAttachment: uploadAttachmentMock,
}))

vi.mock('react-hot-toast', () => ({
  default: { success: vi.fn(), error: vi.fn() },
}))

const { PostProjectPage } = await import('../PostProjectPage')

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <PostProjectPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

async function fillValidForm() {
  await waitFor(() => expect(screen.getByLabelText('Category')).not.toBeDisabled())
  await userEvent.selectOptions(screen.getByLabelText('Category'), 'cat-1')
  await userEvent.type(screen.getByLabelText('Project title'), 'Rebuild the marketing site')
  await userEvent.type(screen.getByLabelText('Description'), 'Full scope of the work.')
  await userEvent.type(screen.getByLabelText(/minimum budget/i), '500')
  await userEvent.type(screen.getByLabelText(/maximum budget/i), '1000')
  await userEvent.type(screen.getByLabelText('Deadline'), '2027-01-01')
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('PostProjectPage', () => {
  it('blocks submission and reports every missing field without calling the API', async () => {
    renderPage()

    await userEvent.click(screen.getByRole('button', { name: /publish project/i }))

    expect(await screen.findByText('Choose a category.')).toBeInTheDocument()
    expect(screen.getByText('Give your project a title.')).toBeInTheDocument()
    expect(screen.getByText('Describe the work you need done.')).toBeInTheDocument()
    expect(createProjectMock).not.toHaveBeenCalled()
  })

  it('rejects a maximum budget below the minimum, mirroring the server rule', async () => {
    renderPage()
    await fillValidForm()

    await userEvent.clear(screen.getByLabelText(/maximum budget/i))
    await userEvent.type(screen.getByLabelText(/maximum budget/i), '100')
    await userEvent.click(screen.getByRole('button', { name: /publish project/i }))

    expect(await screen.findByText('The maximum must be at least the minimum.')).toBeInTheDocument()
    expect(createProjectMock).not.toHaveBeenCalled()
  })

  it('publishes a valid project and omits skills when none were added', async () => {
    createProjectMock.mockResolvedValue({ id: 'proj-1' })
    renderPage()
    await fillValidForm()

    await userEvent.click(screen.getByRole('button', { name: /publish project/i }))

    await waitFor(() => expect(createProjectMock).toHaveBeenCalledTimes(1))
    expect(createProjectMock).toHaveBeenCalledWith({
      category_id: 'cat-1',
      title: 'Rebuild the marketing site',
      description: 'Full scope of the work.',
      budget_min: 500,
      budget_max: 1000,
      deadline: '2027-01-01',
    })
    expect(navigateMock).toHaveBeenCalledWith('/customer/projects/proj-1')
  })

  it('sends normalized skills when the client adds them', async () => {
    createProjectMock.mockResolvedValue({ id: 'proj-1' })
    renderPage()
    await fillValidForm()

    await userEvent.type(screen.getByLabelText(/required skills/i), 'React{Enter}')
    await userEvent.click(screen.getByRole('button', { name: /publish project/i }))

    await waitFor(() => expect(createProjectMock).toHaveBeenCalledTimes(1))
    expect(createProjectMock.mock.calls[0][0]).toMatchObject({ required_skills: ['react'] })
  })

  it('surfaces server-side field errors against the right inputs', async () => {
    createProjectMock.mockRejectedValue(
      new ApiError('Validation failed', 'validation', 422, {
        deadline: ['The deadline must be in the future.'],
      }),
    )
    renderPage()
    await fillValidForm()

    await userEvent.click(screen.getByRole('button', { name: /publish project/i }))

    expect(await screen.findByText('The deadline must be in the future.')).toBeInTheDocument()
    expect(navigateMock).not.toHaveBeenCalled()
  })

  it('still navigates when a brief upload fails, because the project is already published', async () => {
    createProjectMock.mockResolvedValue({ id: 'proj-1' })
    uploadAttachmentMock.mockRejectedValue(new Error('network'))
    renderPage()
    await fillValidForm()

    const file = new File(['brief'], 'brief.pdf', { type: 'application/pdf' })
    await userEvent.upload(screen.getByLabelText(/brief or reference files/i), file)
    await userEvent.click(screen.getByRole('button', { name: /publish project/i }))

    await waitFor(() => expect(uploadAttachmentMock).toHaveBeenCalled())
    // A failed attachment must not read as a failed publish.
    await waitFor(() => expect(navigateMock).toHaveBeenCalledWith('/customer/projects/proj-1'))
  })
})
