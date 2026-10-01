import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ApiError } from '@/lib/api'

const toastMock = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn() }))
const createMilestonesMock = vi.hoisted(() => vi.fn())

vi.mock('react-hot-toast', () => ({ default: toastMock }))

vi.mock('../../api/contractApi', () => ({
  createContractMilestones: createMilestonesMock,
  rejectMilestone: vi.fn(),
  approveMilestone: vi.fn(),
  submitMilestone: vi.fn(),
  fetchContract: vi.fn(),
  fetchContractsForMe: vi.fn(),
  fetchMyContracts: vi.fn(),
  fetchMilestoneDeliverables: vi.fn(),
  requestDeliverableUploadUrl: vi.fn(),
  confirmDeliverable: vi.fn(),
  uploadFileToS3: vi.fn(),
}))

const { MilestoneSetupForm } = await import('../MilestoneSetupForm')

function renderForm(total = '900.00', onDone = vi.fn()) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={queryClient}>
      <MilestoneSetupForm
        contractId="contract-1"
        contractTotal={total}
        currency="CAD"
        onDone={onDone}
      />
    </QueryClientProvider>,
  )
  return { onDone }
}

async function fillRow(index: number, title: string, amount: string, due: string) {
  const titles = screen.getAllByLabelText('What gets delivered')
  const amounts = screen.getAllByLabelText('Amount (CAD)')
  const dues = screen.getAllByLabelText('Due date')
  await userEvent.type(titles[index], title)
  await userEvent.type(amounts[index], amount)
  await userEvent.type(dues[index], due)
}

beforeEach(() => vi.clearAllMocks())

describe('MilestoneSetupForm', () => {
  it('shows how much of the contract total is still unallocated', async () => {
    renderForm('900.00')

    await fillRow(0, 'Design', '400.00', '2027-01-01')

    expect(await screen.findByText(/\$500\.00 left to allocate/)).toBeInTheDocument()
  })

  it('refuses to submit a breakdown that does not sum to the contract total', async () => {
    renderForm('900.00')

    await fillRow(0, 'Design', '400.00', '2027-01-01')
    await userEvent.click(screen.getByRole('button', { name: /save milestones/i }))

    // CreateContractMilestones would 422 on this; catching it here saves a
    // round trip and names the exact shortfall.
    expect(
      await screen.findByText(/\$500\.00 of the contract total is still unallocated/),
    ).toBeInTheDocument()
    expect(createMilestonesMock).not.toHaveBeenCalled()
  })

  it('reports an over-allocation as an overage, not a shortfall', async () => {
    renderForm('900.00')

    await fillRow(0, 'Design', '1000.00', '2027-01-01')
    await userEvent.click(screen.getByRole('button', { name: /save milestones/i }))

    expect(await screen.findByText(/exceed the contract total by \$100\.00/)).toBeInTheDocument()
    expect(createMilestonesMock).not.toHaveBeenCalled()
  })

  it('submits a balanced multi-milestone breakdown', async () => {
    createMilestonesMock.mockResolvedValue([])
    const { onDone } = renderForm('900.00')

    await fillRow(0, 'Design', '400.00', '2027-01-01')
    await userEvent.click(screen.getByRole('button', { name: /add another milestone/i }))
    await fillRow(1, 'Build', '500.00', '2027-02-01')

    await userEvent.click(screen.getByRole('button', { name: /save milestones/i }))

    await waitFor(() =>
      expect(createMilestonesMock).toHaveBeenCalledWith('contract-1', [
        { title: 'Design', amount: 400, due_date: '2027-01-01' },
        { title: 'Build', amount: 500, due_date: '2027-02-01' },
      ]),
    )
    await waitFor(() => expect(onDone).toHaveBeenCalled())
  })

  it('fills the remaining amount on request so the total balances exactly', async () => {
    renderForm('900.00')

    await fillRow(0, 'Design', '400.00', '2027-01-01')
    await userEvent.click(screen.getByRole('button', { name: /add another milestone/i }))

    await userEvent.click(screen.getByRole('button', { name: /use remaining \$500\.00/i }))

    expect(screen.getAllByLabelText('Amount (CAD)')[1]).toHaveValue(500)
    expect(await screen.findByText(/Allocated \$900\.00 of \$900\.00/)).toBeInTheDocument()
  })

  it('reports every incomplete row before checking the total', async () => {
    renderForm('900.00')

    await userEvent.click(screen.getByRole('button', { name: /save milestones/i }))

    expect(await screen.findByText('Name this milestone.')).toBeInTheDocument()
    expect(screen.getByText('Enter an amount above zero.')).toBeInTheDocument()
    expect(screen.getByText('Pick a due date.')).toBeInTheDocument()
    expect(createMilestonesMock).not.toHaveBeenCalled()
  })

  it('removes a row and reflects it in the running total', async () => {
    renderForm('900.00')

    await fillRow(0, 'Design', '400.00', '2027-01-01')
    await userEvent.click(screen.getByRole('button', { name: /add another milestone/i }))
    await fillRow(1, 'Build', '500.00', '2027-02-01')
    expect(await screen.findByText(/Allocated \$900\.00 of \$900\.00/)).toBeInTheDocument()

    await userEvent.click(screen.getAllByRole('button', { name: 'Remove' })[1])

    expect(await screen.findByText(/\$500\.00 left to allocate/)).toBeInTheDocument()
  })

  it('never offers to remove the only row', async () => {
    renderForm('900.00')

    expect(screen.queryByRole('button', { name: 'Remove' })).not.toBeInTheDocument()
  })

  it('explains a milestones_locked conflict in terms of work having started', async () => {
    createMilestonesMock.mockRejectedValue(
      new ApiError(
        'Milestones cannot be redefined once work has begun on this contract.',
        'conflict',
        409,
        {},
        'milestones_locked',
      ),
    )
    renderForm('900.00')

    await fillRow(0, 'Everything', '900.00', '2027-01-01')
    await userEvent.click(screen.getByRole('button', { name: /save milestones/i }))

    await waitFor(() => expect(toastMock.error).toHaveBeenCalled())
    expect(toastMock.error.mock.calls[0][0]).toMatch(/work has already started/i)
  })

  it("surfaces the server's own sum message when it disagrees with the client", async () => {
    createMilestonesMock.mockRejectedValue(
      new ApiError('Validation failed', 'validation', 422, {
        milestones: ['Milestone amounts must sum to the contract total (900.00 CAD).'],
      }),
    )
    renderForm('900.00')

    await fillRow(0, 'Everything', '900.00', '2027-01-01')
    await userEvent.click(screen.getByRole('button', { name: /save milestones/i }))

    expect(
      await screen.findByText(/must sum to the contract total \(900\.00 CAD\)/),
    ).toBeInTheDocument()
  })
})
