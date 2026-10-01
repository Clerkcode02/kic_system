import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ContractSummary, Milestone, MilestoneStatus } from '../../types'

const fetchContractsForMeMock = vi.hoisted(() => vi.fn())

vi.mock('../../api/contractApi', () => ({
  fetchContractsForMe: fetchContractsForMeMock,
  fetchContract: vi.fn(),
  fetchMyContracts: vi.fn(),
  createContractMilestones: vi.fn(),
  rejectMilestone: vi.fn(),
  approveMilestone: vi.fn(),
  submitMilestone: vi.fn(),
  fetchMilestoneDeliverables: vi.fn(),
  requestDeliverableUploadUrl: vi.fn(),
  confirmDeliverable: vi.fn(),
  uploadFileToS3: vi.fn(),
}))

const { ClientContractListPage } = await import('../ClientContractListPage')

function milestone(status: MilestoneStatus, id: string): Milestone {
  return {
    id,
    contract_id: 'contract-1',
    title: 'Stage',
    amount: '300.00',
    currency: 'CAD',
    due_date: '2027-01-01',
    status,
    rejection_reason: null,
    created_at: null,
    updated_at: null,
  }
}

function contract(milestones: Milestone[], id = 'contract-1'): ContractSummary {
  return {
    id,
    project_id: 'proj-1',
    proposal_id: 'prop-1',
    total_amount: '900.00',
    currency: 'CAD',
    status: 'active',
    created_at: null,
    project: { id: 'proj-1', title: 'Rebuild the marketing site', status: 'in_progress' },
    milestones,
  }
}

function renderList(contracts: ContractSummary[]) {
  fetchContractsForMeMock.mockResolvedValue({
    data: contracts,
    meta: { next_cursor: null, prev_cursor: null },
  })
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <ClientContractListPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeEach(() => vi.clearAllMocks())

describe('ClientContractListPage', () => {
  it('reads the client-scoped route, not the freelancer one', async () => {
    renderList([contract([])])

    expect(await screen.findByText('Rebuild the marketing site')).toBeInTheDocument()
    expect(fetchContractsForMeMock).toHaveBeenCalled()
  })

  it('flags a contract that is stalled waiting for milestones', async () => {
    renderList([contract([])])

    // "0 of 0 paid" would hide the fact that the client has to act.
    expect(await screen.findByText(/Needs milestones/)).toBeInTheDocument()
  })

  it('surfaces work awaiting review ahead of the paid count', async () => {
    renderList([contract([milestone('paid', 'ms-1'), milestone('submitted', 'ms-2')])])

    expect(await screen.findByText(/1 awaiting your review/)).toBeInTheDocument()
    expect(screen.queryByText(/1 of 2 milestones paid/)).not.toBeInTheDocument()
  })

  it('falls back to the paid count when nothing needs review', async () => {
    renderList([contract([milestone('paid', 'ms-1'), milestone('pending', 'ms-2')])])

    expect(await screen.findByText(/1 of 2 milestones paid/)).toBeInTheDocument()
  })

  it('links each contract to its client-side detail page', async () => {
    renderList([contract([])])

    expect(await screen.findByRole('link')).toHaveAttribute(
      'href',
      '/customer/contracts/contract-1',
    )
  })

  it('explains the empty state in terms of hiring', async () => {
    renderList([])

    expect(await screen.findByText('No contracts yet')).toBeInTheDocument()
  })
})
