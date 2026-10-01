export type ProjectStatus = 'open' | 'in_progress' | 'completed' | 'cancelled'

export interface ProjectCategoryOption {
  id: string
  name: string
  slug: string
}

export interface ProjectListItem {
  id: string
  title: string
  budget_min: string
  budget_max: string
  currency: string
  deadline: string
  status: ProjectStatus
  category: { id: string; name: string }
  required_skills: string[]
  /** Present only on the client's own list (`GET /me/projects`). */
  proposals_count?: number
  contract?: { id: string; status: string } | null
  created_at: string | null
}

export interface ProjectDetail {
  id: string
  title: string
  description: string
  budget_min: string
  budget_max: string
  currency: string
  deadline: string
  status: ProjectStatus
  required_skills: string[]
  proposals_count?: number
  category: { id: string; name: string; slug: string }
  client: { id: string; name: string }
  contract?: { id: string; status: string } | null
  created_at: string | null
  updated_at: string | null
}

export interface ProjectListFilters {
  category?: string
  budget_min?: number
  budget_max?: number
  /** Matches projects requiring *any* of these skills. */
  skills?: string[]
}

export interface MyProjectListFilters {
  status?: ProjectStatus
}

/**
 * Mirrors StoreProjectRequest. The server re-validates and is the source of
 * truth for the budget and deadline rules — these types only shape the
 * request.
 */
export interface CreateProjectPayload {
  category_id: string
  title: string
  description: string
  budget_min: number
  budget_max: number
  deadline: string
  required_skills?: string[]
}

export type UpdateProjectPayload = Partial<CreateProjectPayload>

export interface CursorPage<T> {
  data: T[]
  meta: {
    next_cursor: string | null
    prev_cursor: string | null
  }
}
