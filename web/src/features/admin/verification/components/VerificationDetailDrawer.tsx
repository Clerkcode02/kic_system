import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import toast from 'react-hot-toast'
import { Badge, Button, Card, Modal } from '@/components'
import { ApiError } from '@/lib/api'
import {
  getBusinessDocumentUrl,
  getPortfolioItemUrl,
  useApproveBusinessVerification,
  useApproveFreelancerVerification,
  useBusinessVerification,
  useFreelancerVerification,
  useRejectBusinessVerification,
  useRejectFreelancerVerification,
} from '../hooks/useVerificationQueue'

const rejectReasonSchema = z.object({
  reason: z.string().min(10, 'Reason must be at least 10 characters').max(1000),
})

type RejectReasonValues = z.infer<typeof rejectReasonSchema>

interface VerificationDetailDrawerProps {
  kind: 'business' | 'freelancer'
  id: string | null
  onClose: () => void
}

function RejectReasonForm({
  isSubmitting,
  onSubmit,
  onCancel,
}: {
  isSubmitting: boolean
  onSubmit: (reason: string) => void
  onCancel: () => void
}) {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<RejectReasonValues>({
    resolver: zodResolver(rejectReasonSchema),
    defaultValues: { reason: '' },
  })

  return (
    <form
      onSubmit={handleSubmit((values) => onSubmit(values.reason))}
      className="flex flex-col gap-2 rounded-md border border-red-200 bg-red-50 p-3"
    >
      <label htmlFor="reject-reason" className="text-sm font-medium text-gray-700">
        Rejection reason
      </label>
      <textarea
        id="reject-reason"
        rows={3}
        className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        {...register('reason')}
      />
      {errors.reason && <p className="text-sm text-red-600">{errors.reason.message}</p>}
      <div className="flex justify-end gap-2">
        <Button type="button" variant="secondary" size="sm" onClick={onCancel}>
          Cancel
        </Button>
        <Button type="submit" variant="danger" size="sm" isLoading={isSubmitting}>
          Confirm reject
        </Button>
      </div>
    </form>
  )
}

function DocumentLink({
  label,
  onFetchUrl,
}: {
  label: string
  onFetchUrl: () => Promise<string>
}) {
  const [isLoading, setIsLoading] = useState(false)

  const handleClick = async () => {
    setIsLoading(true)
    try {
      const url = await onFetchUrl()
      window.open(url, '_blank', 'noopener,noreferrer')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not load the document.')
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <Button type="button" variant="secondary" size="sm" isLoading={isLoading} onClick={handleClick}>
      {label}
    </Button>
  )
}

function BusinessDetail({ businessId, onClose }: { businessId: string; onClose: () => void }) {
  const { data: business, isLoading } = useBusinessVerification(businessId)
  const approve = useApproveBusinessVerification()
  const reject = useRejectBusinessVerification()
  const [isRejecting, setIsRejecting] = useState(false)

  const handleApprove = async () => {
    try {
      await approve.mutateAsync(businessId)
      toast.success('Business verification approved.')
      onClose()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not approve this business.')
    }
  }

  const handleReject = async (reason: string) => {
    try {
      await reject.mutateAsync({ businessId, reason })
      toast.success('Business verification rejected.')
      onClose()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not reject this business.')
    }
  }

  if (isLoading || !business) {
    return <p className="text-sm text-gray-500">Loading…</p>
  }

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h3 className="text-base font-semibold text-gray-900">{business.legal_name}</h3>
        <p className="text-sm text-gray-500">
          {business.city}, {business.province} · Reg. #{business.registration_number}
        </p>
        <Badge tone={business.verification_status === 'pending' ? 'warning' : 'neutral'}>
          {business.verification_status}
        </Badge>
      </div>

      {business.owner && (
        <Card className="p-3">
          <p className="text-sm font-medium text-gray-700">Owner</p>
          <p className="text-sm text-gray-500">
            {business.owner.name} · {business.owner.email}
          </p>
        </Card>
      )}

      <div>
        <p className="mb-2 text-sm font-medium text-gray-700">Documents</p>
        {business.documents && business.documents.length > 0 ? (
          <ul className="flex flex-col gap-2">
            {business.documents.map((doc) => (
              <li
                key={doc.id}
                className="flex items-center justify-between gap-2 rounded-md border border-gray-200 p-2 text-sm"
              >
                <div>
                  <p className="font-medium text-gray-900">{doc.document_type}</p>
                  <p className="text-gray-500">{doc.issuing_authority}</p>
                </div>
                <DocumentLink
                  label="View document"
                  onFetchUrl={() => getBusinessDocumentUrl(doc.id)}
                />
              </li>
            ))}
          </ul>
        ) : (
          <p className="text-sm text-gray-500">No documents uploaded.</p>
        )}
      </div>

      {isRejecting ? (
        <RejectReasonForm
          isSubmitting={reject.isPending}
          onSubmit={handleReject}
          onCancel={() => setIsRejecting(false)}
        />
      ) : (
        <div className="flex justify-end gap-2">
          <Button variant="danger" size="sm" onClick={() => setIsRejecting(true)}>
            Reject
          </Button>
          <Button variant="primary" size="sm" isLoading={approve.isPending} onClick={handleApprove}>
            Approve
          </Button>
        </div>
      )}
    </div>
  )
}

