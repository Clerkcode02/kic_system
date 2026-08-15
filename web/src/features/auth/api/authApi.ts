import { apiClient } from '@/lib/api'
import type { AuthUser, LoginPayload } from '../types'

export async function fetchCurrentUser(): Promise<AuthUser> {
  const { data } = await apiClient.get<{ data: AuthUser }>('/auth/me')
  return data.data
}

export async function login(payload: LoginPayload): Promise<AuthUser> {
  const { data } = await apiClient.post<{ data: AuthUser }>('/auth/login', payload)
  return data.data
}

export async function logout(): Promise<void> {
  await apiClient.post('/auth/logout')
}

export async function logoutAllDevices(): Promise<void> {
  await apiClient.post('/auth/logout-all-devices')
}
