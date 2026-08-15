import { Link } from 'react-router-dom'
import { Badge, Card } from '@/components'
import type { ProjectListItem, ProjectStatus } from '../types'

const STATUS_TONE: Record<ProjectStatus, 'neutral' | 'success' | 'warning' | 'danger' | 'info'> = {
  open: 'success',
  in_progress: 'info',
  completed: 'neutral',
  cancelled: 'danger',
}

export function ProjectCard({ project }: { project: ProjectListItem }) {
  return (
    <Link to={`/freelancer/projects/${project.id}`}>
      <Card className="flex flex-col gap-2 transition hover:shadow-md">
        <div className="flex items-start justify-between gap-2">
          <p className="font-medium text-gray-900">{project.title}</p>
          <Badge tone={STATUS_TONE[project.status]}>{project.status.replace('_', ' ')}</Badge>
        </div>
        <p className="text-sm text-gray-500">{project.category.name}</p>
        <p className="text-sm text-gray-700">
          ${project.budget_min}–${project.budget_max} {project.currency}
        </p>
        <p className="text-xs text-gray-400">Deadline {project.deadline}</p>
      </Card>
    </Link>
  )
}