function FreelancerDetail({
  freelancerId,
  onClose,
}: {
  freelancerId: string
  onClose: () => void
}) {
  const { data: freelancer, isLoading } = useFreelancerVerification(freelancerId)
  const approve = useApproveFreelancerVerification()
  const reject = useRejectFreelancerVerification()
  const [isRejecting, setIsRejecting] = useState(false)

  const handleApprove = async () => {
    try {
      await approve.mutateAsync(freelancerId)
      toast.success('Freelancer verification approved.')
      onClose()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not approve this freelancer.')
    }
  }

  const handleReject = async (reason: string) => {
    try {
      await reject.mutateAsync({ freelancerId, reason })
      toast.success('Freelancer verification rejected.')
      onClose()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not reject this freelancer.')
    }
  }

  if (isLoading || !freelancer) {
    return <p className="text-sm text-gray-500">Loading…</p>
  }

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h3 className="text-base font-semibold text-gray-900">{freelancer.headline}</h3>
        <p className="text-sm text-gray-500">{freelancer.years_experience} years of experience</p>
        <Badge tone={freelancer.approval_status === 'pending' ? 'warning' : 'neutral'}>
          {freelancer.approval_status}
        </Badge>
      </div>

      {freelancer.user && (
        <Card className="p-3">
          <p className="text-sm font-medium text-gray-700">Freelancer</p>
          <p className="text-sm text-gray-500">
            {freelancer.user.name} · {freelancer.user.email}
          </p>
        </Card>
      )}

      <div>
        <p className="mb-1 text-sm font-medium text-gray-700">Bio</p>
        <p className="text-sm text-gray-600">{freelancer.bio}</p>
      </div>

      {freelancer.skills && freelancer.skills.length > 0 && (
        <div>
          <p className="mb-2 text-sm font-medium text-gray-700">Skills</p>
          <div className="flex flex-wrap gap-1.5">
            {freelancer.skills.map((skill) => (
              <Badge key={skill} tone="info">
                {skill}
              </Badge>
            ))}
          </div>
        </div>
      )}

      <div>
        <p className="mb-2 text-sm font-medium text-gray-700">Portfolio</p>
        {freelancer.portfolio_items && freelancer.portfolio_items.length > 0 ? (
          <ul className="flex flex-col gap-2">
            {freelancer.portfolio_items.map((item) => (
              <li
                key={item.id}
                className="flex items-center justify-between gap-2 rounded-md border border-gray-200 p-2 text-sm"
              >
                <div>
                  <p className="font-medium text-gray-900">{item.title}</p>
                  <p className="text-gray-500">{item.description}</p>
                </div>
                <DocumentLink
                  label="View item"
                  onFetchUrl={() => getPortfolioItemUrl(item.id)}
                />
              </li>
            ))}
          </ul>
        ) : (
          <p className="text-sm text-gray-500">No portfolio items.</p>
        )}
      </div>

      {isRejecting ? (
        <RejectReasonForm
          isSubmitting={reject.isPending}
          onSubmit={handleReject}
          onCancel={() => setIsRejecting(false)}
        />
      ) : (
        <div className="flex justify-end gap-2">
          <Button variant="danger" size="sm" onClick={() => setIsRejecting(true)}>
            Reject
          </Button>
          <Button variant="primary" size="sm" isLoading={approve.isPending} onClick={handleApprove}>
            Approve
          </Button>
        </div>
      )}
    </div>
  )
}

export function VerificationDetailDrawer({ kind, id, onClose }: VerificationDetailDrawerProps) {
  return (
    <Modal
      isOpen={Boolean(id)}
      onClose={onClose}
      title={kind === 'business' ? 'Business application' : 'Freelancer application'}
    >
      {id ? (
        kind === 'business' ? (
          <BusinessDetail businessId={id} onClose={onClose} />
        ) : (
          <FreelancerDetail freelancerId={id} onClose={onClose} />
        )
      ) : null}
    </Modal>
  )
}
