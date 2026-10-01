import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  cancelProject,
  createProject,
  fetchMyProjects,
  fetchProject,
  fetchProjectCategories,
  fetchProjects,
  updateProject,
} from '../api/projectApi'
import type {
  CreateProjectPayload,
  MyProjectListFilters,
  ProjectListFilters,
  UpdateProjectPayload,
} from '../types'

const MY_PROJECTS_QUERY_KEY = ['freelance', 'my-projects'] as const

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

export function useInfiniteMyProjects(filters: MyProjectListFilters = {}) {
  return useInfiniteQuery({
    queryKey: [...MY_PROJECTS_QUERY_KEY, filters] as const,
    queryFn: ({ pageParam }) => fetchMyProjects(filters, pageParam),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.meta.next_cursor ?? undefined,
  })
}

export function useCreateProject() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateProjectPayload) => createProject(payload),
    onSuccess: () => {
      // Both lists can change: the client's own list gains a row, and the
      // public browse list gains an open project.
      queryClient.invalidateQueries({ queryKey: MY_PROJECTS_QUERY_KEY })
      queryClient.invalidateQueries({ queryKey: ['freelance', 'projects'] })
    },
  })
}

export function useUpdateProject(projectId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: UpdateProjectPayload) => updateProject(projectId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: MY_PROJECTS_QUERY_KEY })
      queryClient.invalidateQueries({ queryKey: ['freelance', 'project', projectId] })
      queryClient.invalidateQueries({ queryKey: ['freelance', 'projects'] })
    },
  })
}

export function useCancelProject() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (projectId: string) => cancelProject(projectId),
    onSuccess: (_result, projectId) => {
      queryClient.invalidateQueries({ queryKey: MY_PROJECTS_QUERY_KEY })
      queryClient.invalidateQueries({ queryKey: ['freelance', 'project', projectId] })
      queryClient.invalidateQueries({ queryKey: ['freelance', 'projects'] })
    },
  })
}
