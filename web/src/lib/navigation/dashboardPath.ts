import type { Role } from '@/features/auth/types'

/**
 * Where each role's dashboard lives. These are the role route roots from
 * AppRouter, each of which has an `index` route rendering that role's
 * dashboard home.
 */
const DASHBOARD_PATHS: Record<Role, string> = {
  customer: '/customer',
  provider: '/provider',
  freelancer: '/freelancer',
  admin: '/admin',
}

/**
 * Default post-login destination: the signed-in user's own dashboard.
 *
 * Only a fallback — an explicit, validated `?next=` (the guest → register →
 * claim detour, SRS §6.1) or a RoleGuard's saved location always wins, so
 * deep links still land where the user was headed.
 */
export function dashboardPathForRole(role: Role | null | undefined): string {
  return role ? DASHBOARD_PATHS[role] : '/'
}
  