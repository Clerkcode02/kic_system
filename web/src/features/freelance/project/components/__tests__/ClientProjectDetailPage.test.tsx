import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ProjectDetail } from '../../types'

const navigateMock = vi.hoisted(() => vi.fn())
const toastMock = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn() }))
const fetchProjectMock = vi.hoisted(() => vi.fn())
const cancelProjectMock = vi.hoisted(() => vi.fn())

vi.mock('react-router-dom', async () => ({
  ...(await vi.importActual<typeof import('react-router-dom')>('react-router-dom')),
  useNavigate: () => navigateMock,
}))

vi.mock('react-hot-toast', () => ({ default: toastMock }))

vi.mock('../../api/projectApi', () => ({
  fetchProject: fetchProjectMock,
  cancelProject: cancelProjectMock,
  createProject: vi.fn(),
  updateProject: vi.fn(),
  fetchMyProjects: vi.fn(),
  fetchProjects: vi.fn(),
  fetchProjectCategories: vi.fn().mockResolvedValue([]),
  uploadProjectAttachment: vi.fn(),
}))

// The proposal list is covered by its own suite; stubbing it keeps this one
// focused on the project header, action gating and cancellation.
vi.mock('@/features/freelance/proposal', () => ({
  ProposalReviewList: ({ canHire }: { canHire: boolean }) => (
    <div data-testid="proposal-list" data-can-hire={String(canHire)} />
  ),
}))

const { ClientProjectDetailPage } = await import('../ClientProjectDetailPage')

function project(overrides: Partial<ProjectDetail> = {}): ProjectDetail {
  return {
    id: 'proj-1',
    title: 'Rebuild the marketing site',
    description: 'Full scope of the work.',
    budget_min: '500.00',
    budget_max: '1000.00',
    currency: 'CAD',
    deadline: '2027-01-01',
    status: 'open',
    required_skills: ['react'],
    category: { id: 'cat-1', name: 'Web development', slug: 'web-development' },
    client: { id: 'user-1', name: 'Alex Client' },
    contract: null,
    created_at: '2026-09-01T00:00:00Z',
    updated_at: '2026-09-01T00:00:00Z',
    ...overrides,
  }
}

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={['/customer/projects/proj-1']}>
        <Routes>
          <Route path="/customer/projects/:projectId" element={<ClientProjectDetailPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  fetchProjectMock.mockResolvedValue(project())
})

describe('ClientProjectDetailPage', () => {
  it('shows the project scope including its skills', async () => {
    renderPage()

    expect(await screen.findByText('Rebuild the marketing site')).toBeInTheDocument()
    expect(screen.getByText('react')).toBeInTheDocument()
    expect(screen.getByText(/Budget: CAD 500.00–1000.00/)).toBeInTheDocument()
  })

  it('allows hiring while the project is open with no contract', async () => {
    renderPage()

    expect(await screen.findByTestId('proposal-list')).toHaveAttribute('data-can-hire', 'true')
  })

  it('stops allowing hiring once a contract exists', async () => {
    fetchProjectMock.mockResolvedValue(
      project({ status: 'in_progress', contract: { id: 'contract-1', status: 'active' } }),
    )
    renderPage()

    // Mirrors HireFreelancer's own guard — the server would 409 anyway, but
    // offering the button at all would be misleading.
    expect(await screen.findByTestId('proposal-list')).toHaveAttribute('data-can-hire', 'false')
    expect(screen.getByRole('link', { name: /open contract/i })).toHaveAttribute(
      'href',
      '/customer/contracts/contract-1',
    )
  })

  it('hides the proposal list entirely on a cancelled project', async () => {
    fetchProjectMock.mockResolvedValue(project({ status: 'cancelled' }))
    renderPage()

    expect(await screen.findByText('Cancelled')).toBeInTheDocument()
    expect(screen.queryByTestId('proposal-list')).not.toBeInTheDocument()
  })

  it('offers no cancel action on a completed project, matching the state machine', async () => {
    fetchProjectMock.mockResolvedValue(project({ status: 'completed' }))
    renderPage()

    expect(await screen.findByText('Completed')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /cancel project/i })).not.toBeInTheDocument()
  })

  it('confirms before cancelling, then returns to the list', async () => {
    cancelProjectMock.mockResolvedValue(undefined)
    renderPage()

    await userEvent.click(await screen.findByRole('button', { name: /cancel project/i }))
    expect(cancelProjectMock).not.toHaveBeenCalled()

    await userEvent.click(screen.getByRole('button', { name: /yes, cancel it/i }))

    await waitFor(() => expect(cancelProjectMock).toHaveBeenCalledWith('proj-1'))
    await waitFor(() => expect(navigateMock).toHaveBeenCalledWith('/customer/projects'))
  })

  it('warns differently when cancelling a project that already has a freelancer', async () => {
    fetchProjectMock.mockResolvedValue(
      project({ status: 'in_progress', contract: { id: 'contract-1', status: 'active' } }),
    )
    renderPage()

    await userEvent.click(await screen.findByRole('button', { name: /cancel project/i }))

    expect(await screen.findByText(/already has a hired freelancer/i)).toBeInTheDocument()
  })

  it('reports a failed load rather than rendering an empty shell', async () => {
    fetchProjectMock.mockRejectedValue(new Error('boom'))
    renderPage()

    expect(await screen.findByText("Couldn't load this project")).toBeInTheDocument()
  })
})
