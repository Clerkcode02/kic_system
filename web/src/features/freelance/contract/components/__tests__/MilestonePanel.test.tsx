import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { Milestone } from '../../types'

const useAuthMock = vi.hoisted(() => vi.fn())

vi.mock('@/app/providers/useAuth', () => ({ useAuth: useAuthMock }))

vi.mock('../../api/contractApi', () => ({
  fetchMilestoneDeliverables: vi.fn().mockResolvedValue([]),
  submitMilestone: vi.fn(),
  approveMilestone: vi.fn(),
  rejectMilestone: vi.fn(),
  createContractMilestones: vi.fn(),
  fetchContract: vi.fn(),
  fetchContractsForMe: vi.fn(),
  fetchMyContracts: vi.fn(),
  requestDeliverableUploadUrl: vi.fn(),
  confirmDeliverable: vi.fn(),
  uploadFileToS3: vi.fn(),
}))

vi.mock('@/features/payments', () => ({
  MilestoneEscrowPanel: () => <div data-testid="escrow-panel" />,
}))

const { MilestonePanel } = await import('../MilestonePanel')

const pendingMilestone: Milestone = {
  id: 'ms-1',
  contract_id: 'contract-1',
  title: 'Design mockups',
  amount: '400.00',
  currency: 'CAD',
  due_date: '2027-01-01',
  status: 'pending',
  rejection_reason: null,
  created_at: null,
  updated_at: null,
}

function renderPanel() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={queryClient}>
      <MilestonePanel milestone={pendingMilestone} contractId="contract-1" />
    </QueryClientProvider>,
  )
}

beforeEach(() => vi.clearAllMocks())

describe('MilestonePanel', () => {
  it('offers upload and submit to the hired freelancer', async () => {
    useAuthMock.mockReturnValue({ user: { role: 'freelancer' }, isAuthenticated: true })
    renderPanel()

    expect(await screen.findByRole('button', { name: /submit for approval/i })).toBeInTheDocument()
  })

  /*
   * Regression: `canManage` was keyed on milestone status alone, so any party
   * viewing this panel — including the client, who reaches a contract from
   * their own dashboard — was offered the freelancer's deliverable upload and
   * "Submit for approval" button. MilestonePolicy::submit would have rejected
   * the call, but the UI should never have offered it.
   */
  it('offers neither to a client, whose submit the API would reject', async () => {
    useAuthMock.mockReturnValue({ user: { role: 'customer' }, isAuthenticated: true })
    renderPanel()

    expect(await screen.findByText('Design mockups')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /submit for approval/i })).not.toBeInTheDocument()
  })

  it('offers neither to an anonymous viewer', async () => {
    useAuthMock.mockReturnValue({ user: null, isAuthenticated: false })
    renderPanel()

    expect(await screen.findByText('Design mockups')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /submit for approval/i })).not.toBeInTheDocument()
  })
})
