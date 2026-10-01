import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { Milestone, MilestoneStatus } from '../../types'

const toastMock = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn() }))
const rejectMilestoneMock = vi.hoisted(() => vi.fn())
const fetchDeliverablesMock = vi.hoisted(() => vi.fn())

vi.mock('react-hot-toast', () => ({ default: toastMock }))

vi.mock('../../api/contractApi', () => ({
  rejectMilestone: rejectMilestoneMock,
  fetchMilestoneDeliverables: fetchDeliverablesMock,
  createContractMilestones: vi.fn(),
  approveMilestone: vi.fn(),
  submitMilestone: vi.fn(),
  fetchContract: vi.fn(),
  fetchContractsForMe: vi.fn(),
  fetchMyContracts: vi.fn(),
  requestDeliverableUploadUrl: vi.fn(),
  confirmDeliverable: vi.fn(),
  uploadFileToS3: vi.fn(),
}))

// Escrow funding/release is MilestoneEscrowPanel's own concern and has its
// own coverage; stubbing keeps this suite on review and send-back.
vi.mock('@/features/payments', () => ({
  MilestoneEscrowPanel: () => <div data-testid="escrow-panel" />,
}))

const { ClientMilestoneCard } = await import('../ClientMilestoneCard')

function milestone(
  status: MilestoneStatus = 'submitted',
  overrides: Partial<Milestone> = {},
): Milestone {
  return {
    id: 'ms-1',
    contract_id: 'contract-1',
    title: 'Design mockups',
    amount: '400.00',
    currency: 'CAD',
    due_date: '2027-01-01',
    status,
    rejection_reason: null,
    created_at: null,
    updated_at: null,
    ...overrides,
  }
}

function renderCard(ms: Milestone = milestone()) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={queryClient}>
      <ClientMilestoneCard milestone={ms} contractId="contract-1" />
    </QueryClientProvider>,
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  fetchDeliverablesMock.mockResolvedValue([])
})

describe('ClientMilestoneCard', () => {
  it("labels status from the client's point of view", async () => {
    renderCard(milestone('submitted'))

    // "submitted" is the freelancer's word for it; the client's question is
    // whether it needs their attention.
    expect(await screen.findByText('Awaiting your review')).toBeInTheDocument()
  })

  it('offers no review actions on a milestone that has not been submitted', async () => {
    renderCard(milestone('pending'))

    expect(await screen.findByText('Not started')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /request changes/i })).not.toBeInTheDocument()
  })

  it('lists submitted deliverables with a download link', async () => {
    fetchDeliverablesMock.mockResolvedValue([
      {
        id: 'd-1',
        milestone_id: 'ms-1',
        mime_type: 'application/pdf',
        size_bytes: 1024,
        description: 'Homepage mockup',
        submitted_at: '2026-09-01T00:00:00Z',
        scanned: true,
        download_url: 'https://example.test/file.pdf',
      },
    ])
    renderCard()

    expect(await screen.findByText('Homepage mockup')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Download' })).toHaveAttribute(
      'href',
      'https://example.test/file.pdf',
    )
  })

  it('shows a scanning placeholder instead of a broken link when no URL is issued yet', async () => {
    fetchDeliverablesMock.mockResolvedValue([
      {
        id: 'd-1',
        milestone_id: 'ms-1',
        mime_type: 'application/pdf',
        size_bytes: 1024,
        description: 'Homepage mockup',
        submitted_at: '2026-09-01T00:00:00Z',
        scanned: false,
        download_url: null,
      },
    ])
    renderCard()

    expect(await screen.findByText('Scanning…')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Download' })).not.toBeInTheDocument()
  })

  it('requires a reason before sending a milestone back', async () => {
    renderCard()

    await userEvent.click(await screen.findByRole('button', { name: /request changes/i }))
    await userEvent.click(screen.getByRole('button', { name: /send back/i }))

    expect(await screen.findByText('Tell the freelancer what needs changing.')).toBeInTheDocument()
    // RejectMilestoneRequest requires `reason`, so an empty send would 422.
    expect(rejectMilestoneMock).not.toHaveBeenCalled()
  })

  it('sends the milestone back with the trimmed reason', async () => {
    rejectMilestoneMock.mockResolvedValue(milestone('disputed'))
    renderCard()

    await userEvent.click(await screen.findByRole('button', { name: /request changes/i }))
    await userEvent.type(screen.getByLabelText(/what needs changing/i), '  Colours are off  ')
    await userEvent.click(screen.getByRole('button', { name: /send back/i }))

    await waitFor(() => expect(rejectMilestoneMock).toHaveBeenCalledWith('ms-1', 'Colours are off'))
  })

  it('shows the reason back to the client on a disputed milestone', async () => {
    renderCard(milestone('disputed', { rejection_reason: 'Colours are off' }))

    expect(await screen.findByText('Changes requested')).toBeInTheDocument()
    expect(screen.getByText('Colours are off')).toBeInTheDocument()
    expect(screen.getByText(/waiting for the freelancer to resubmit/i)).toBeInTheDocument()
  })

  it('reports a paid milestone as settled with no actions left', async () => {
    renderCard(milestone('paid'))

    expect(await screen.findByText('Paid')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /request changes/i })).not.toBeInTheDocument()
  })
})
