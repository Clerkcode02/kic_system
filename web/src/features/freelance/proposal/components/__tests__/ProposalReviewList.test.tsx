import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ApiError } from '@/lib/api'
import type { Proposal } from '../../types'

const navigateMock = vi.hoisted(() => vi.fn())
const toastMock = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn() }))
const fetchProjectProposalsMock = vi.hoisted(() => vi.fn())
const hireProposalMock = vi.hoisted(() => vi.fn())
const shortlistProposalMock = vi.hoisted(() => vi.fn())

vi.mock('react-router-dom', async () => ({
  ...(await vi.importActual<typeof import('react-router-dom')>('react-router-dom')),
  useNavigate: () => navigateMock,
}))

vi.mock('react-hot-toast', () => ({ default: toastMock }))

vi.mock('../../api/proposalApi', () => ({
  fetchProjectProposals: fetchProjectProposalsMock,
  hireProposal: hireProposalMock,
  shortlistProposal: shortlistProposalMock,
  submitProposal: vi.fn(),
  fetchMyProposals: vi.fn(),
  withdrawProposal: vi.fn(),
}))

const { ProposalReviewList } = await import('../ProposalReviewList')

function proposal(overrides: Partial<Proposal> = {}): Proposal {
  return {
    id: 'prop-1',
    project_id: 'proj-1',
    proposed_amount: '900.00',
    currency: 'CAD',
    cover_letter: 'I can build this for you.',
    delivery_days: 10,
    status: 'submitted',
    freelancer: {
      id: 'fl-1',
      user_id: 'user-1',
      headline: 'Full-stack developer',
      rating_avg: 4.5,
      name: 'Dana Smith',
    },
    created_at: '2026-09-01T00:00:00Z',
    updated_at: '2026-09-01T00:00:00Z',
    ...overrides,
  }
}

function renderList(canHire = true) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <ProposalReviewList projectId="proj-1" canHire={canHire} />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

async function openHireDialog() {
  await userEvent.click(await screen.findByRole('button', { name: 'Hire' }))
  return screen.findByRole('button', { name: /confirm and hire/i })
}

beforeEach(() => {
  vi.clearAllMocks()
  fetchProjectProposalsMock.mockResolvedValue({
    data: [proposal()],
    meta: { next_cursor: null, prev_cursor: null },
  })
})

describe('ProposalReviewList', () => {
  it('shows each proposal with the figures a client compares on', async () => {
    renderList()

    expect(await screen.findByText('Dana Smith')).toBeInTheDocument()
    expect(screen.getByText('CAD 900.00')).toBeInTheDocument()
    expect(screen.getByText('10 days to deliver')).toBeInTheDocument()
    expect(screen.getByText('4.5 ★ rating')).toBeInTheDocument()
  })

  it('says so explicitly when a freelancer has no ratings, rather than showing 0.0', async () => {
    fetchProjectProposalsMock.mockResolvedValue({
      data: [proposal({ freelancer: { ...proposal().freelancer, rating_avg: 0 } })],
      meta: { next_cursor: null, prev_cursor: null },
    })
    renderList()

    expect(await screen.findByText('No ratings yet')).toBeInTheDocument()
    expect(screen.queryByText('0.0 ★ rating')).not.toBeInTheDocument()
  })

  it('requires confirmation before hiring, because hiring is irreversible', async () => {
    renderList()

    await userEvent.click(await screen.findByRole('button', { name: 'Hire' }))

    expect(await screen.findByRole('dialog')).toBeInTheDocument()
    // The list button alone must not fire the mutation.
    expect(hireProposalMock).not.toHaveBeenCalled()
  })

  it('hires on confirmation and routes to the new contract', async () => {
    hireProposalMock.mockResolvedValue({ id: 'contract-1', project_id: 'proj-1', status: 'active' })
    renderList()

    await userEvent.click(await openHireDialog())

    await waitFor(() => expect(hireProposalMock).toHaveBeenCalledWith('prop-1'))
    await waitFor(() => expect(navigateMock).toHaveBeenCalledWith('/customer/contracts/contract-1'))
  })

  it('explains a 409 project_not_open instead of showing a generic error', async () => {
    hireProposalMock.mockRejectedValue(
      new ApiError(
        'This project is no longer open for hiring.',
        'conflict',
        409,
        {},
        'project_not_open',
      ),
    )
    renderList()

    await userEvent.click(await openHireDialog())

    await waitFor(() => expect(toastMock.error).toHaveBeenCalled())
    expect(toastMock.error.mock.calls[0][0]).toMatch(/already been hired/i)
    expect(navigateMock).not.toHaveBeenCalled()
  })

  it('explains a 403 freelancer_payouts_not_enabled with what to do next', async () => {
    hireProposalMock.mockRejectedValue(
      new ApiError(
        'This freelancer cannot receive payouts yet.',
        'forbidden',
        403,
        {},
        'freelancer_payouts_not_enabled',
      ),
    )
    renderList()

    await userEvent.click(await openHireDialog())

    await waitFor(() => expect(toastMock.error).toHaveBeenCalled())
    expect(toastMock.error.mock.calls[0][0]).toMatch(/payouts|payment setup/i)
    expect(navigateMock).not.toHaveBeenCalled()
  })

  it('shortlists a submitted proposal', async () => {
    shortlistProposalMock.mockResolvedValue(proposal({ status: 'shortlisted' }))
    renderList()

    await userEvent.click(await screen.findByRole('button', { name: 'Shortlist' }))

    await waitFor(() => expect(shortlistProposalMock).toHaveBeenCalledWith('prop-1'))
  })

  it('offers no shortlist action on an already-shortlisted proposal', async () => {
    fetchProjectProposalsMock.mockResolvedValue({
      data: [proposal({ status: 'shortlisted' })],
      meta: { next_cursor: null, prev_cursor: null },
    })
    renderList()

    expect(await screen.findByRole('button', { name: 'Hire' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Shortlist' })).not.toBeInTheDocument()
  })

  it('hides all actions once the project can no longer be hired on', async () => {
    renderList(false)

    expect(await screen.findByText('Dana Smith')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Hire' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Shortlist' })).not.toBeInTheDocument()
  })

  it('offers no actions on a proposal that is already decided', async () => {
    fetchProjectProposalsMock.mockResolvedValue({
      data: [proposal({ status: 'rejected' })],
      meta: { next_cursor: null, prev_cursor: null },
    })
    renderList()

    expect(await screen.findByText('Declined')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Hire' })).not.toBeInTheDocument()
  })

  it('refetches with the chosen sort rather than reordering client-side', async () => {
    fetchProjectProposalsMock.mockResolvedValue({
      data: [proposal(), proposal({ id: 'prop-2' })],
      meta: { next_cursor: null, prev_cursor: null },
    })
    renderList()

    await userEvent.selectOptions(await screen.findByLabelText('Sort by'), 'amount_asc')

    // Cursor pagination means ordering has to come from the server; sorting
    // the loaded page only would reorder a partial list.
    await waitFor(() =>
      expect(fetchProjectProposalsMock).toHaveBeenCalledWith('proj-1', 'amount_asc', undefined),
    )
  })

  it('invites patience when no proposals have arrived yet', async () => {
    fetchProjectProposalsMock.mockResolvedValue({
      data: [],
      meta: { next_cursor: null, prev_cursor: null },
    })
    renderList()

    expect(await screen.findByText('No proposals yet')).toBeInTheDocument()
  })
})
