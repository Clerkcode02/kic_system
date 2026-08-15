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
}

export interface CursorPage<T> {
  data: T[]
  meta: {
    next_cursor: string | null
    prev_cursor: string | null
  }
}
