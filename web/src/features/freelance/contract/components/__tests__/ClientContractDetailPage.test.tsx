import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ContractDetail, Milestone, MilestoneStatus } from '../../types'

const fetchContractMock = vi.hoisted(() => vi.fn())

vi.mock('../../api/contractApi', () => ({
  fetchContract: fetchContractMock,
  createContractMilestones: vi.fn(),
  rejectMilestone: vi.fn(),
  approveMilestone: vi.fn(),
  submitMilestone: vi.fn(),
  fetchContractsForMe: vi.fn(),
  fetchMyContracts: vi.fn(),
  fetchMilestoneDeliverables: vi.fn().mockResolvedValue([]),
  requestDeliverableUploadUrl: vi.fn(),
  confirmDeliverable: vi.fn(),
  uploadFileToS3: vi.fn(),
}))

vi.mock('@/features/payments', () => ({
  MilestoneEscrowPanel: () => <div data-testid="escrow-panel" />,
}))

const { ClientContractDetailPage } = await import('../ClientContractDetailPage')

function milestone(status: MilestoneStatus, amount = '400.00', id = 'ms-1'): Milestone {
  return {
    id,
    contract_id: 'contract-1',
    title: `Stage ${id}`,
    amount,
    currency: 'CAD',
    due_date: '2027-01-01',
    status,
    rejection_reason: null,
    created_at: null,
    updated_at: null,
  }
}

function contract(overrides: Partial<ContractDetail> = {}): ContractDetail {
  return {
    id: 'contract-1',
    project_id: 'proj-1',
    proposal_id: 'prop-1',
    total_amount: '900.00',
    currency: 'CAD',
    status: 'active',
    created_at: null,
    project: { id: 'proj-1', title: 'Rebuild the marketing site', status: 'in_progress' },
    milestones: [],
    ...overrides,
  }
}

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={['/customer/contracts/contract-1']}>
        <Routes>
          <Route path="/customer/contracts/:contractId" element={<ClientContractDetailPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  fetchContractMock.mockResolvedValue(contract())
})

describe('ClientContractDetailPage', () => {
  it('leads with milestone setup on a freshly hired contract', async () => {
    renderPage()

    // Nothing can be funded, submitted or paid until milestones exist, so
    // this is the page's primary action rather than a buried option.
    expect(await screen.findByText('Set up milestones')).toBeInTheDocument()
  })

  it('shows the milestone list once a breakdown exists, not the setup form', async () => {
    fetchContractMock.mockResolvedValue(
      contract({ milestones: [milestone('pending'), milestone('paid', '500.00', 'ms-2')] }),
    )
    renderPage()

    expect(await screen.findByText('Stage ms-1')).toBeInTheDocument()
    expect(screen.getByText('Stage ms-2')).toBeInTheDocument()
    expect(screen.queryByText('Set up milestones')).not.toBeInTheDocument()
  })

  it('totals only the paid milestones in the payout summary', async () => {
    fetchContractMock.mockResolvedValue(
      contract({
        milestones: [milestone('paid', '400.00', 'ms-1'), milestone('submitted', '500.00', 'ms-2')],
      }),
    )
    renderPage()

    expect(await screen.findByText(/\$400\.00 paid out so far/)).toBeInTheDocument()
  })

  it('does not offer milestone setup on a contract that is no longer active', async () => {
    fetchContractMock.mockResolvedValue(contract({ status: 'terminated', milestones: [] }))
    renderPage()

    // CreateContractMilestones returns 409 contract_not_active here.
    expect(await screen.findByText('No milestones on this contract')).toBeInTheDocument()
    expect(screen.queryByText('Set up milestones')).not.toBeInTheDocument()
  })

  it('links back to the project it came from', async () => {
    renderPage()

    expect(await screen.findByRole('link', { name: /view the project/i })).toHaveAttribute(
      'href',
      '/customer/projects/proj-1',
    )
  })

  it('reports a failed load rather than rendering an empty shell', async () => {
    fetchContractMock.mockRejectedValue(new Error('boom'))
    renderPage()

    expect(await screen.findByText("Couldn't load this contract")).toBeInTheDocument()
  })
})
