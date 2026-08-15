import { Link, useParams } from 'react-router-dom'
import { Badge, Card, EmptyState, Skeleton } from '@/components'
import { ProposalForm } from '@/features/freelance/proposal'
import { useProject } from '../hooks/useProjects'
import type { ProjectStatus } from '../types'

const STATUS_TONE: Record<ProjectStatus, 'neutral' | 'success' | 'warning' | 'danger' | 'info'> = {
  open: 'success',
  in_progress: 'info',
  completed: 'neutral',
  cancelled: 'danger',
}

export function ProjectDetailPage() {
  const { projectId } = useParams<{ projectId: string }>()
  const { data: project, isLoading, isError } = useProject(projectId)

  if (isLoading) {
    return (
      <div className="flex flex-col gap-4 p-4 sm:p-6">
        <Skeleton className="h-40 rounded-lg" />
      </div>
    )
  }

  if (isError || !project) {
    return (
      <div className="p-4 sm:p-6">
        <EmptyState title="Couldn't load this project" description="Please try again." />
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <Card className="flex flex-col gap-3">
        <div className="flex items-start justify-between gap-2">
          <h1 className="text-lg font-semibold text-gray-900">{project.title}</h1>
          <Badge tone={STATUS_TONE[project.status]}>{project.status.replace('_', ' ')}</Badge>
        </div>
        <p className="text-sm text-gray-500">
          {project.category.name} · Posted by {project.client.name}
        </p>
        <p className="whitespace-pre-wrap text-sm text-gray-700">{project.description}</p>
        <div className="flex flex-wrap gap-4 text-sm text-gray-600">
          <span>
            Budget: ${project.budget_min}–${project.budget_max} {project.currency}
          </span>
          <span>Deadline: {project.deadline}</span>
        </div>
      </Card>

      {project.contract && (
        <Card>
          <p className="text-sm text-gray-700">
            You already have a contract for this project.{' '}
            <Link
              to={`/freelancer/contracts/${project.contract.id}`}
              className="font-medium text-blue-600 underline"
            >
              View contract
            </Link>
          </p>
        </Card>
      )}

      {project.status === 'open' && !project.contract && <ProposalForm projectId={project.id} />}
    </div>
  )
}
