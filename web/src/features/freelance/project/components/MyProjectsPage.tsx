import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { Badge, EmptyState, Select, Skeleton } from '@/components'
import { useInfiniteMyProjects } from '../hooks/useProjects'
import type { ProjectStatus } from '../types'

const STATUS_OPTIONS: { value: '' | ProjectStatus; label: string }[] = [
  { value: '', label: 'All statuses' },
  { value: 'open', label: 'Open' },
  { value: 'in_progress', label: 'In progress' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
]

// Same mapping as ProjectCard — one status must not mean two colours
// depending on which list the client is looking at.
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

export function MyProjectsPage() {
  const [status, setStatus] = useState<'' | ProjectStatus>('')
  const sentinelRef = useRef<HTMLDivElement | null>(null)

  const { data, isLoading, isError, fetchNextPage, hasNextPage, isFetchingNextPage } =
    useInfiniteMyProjects(status ? { status } : {})
  const projects = data?.pages.flatMap((page) => page.data) ?? []

  useEffect(() => {
    const sentinel = sentinelRef.current
    if (!sentinel) return
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && hasNextPage && !isFetchingNextPage) fetchNextPage()
      },
      { rootMargin: '200px' },
    )
    observer.observe(sentinel)
    return () => observer.disconnect()
  }, [fetchNextPage, hasNextPage, isFetchingNextPage])

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-lg font-semibold text-gray-900">My projects</h1>
          <p className="mt-1 text-sm text-gray-500">
            Projects you've posted for freelancers, in every status.
          </p>
        </div>
        {/*
          A real <Link>, not a Button with an onClick: posting a project is
          navigation, so middle-click and "open in new tab" should work.
          Button isn't polymorphic, so the primary-variant classes are
          mirrored here rather than widening the design system's API for one
          call site.
        */}
        <Link
          to="/customer/projects/new"
          className="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
        >
          Post a project
        </Link>
      </div>

      <div className="sm:max-w-xs">
        <Select
          label="Status"
          name="status"
          options={STATUS_OPTIONS}
          value={status}
          onChange={(event) => setStatus(event.target.value as '' | ProjectStatus)}
        />
      </div>

      {isLoading && (
        <div className="flex flex-col gap-3">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-24 rounded-lg" />
          ))}
        </div>
      )}

      {isError && (
        <EmptyState title="Couldn't load your projects" description="Please try again." />
      )}

      {!isLoading && !isError && projects.length === 0 && (
        <EmptyState
          title={status ? `No ${STATUS_LABEL[status].toLowerCase()} projects` : 'No projects yet'}
          description={
            status
              ? 'Try clearing the status filter.'
              : 'Post your first project and start receiving proposals from freelancers.'
          }
        />
      )}

      <ul className="flex flex-col gap-3">
        {projects.map((project) => (
          <li key={project.id}>
            <Link
              // The client-side detail page (with proposal review and hire)
              // is the next increment; until then this opens the project's
              // public page, which already renders the full scope.
              to={`/customer/projects/${project.id}`}
              className="block rounded-lg border border-gray-200 bg-white p-4 hover:border-gray-300 hover:shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-600"
            >
              <div className="flex flex-wrap items-start justify-between gap-2">
                <h2 className="font-medium text-gray-900">{project.title}</h2>
                <Badge tone={STATUS_TONE[project.status]}>{STATUS_LABEL[project.status]}</Badge>
              </div>

              <p className="mt-1 text-sm text-gray-500">
                {project.category.name} · {project.currency} {project.budget_min}–
                {project.budget_max} · due {project.deadline}
              </p>

              {project.required_skills.length > 0 && (
                <ul className="mt-2 flex flex-wrap gap-1.5">
                  {project.required_skills.map((skill) => (
                    <li
                      key={skill}
                      className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600"
                    >
                      {skill}
                    </li>
                  ))}
                </ul>
              )}

              {/*
                Only meaningful while a project is still taking proposals —
                after a hire the count is history, and the contract is what
                the client actually wants to reach.
              */}
              {project.status === 'open' && (
                <p className="mt-2 text-sm font-medium text-blue-700">
                  {project.proposals_count ?? 0}{' '}
                  {project.proposals_count === 1 ? 'proposal' : 'proposals'}
                </p>
              )}
            </Link>
          </li>
        ))}
      </ul>

      <div ref={sentinelRef} className="h-4" />
      {isFetchingNextPage && (
        <p className="py-2 text-center text-sm text-gray-500">Loading more…</p>
      )}
    </div>
  )
}
