export type Role =
  'customer' | 'provider_owner' | 'provider_staff' | 'freelancer' | 'admin' | 'super_admin'

export interface AuthUser {
  id: string
  name: string
  email: string
  roles: Role[]
  email_verified_at: string | null
  is_verified: boolean
  is_suspended: boolean
}

export interface LoginPayload {
  email: string
  password: string
}
