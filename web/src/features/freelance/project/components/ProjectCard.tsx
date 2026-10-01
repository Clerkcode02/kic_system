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
    // The canonical project URL is public, so this card resolves the same
    // whether it is rendered on /projects or inside the freelancer
    // dashboard — a hardcoded /freelancer/* path would bounce an anonymous
    // visitor to the login page.
    <Link to={`/projects/${project.id}`}>
      <Card className="flex flex-col gap-2 transition hover:shadow-md">
        <div className="flex items-start justify-between gap-2">
          <p className="font-medium text-gray-900">{project.title}</p>
          <Badge tone={STATUS_TONE[project.status]}>{project.status.replace('_', ' ')}</Badge>
        </div>
        <p className="text-sm text-gray-500">{project.category.name}</p>
        <p className="text-sm text-gray-700">
          ${project.budget_min}–${project.budget_max} {project.currency}
        </p>
        {project.required_skills.length > 0 && (
          <ul className="flex flex-wrap gap-1.5">
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
        <p className="text-xs text-gray-400">Deadline {project.deadline}</p>
      </Card>
    </Link>
  )
}
