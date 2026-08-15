export type Role = 'customer' | 'provider' | 'freelancer' | 'admin'

export type UserStatus = 'active' | 'suspended' | 'pending'

export interface AuthUser {
  id: string
  name: string
  email: string
  phone: string
  role: Role
  status: UserStatus
  email_verified_at: string | null
  created_at: string
}

export interface LoginPayload {
  email: string
  password: string
}

export interface RegisterCustomerPayload {
  name: string
  email: string
  phone: string
  password: string
  password_confirmation: string
}

export interface BusinessHours {
  monday: { open: string; close: string } | null
  tuesday: { open: string; close: string } | null
  wednesday: { open: string; close: string } | null
  thursday: { open: string; close: string } | null
  friday: { open: string; close: string } | null
  saturday: { open: string; close: string } | null
  sunday: { open: string; close: string } | null
}

export interface RegisterBusinessPayload {
  name: string
  email: string
  phone: string
  password: string
  password_confirmation: string
  legal_name: string
  registration_number: string
  business_hours: BusinessHours
  max_bookings_per_day: number
}

export interface RegisterFreelancerPayload {
  name: string
  email: string
  phone: string
  password: string
  password_confirmation: string
  headline: string
  bio: string
  hourly_rate: number
  years_experience?: number
}

export interface ForgotPasswordPayload {
  email: string
}

export interface ResetPasswordPayload {
  token: string
  email: string
  password: string
  password_confirmation: string
}
