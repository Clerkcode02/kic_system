import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  createCategory,
  deleteCategory,
  fetchCategoryTree,
  reorderCategories,
  updateCategory,
} from '../api/categoryApi'
import type { CategoryPayload, ReorderEntry } from '../types'

const CATEGORY_TREE_QUERY_KEY = ['admin', 'categories', 'tree'] as const

export function useCategoryTree() {
  return useQuery({
    queryKey: CATEGORY_TREE_QUERY_KEY,
    queryFn: fetchCategoryTree,
  })
}

export function useCreateCategory() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: CategoryPayload) => createCategory(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: CATEGORY_TREE_QUERY_KEY })
    },
  })
}

export function useUpdateCategory() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: CategoryPayload }) =>
      updateCategory(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: CATEGORY_TREE_QUERY_KEY })
    },
  })
}

export function useDeleteCategory() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => deleteCategory(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: CATEGORY_TREE_QUERY_KEY })
    },
  })
}

export function useReorderCategories() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (categories: ReorderEntry[]) => reorderCategories(categories),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: CATEGORY_TREE_QUERY_KEY })
    },
  })
}
