import { apiClient } from '@/lib/api'
import type {
  Category,
  CursorPage,
  ServiceDetail,
  ServiceListFilters,
  ServiceListItem,
} from '../types'

export async function fetchCategories(): Promise<Category[]> {
  const { data } = await apiClient.get<{ data: Category[] }>('/categories')
  return data.data
}

export async function fetchServices(
  filters: ServiceListFilters,
  cursor?: string,
): Promise<CursorPage<ServiceListItem>> {
  const { data } = await apiClient.get<CursorPage<ServiceListItem>>('/services', {
    params: { ...filters, cursor },
  })
  return data
}

export async function fetchService(serviceId: string): Promise<ServiceDetail> {
  const { data } = await apiClient.get<{ data: ServiceDetail }>(`/services/${serviceId}`)
  return data.data
}
