import { Link, useLocation, useParams } from 'react-router-dom'
import { Badge, Card, EmptyState, Skeleton } from '@/components'
import { useAuth } from '@/app/providers/useAuth'
import { ProposalForm } from '@/features/freelance/proposal'
import { withNext } from '@/lib/navigation/nextParam'
import { useProject } from '../hooks/useProjects'
import type { ProjectStatus } from '../types'

const STATUS_TONE: Record<ProjectStatus, 'neutral' | 'success' | 'warning' | 'danger' | 'info'> = {
  open: 'success',
  in_progress: 'info',
  completed: 'neutral',
  cancelled: 'danger',
}

/**
 * Prompt shown in place of the proposal form to anyone who can't submit one.
 *
 * Browsing projects is public (CLAUDE.md §4), but everything on the
 * freelance side that *acts* requires an account — so an anonymous visitor
 * gets a route into signup rather than a form that would 401 on submit.
 */
function ProposalGate({ projectId }: { projectId: string }) {
  const { isAuthenticated, user } = useAuth()
  const { pathname } = useLocation()

  if (isAuthenticated && user?.role === 'freelancer') {
    return <ProposalForm projectId={projectId} />
  }

  if (isAuthenticated) {
    return (
      <Card>
        <p className="text-sm text-gray-700">
          Only freelancer accounts can submit proposals on this project.
        </p>
      </Card>
    )
  }

  return (
    <Card className="flex flex-col gap-3">
      <h2 className="text-sm font-semibold text-gray-900">Want to work on this?</h2>
      <p className="text-sm text-gray-600">
        Freelance work needs an account so we can hold your milestone payments in escrow and pay
        them out to you.
      </p>
      {/* The register link carries no `?next=`: RegisterFreelancerPage
          doesn't read one, and a new freelancer can't propose until they're
          verified and approved anyway. */}
      <div className="flex flex-wrap gap-3">
        <Link
          to="/register/freelancer"
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
        >
          Join as a freelancer
        </Link>
        <Link
          to={withNext('/login', pathname)}
          className="rounded-md px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
        >
          Sign in
        </Link>
      </div>
    </Card>
  )
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
    <div className="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4 sm:p-6">
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
        {project.required_skills.length > 0 && (
          <div className="flex flex-col gap-1.5">
            <h2 className="text-xs font-medium uppercase tracking-wide text-gray-500">
              Required skills
            </h2>
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
          </div>
        )}
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

      {project.status === 'open' && !project.contract && <ProposalGate projectId={project.id} />}
    </div>
  )
}
