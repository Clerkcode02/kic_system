import { apiClient } from '@/lib/api'
import type {
  CursorPage,
  ProjectCategoryOption,
  ProjectDetail,
  ProjectListFilters,
  ProjectListItem,
} from '../types'

export async function fetchProjectCategories(): Promise<ProjectCategoryOption[]> {
  const { data } = await apiClient.get<{ data: ProjectCategoryOption[] }>('/categories')
  return data.data
}

export async function fetchProjects(
  filters: ProjectListFilters,
  cursor?: string,
): Promise<CursorPage<ProjectListItem>> {
  const { data } = await apiClient.get<CursorPage<ProjectListItem>>('/projects', {
    params: { ...filters, cursor },
  })
  return data
}

export async function fetchProject(projectId: string): Promise<ProjectDetail> {
  const { data } = await apiClient.get<{ data: ProjectDetail }>(`/projects/${projectId}`)
  return data.data
}
