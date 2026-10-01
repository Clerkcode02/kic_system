import { useState } from 'react'
import toast from 'react-hot-toast'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { Badge, Button, Card, EmptyState, Modal, Skeleton } from '@/components'
import { ApiError } from '@/lib/api'
import { ProposalReviewList } from '@/features/freelance/proposal'
import { useCancelProject, useProject } from '../hooks/useProjects'
import type { ProjectStatus } from '../types'

const STATUS_TONE: Record<ProjectStatus, 'neutral' | 'success' | 'warning' | 'danger' | 'info'> = {
  open: 'success',
  in_progress: 'info',
  completed: 'neutral',
  cancelled: 'danger',
}

const STATUS_LABEL: Record<ProjectStatus, string> = {
  open: 'Open',
  in_progress: 'In progress',
  completed: 'Completed',
  cancelled: 'Cancelled',
}

export function ClientProjectDetailPage() {
  const { projectId } = useParams<{ projectId: string }>()
  const { data: project, isLoading, isError } = useProject(projectId)
  const [confirmingCancel, setConfirmingCancel] = useState(false)
  const cancel = useCancelProject()
  const navigate = useNavigate()

  if (isLoading) {
    return (
      <div className="flex flex-col gap-4 p-4 sm:p-6">
        <Skeleton className="h-40 rounded-lg" />
        <Skeleton className="h-64 rounded-lg" />
      </div>
    )
  }

  if (isError || !project) {
    return (
      <div className="p-4 sm:p-6">
        <EmptyState
          title="Couldn't load this project"
          description="It may have been removed, or you may not have access to it."
        />
      </div>
    )
  }

  const isOpen = project.status === 'open'
  // The state machine allows cancellation from open and in_progress only.
  const isCancellable = isOpen || project.status === 'in_progress'

  const handleCancel = async () => {
    try {
      await cancel.mutateAsync(project.id)
      setConfirmingCancel(false)
      toast.success('Project cancelled.')
      navigate('/customer/projects')
    } catch (error) {
      setConfirmingCancel(false)
      toast.error(error instanceof ApiError ? error.message : 'Could not cancel this project.')
    }
  }

  return (
    <div className="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4 sm:p-6">
      <Link to="/customer/projects" className="text-sm font-medium text-blue-600 hover:underline">
        ← All projects
      </Link>

      <Card>
        <div className="flex flex-col gap-3">
          <div className="flex flex-wrap items-start justify-between gap-2">
            <h1 className="text-lg font-semibold text-gray-900">{project.title}</h1>
            <Badge tone={STATUS_TONE[project.status]}>{STATUS_LABEL[project.status]}</Badge>
          </div>

          <p className="text-sm text-gray-500">{project.category.name}</p>
          <p className="whitespace-pre-wrap text-sm text-gray-700">{project.description}</p>

          <div className="flex flex-wrap gap-x-5 gap-y-1 text-sm text-gray-600">
            <span className="tabular-nums">
              Budget: {project.currency} {project.budget_min}–{project.budget_max}
            </span>
            <span>Deadline: {project.deadline}</span>
          </div>

          {project.required_skills.length > 0 && (
            <ul className="flex flex-wrap gap-1.5">
              {project.required_skills.map((skill) => (
                <li
                  key={skill}
                  className="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-700"
                >
                  {skill}
                </li>
              ))}
            </ul>
          )}

          {isCancellable && (
            <div className="flex flex-wrap gap-2 border-t border-gray-100 pt-3">
              {/* Editing is only accepted while the project is open —
                  UpdateProject returns 409 project_not_open otherwise. */}
              {isOpen && (
                <Link
                  to={`/projects/${project.id}`}
                  className="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm font-medium text-gray-900 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-400"
                >
                  View public page
                </Link>
              )}
              <Button
                type="button"
                size="sm"
                variant="danger"
                onClick={() => setConfirmingCancel(true)}
              >
                Cancel project
              </Button>
            </div>
          )}
        </div>
      </Card>

      {project.contract && (
        <Card>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="text-sm font-medium text-gray-900">A freelancer is hired</p>
              <p className="text-sm text-gray-500">
                Manage milestones and release payments from the contract.
              </p>
            </div>
            <Link
              to={`/customer/contracts/${project.contract.id}`}
              className="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
            >
              Open contract
            </Link>
          </div>
        </Card>
      )}

      {/*
        Proposals stay visible after a hire — the client should still be able
        to see who applied and who was hired — but `canHire` closes the
        actions, matching HireFreelancer's own guard.
      */}
      {project.status !== 'cancelled' && (
        <ProposalReviewList projectId={project.id} canHire={isOpen && !project.contract} />
      )}

      <Modal
        isOpen={confirmingCancel}
        onClose={() => setConfirmingCancel(false)}
        title="Cancel this project?"
      >
        <div className="flex flex-col gap-4">
          <p className="text-sm text-gray-700">
            {isOpen
              ? 'Freelancers will no longer see this project and any proposals they sent will be closed.'
              : 'This project already has a hired freelancer. Cancelling it ends the engagement — settle any funded milestones first.'}
          </p>
          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              variant="danger"
              isLoading={cancel.isPending}
              onClick={handleCancel}
            >
              Yes, cancel it
            </Button>
            <Button
              type="button"
              variant="secondary"
              disabled={cancel.isPending}
              onClick={() => setConfirmingCancel(false)}
            >
              Keep it open
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
