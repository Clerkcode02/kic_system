import { apiClient } from '@/lib/api'
import type {
  AuthUser,
  ForgotPasswordPayload,
  LoginPayload,
  RegisterBusinessPayload,
  RegisterCustomerPayload,
  RegisterFreelancerPayload,
  ResetPasswordPayload,
} from '../types'

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

export async function registerCustomer(payload: RegisterCustomerPayload): Promise<AuthUser> {
  const { data } = await apiClient.post<{ data: AuthUser }>('/auth/register/customer', payload)
  return data.data
}

export async function registerBusiness(payload: RegisterBusinessPayload): Promise<AuthUser> {
  const { data } = await apiClient.post<{ data: AuthUser }>('/auth/register/business', payload)
  return data.data
}

export async function registerFreelancer(payload: RegisterFreelancerPayload): Promise<AuthUser> {
  const { data } = await apiClient.post<{ data: AuthUser }>('/auth/register/freelancer', payload)
  return data.data
}

export async function verifyEmail(id: string, hash: string): Promise<void> {
  await apiClient.post(`/auth/email/verify/${id}/${hash}`)
}

export async function resendEmailVerification(email: string): Promise<void> {
  await apiClient.post('/auth/email/resend', { email })
}

/**
 * No forgot/reset-password endpoints exist on the backend yet (no routes,
 * no FormRequests) — these follow the naming convention of the existing
 * /auth/email/verify pair until the real contract lands.
 */
export async function forgotPassword(payload: ForgotPasswordPayload): Promise<void> {
  await apiClient.post('/auth/password/forgot', payload)
}

export async function resetPassword(payload: ResetPasswordPayload): Promise<void> {
  await apiClient.post('/auth/password/reset', payload)
}
