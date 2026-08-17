import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Link, useParams } from 'react-router-dom'
import toast from 'react-hot-toast'
import { z } from 'zod'
import { Badge, Button, Card, EmptyState, Input, Skeleton } from '@/components'
import { ApiError } from '@/lib/api'
import { useAssignDispute, useDispute, useIssueRefund, useResolveDispute } from '../hooks/useDisputes'
import type { DisputeStatus } from '../types'

const STATUS_TONE: Record<DisputeStatus, 'neutral' | 'success' | 'warning' | 'danger' | 'info'> = {
  open: 'warning',
  under_review: 'info',
  resolved: 'success',
  closed: 'neutral',
}

const resolveSchema = z.object({
  resolution_notes: z
    .string()
    .min(10, 'Resolution notes must be at least 10 characters.')
    .max(2000, 'Resolution notes must be at most 2000 characters.'),
  release_escrow: z.boolean().optional(),
})

type ResolveFormValues = z.infer<typeof resolveSchema>

function AssignForm({ disputeId }: { disputeId: string }) {
  const [adminId, setAdminId] = useState('')
  const { mutateAsync: assign, isPending } = useAssignDispute(disputeId)

  const onSubmit = async (event: React.FormEvent) => {
    event.preventDefault()
    try {
      await assign(adminId)
      toast.success('Dispute assigned.')
      setAdminId('')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not assign this dispute.')
    }
  }

  return (
    <Card className="flex flex-col gap-4">
      <h2 className="text-sm font-semibold text-gray-900">Assign</h2>
      <form onSubmit={onSubmit} className="flex flex-col gap-3">
        <Input
          label="Admin user ID (UUID)"
          placeholder="a1b2c3d4-..."
          value={adminId}
          onChange={(event) => setAdminId(event.target.value)}
          required
        />
        <p className="text-xs text-gray-500">
          Paste the user ID of the admin this dispute should be assigned to.
        </p>
        <Button type="submit" isLoading={isPending} className="self-start">
          Assign
        </Button>
      </form>
    </Card>
  )
}

function ResolveForm({ disputeId, disabled }: { disputeId: string; disabled: boolean }) {
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<ResolveFormValues>({
    resolver: zodResolver(resolveSchema),
    defaultValues: { resolution_notes: '', release_escrow: false },
  })
  const { mutateAsync: resolve } = useResolveDispute(disputeId)

  const onSubmit = async (values: ResolveFormValues) => {
    try {
      await resolve(values)
      toast.success('Dispute resolved.')
    } catch (error) {
      if (error instanceof ApiError && error.kind === 'validation') {
        for (const [field, messages] of Object.entries(error.fieldErrors)) {
          if (field === 'resolution_notes') {
            setError(field, { message: messages[0] })
          }
        }
        toast.error(error.message)
        return
      }
      toast.error(error instanceof ApiError ? error.message : 'Could not resolve this dispute.')
    }
  }

  return (
    <Card className="flex flex-col gap-4">
      <h2 className="text-sm font-semibold text-gray-900">Resolve</h2>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-3">
        <div className="flex flex-col gap-1">
          <label htmlFor="resolution_notes" className="text-sm font-medium text-gray-700">
            Resolution notes
          </label>
          <textarea
            id="resolution_notes"
            rows={4}
            disabled={disabled}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
            {...register('resolution_notes')}
          />
          {errors.resolution_notes && (
            <p className="text-sm text-red-600">{errors.resolution_notes.message}</p>
          )}
        </div>

        <label className="flex items-center gap-2 text-sm text-gray-700">
          <input type="checkbox" disabled={disabled} {...register('release_escrow')} />
          Release escrow
        </label>

        <Button type="submit" isLoading={isSubmitting} disabled={disabled} className="self-start">
          Resolve dispute
        </Button>
      </form>
    </Card>
  )
}

function RefundForm({ disputeId: _disputeId }: { disputeId: string }) {
  const [isOpen, setIsOpen] = useState(false)
  const [paymentId, setPaymentId] = useState('')
  const [amount, setAmount] = useState('')
  const [reason, setReason] = useState('')
  const { mutateAsync: refund, isPending } = useIssueRefund()

  const onSubmit = async (event: React.FormEvent) => {
    event.preventDefault()
    try {
      await refund({
        paymentId,
        payload: {
          amount: amount ? Number(amount) : undefined,
          reason: reason || undefined,
        },
      })
      toast.success('Refund issued.')
      setPaymentId('')
      setAmount('')
      setReason('')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not issue this refund.')
    }
  }

  return (
    <Card className="flex flex-col gap-3">
      <button
        type="button"
        onClick={() => setIsOpen((open) => !open)}
        className="flex items-center justify-between text-left text-sm font-semibold text-gray-900"
      >
        Issue refund
        <span className="text-gray-400">{isOpen ? '−' : '+'}</span>
      </button>
      {isOpen && (
        <form onSubmit={onSubmit} className="flex flex-col gap-3">
          <Input
            label="Stripe Payment ID (from the booking's payment record)"
            value={paymentId}
            onChange={(event) => setPaymentId(event.target.value)}
            required
          />
          <Input
            label="Amount (optional, leave blank for full refund)"
            type="number"
            min={0}
            step="0.01"
            value={amount}
            onChange={(event) => setAmount(event.target.value)}
          />
          <Input
            label="Reason (optional)"
            value={reason}
            onChange={(event) => setReason(event.target.value)}
          />
          <Button type="submit" isLoading={isPending} className="self-start">
            Issue refund
          </Button>
        </form>
      )}
    </Card>
  )
}

export function DisputeDetailPage() {
  const { disputeId } = useParams<{ disputeId: string }>()
  const { data: dispute, isLoading, isError } = useDispute(disputeId)

  if (isLoading) {
    return (
      <div className="flex flex-col gap-4 p-4 sm:p-6">
        <Skeleton className="h-40 rounded-lg" />
      </div>
    )
  }

  if (isError || !dispute) {
    return (
      <div className="p-4 sm:p-6">
        <EmptyState title="Couldn't load this dispute" description="Please try again." />
      </div>
    )
  }

  const isFinal = dispute.status === 'resolved' || dispute.status === 'closed'

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <Link to="/admin/disputes" className="text-sm font-medium text-blue-600 hover:underline">
        ← Back to queue
      </Link>

      <Card className="flex flex-col gap-2">
        <div className="flex items-start justify-between gap-2">
          <h1 className="text-lg font-semibold text-gray-900">Dispute {dispute.id}</h1>
          <Badge tone={STATUS_TONE[dispute.status]}>{dispute.status}</Badge>
        </div>
        <p className="text-sm text-gray-500">
          {dispute.disputable_type.charAt(0).toUpperCase() + dispute.disputable_type.slice(1)} ·{' '}
          {dispute.disputable_id}
        </p>
        <p className="text-sm text-gray-500">Raised by: {dispute.raised_by}</p>
        <p className="text-sm text-gray-500">
          Assigned admin: {dispute.assigned_admin_id ?? 'Unassigned'}
        </p>
        {dispute.resolution_notes && (
          <p className="text-sm text-gray-700">Resolution notes: {dispute.resolution_notes}</p>
        )}
      </Card>

      <AssignForm disputeId={dispute.id} />

      <ResolveForm disputeId={dispute.id} disabled={isFinal} />

      {dispute.disputable_type === 'booking' && <RefundForm disputeId={dispute.id} />}
    </div>
  )
}
