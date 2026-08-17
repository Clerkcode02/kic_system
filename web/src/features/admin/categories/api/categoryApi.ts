import { apiClient } from '@/lib/api'
import type { Category, CategoryPayload, ReorderEntry } from '../types'

export async function fetchCategoryTree(): Promise<Category[]> {
  const { data } = await apiClient.get<{ data: Category[] }>('/admin/categories')
  return data.data
}

export async function createCategory(payload: CategoryPayload): Promise<Category> {
  const { data } = await apiClient.post<{ data: Category }>('/admin/categories', payload)
  return data.data
}

export async function updateCategory(id: string, payload: CategoryPayload): Promise<Category> {
  const { data } = await apiClient.patch<{ data: Category }>(`/admin/categories/${id}`, payload)
  return data.data
}

export async function deleteCategory(id: string): Promise<void> {
  await apiClient.delete(`/admin/categories/${id}`)
}

export async function reorderCategories(categories: ReorderEntry[]): Promise<void> {
  await apiClient.post('/admin/categories/reorder', { categories })
}
