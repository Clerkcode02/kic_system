import { useInfiniteQuery, useQuery } from '@tanstack/react-query'
import { fetchProject, fetchProjectCategories, fetchProjects } from '../api/projectApi'
import type { ProjectListFilters } from '../types'

export function useProjectCategories() {
  return useQuery({
    queryKey: ['freelance', 'project-categories'] as const,
    queryFn: fetchProjectCategories,
    staleTime: 5 * 60 * 1000,
  })
}

export function useInfiniteProjects(filters: ProjectListFilters) {
  return useInfiniteQuery({
    queryKey: ['freelance', 'projects', filters] as const,
    queryFn: ({ pageParam }) => fetchProjects(filters, pageParam),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.meta.next_cursor ?? undefined,
  })
}

export function useProject(projectId: string | undefined) {
  return useQuery({
    queryKey: ['freelance', 'project', projectId] as const,
    queryFn: () => fetchProject(projectId as string),
    enabled: Boolean(projectId),
  })
}
