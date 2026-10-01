import { useState } from 'react'
import toast from 'react-hot-toast'
import { Button, Card, Input } from '@/components'
import { ApiError } from '@/lib/api'
import { formatMoney } from '@/lib/format/money'
import { useCreateContractMilestones } from '../hooks/useContracts'
import type { NewMilestoneInput } from '../types'

interface DraftRow {
  title: string
  amount: string
  due_date: string
}

interface MilestoneSetupFormProps {
  contractId: string
  /** Decimal string, e.g. "900.00". Milestones must sum to exactly this. */
  contractTotal: string
  currency: string
  onDone?: () => void
}

const emptyRow = (): DraftRow => ({ title: '', amount: '', due_date: '' })

/** Cents, to avoid comparing a sum of floats against the contract total. */
function toCents(value: string): number {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? Math.round(parsed * 100) : Number.NaN
}

export function MilestoneSetupForm({
  contractId,
  contractTotal,
  currency,
  onDone,
}: MilestoneSetupFormProps) {
  const [rows, setRows] = useState<DraftRow[]>([emptyRow()])
  const [errors, setErrors] = useState<Record<string, string>>({})
  const { mutateAsync, isPending } = useCreateContractMilestones(contractId)

  const totalCents = toCents(contractTotal)
  const allocatedCents = rows.reduce((sum, row) => {
    const cents = toCents(row.amount)
    return sum + (Number.isNaN(cents) ? 0 : cents)
  }, 0)
  const remainingCents = totalCents - allocatedCents
  const isBalanced = remainingCents === 0

  const updateRow = (index: number, patch: Partial<DraftRow>) => {
    setRows((prev) => prev.map((row, i) => (i === index ? { ...row, ...patch } : row)))
  }

  const addRow = () => setRows((prev) => [...prev, emptyRow()])

  const removeRow = (index: number) =>
    setRows((prev) => (prev.length === 1 ? prev : prev.filter((_, i) => i !== index)))

  /** Fills the last empty amount with whatever is left to allocate. */
  const allocateRemainder = (index: number) => {
    const otherCents = rows.reduce((sum, row, i) => {
      if (i === index) return sum
      const cents = toCents(row.amount)
      return sum + (Number.isNaN(cents) ? 0 : cents)
    }, 0)
    const remainder = totalCents - otherCents
    if (remainder > 0) updateRow(index, { amount: (remainder / 100).toFixed(2) })
  }

  /**
   * Mirrors CreateContractMilestones' rules so the client sees the problem
   * before submitting. The Action re-validates and stays the source of
   * truth — in particular the sum check, which it does in minor units.
   */
  const validate = (): Record<string, string> => {
    const found: Record<string, string> = {}

    rows.forEach((row, index) => {
      if (!row.title.trim()) found[`title-${index}`] = 'Name this milestone.'
      const cents = toCents(row.amount)
      if (!row.amount || Number.isNaN(cents) || cents <= 0) {
        found[`amount-${index}`] = 'Enter an amount above zero.'
      }
      if (!row.due_date) found[`due_date-${index}`] = 'Pick a due date.'
    })

    if (Object.keys(found).length === 0 && !isBalanced) {
      found.total =
        remainingCents > 0
          ? `${formatMoney((remainingCents / 100).toFixed(2))} of the contract total is still unallocated.`
          : `Milestones exceed the contract total by ${formatMoney((Math.abs(remainingCents) / 100).toFixed(2))}.`
    }

    return found
  }

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault()

    const found = validate()
    setErrors(found)
    if (Object.keys(found).length > 0) return

    const payload: NewMilestoneInput[] = rows.map((row) => ({
      title: row.title.trim(),
      amount: Number(row.amount),
      due_date: row.due_date,
    }))

    try {
      await mutateAsync(payload)
      toast.success('Milestones set. Fund the first one when you are ready to start.')
      onDone?.()
    } catch (error) {
      if (!(error instanceof ApiError)) {
        toast.error('Could not save these milestones.')
        return
      }

      // The contract already has work underway, so the breakdown is frozen.
      if (error.code === 'milestones_locked') {
        toast.error(
          'Work has already started on this contract, so the milestone breakdown can no longer be changed.',
        )
        return
      }

      if (error.fieldErrors.milestones?.[0]) {
        setErrors({ total: error.fieldErrors.milestones[0] })
        return
      }

      toast.error(error.message)
    }
  }

  return (
    <Card>
      <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
        <div>
          <h2 className="text-sm font-semibold text-gray-900">Set up milestones</h2>
          <p className="mt-1 text-sm text-gray-500">
            Split the {formatMoney(contractTotal)} contract into stages. You fund and approve each
            one separately, so the freelancer is paid as work lands.
          </p>
        </div>

        <ul className="flex flex-col gap-4">
          {rows.map((row, index) => (
            <li key={index} className="flex flex-col gap-3 rounded-lg border border-gray-200 p-3">
              <div className="flex items-center justify-between gap-2">
                <p className="text-xs font-medium uppercase tracking-wide text-gray-500">
                  Milestone {index + 1}
                </p>
                {rows.length > 1 && (
                  <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() => removeRow(index)}
                    disabled={isPending}
                  >
                    Remove
                  </Button>
                )}
              </div>

              <Input
                label="What gets delivered"
                name={`milestone-title-${index}`}
                placeholder="Design mockups approved"
                value={row.title}
                error={errors[`title-${index}`]}
                onChange={(event) => updateRow(index, { title: event.target.value })}
              />

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div className="flex flex-col gap-1">
                  <Input
                    label={`Amount (${currency})`}
                    name={`milestone-amount-${index}`}
                    type="number"
                    min={0}
                    step="0.01"
                    value={row.amount}
                    error={errors[`amount-${index}`]}
                    onChange={(event) => updateRow(index, { amount: event.target.value })}
                  />
                  {remainingCents > 0 && !row.amount && (
                    <button
                      type="button"
                      onClick={() => allocateRemainder(index)}
                      className="self-start text-xs font-medium text-blue-600 hover:underline"
                    >
                      Use remaining {formatMoney((remainingCents / 100).toFixed(2))}
                    </button>
                  )}
                </div>

                <Input
                  label="Due date"
                  name={`milestone-due-${index}`}
                  type="date"
                  value={row.due_date}
                  error={errors[`due_date-${index}`]}
                  onChange={(event) => updateRow(index, { due_date: event.target.value })}
                />
              </div>
            </li>
          ))}
        </ul>

        <Button
          type="button"
          variant="secondary"
          size="sm"
          onClick={addRow}
          disabled={isPending}
          className="self-start"
        >
          Add another milestone
        </Button>

        {/*
          A live running total: the server rejects any breakdown that doesn't
          sum exactly to the contract total, so showing the gap as it changes
          beats a 422 after submit.
        */}
        <div
          className={
            isBalanced
              ? 'rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800'
              : 'rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900'
          }
          aria-live="polite"
        >
          <span className="tabular-nums">
            Allocated {formatMoney((allocatedCents / 100).toFixed(2))} of{' '}
            {formatMoney(contractTotal)}
          </span>
          {!isBalanced && (
            <span className="tabular-nums">
              {' '}
              —{' '}
              {remainingCents > 0
                ? `${formatMoney((remainingCents / 100).toFixed(2))} left to allocate`
                : `${formatMoney((Math.abs(remainingCents) / 100).toFixed(2))} over`}
            </span>
          )}
        </div>

        {errors.total && <p className="text-sm text-red-600">{errors.total}</p>}

        <Button type="submit" isLoading={isPending} className="self-start">
          Save milestones
        </Button>
      </form>
    </Card>
  )
}
