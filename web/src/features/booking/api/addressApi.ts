import { apiClient } from '@/lib/api'
import type { Address } from '../types'

export interface CreateAddressPayload {
  label?: string | null
  street: string
  unit?: string | null
  city: string
  state_province: string
  postal_code: string
  lat: number
  lng: number
  is_default?: boolean
}

export async function fetchMyAddresses(): Promise<Address[]> {
  const { data } = await apiClient.get<{ data: Address[] }>('/me/addresses')
  return data.data
}

export async function createAddress(payload: CreateAddressPayload): Promise<Address> {
  const { data } = await apiClient.post<{ data: Address }>('/me/addresses', payload)
  return data.data
}
