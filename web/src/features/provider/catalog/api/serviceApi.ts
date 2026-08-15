import { apiClient } from '@/lib/api'
import type {
  CursorPage,
  Service,
  StoreServicePayload,
  UpdateServicePayload,
} from '../types'

export async function fetchMyServices(cursor: string | undefined): Promise<CursorPage<Service>> {
  const { data } = await apiClient.get<CursorPage<Service>>('/provider/me/services', {
    params: { cursor },
  })
  return data
}

export async function fetchService(serviceId: string): Promise<Service> {
  const { data } = await apiClient.get<{ data: Service }>(`/provider/services/${serviceId}`)
  return data.data
}

export async function createService(payload: StoreServicePayload): Promise<Service> {
  const { data } = await apiClient.post<{ data: Service }>('/provider/services', payload)
  return data.data
}

export async function updateService(
  serviceId: string,
  payload: UpdateServicePayload,
): Promise<Service> {
  const { data } = await apiClient.patch<{ data: Service }>(
    `/provider/services/${serviceId}`,
    payload,
  )
  return data.data
}

export async function deactivateService(serviceId: string): Promise<Service> {
  return updateService(serviceId, { is_active: false })
}

export async function deleteService(serviceId: string): Promise<void> {
  await apiClient.delete(`/provider/services/${serviceId}`)
}
