import { NavLink, Route, Routes } from 'react-router-dom'
import { cn } from '@/lib/cn'
import { VerificationQueuePage } from '@/features/admin/verification'
import { CategoryTreePage } from '@/features/admin/categories'
import { PlatformSettingsPage } from '@/features/admin/settings'
import { DisputeQueuePage, DisputeDetailPage } from '@/features/admin/disputes'
import { PayoutMonitorPage } from '@/features/admin/payouts'
import { AuditLogExplorerPage } from '@/features/admin/audit'
import { AnalyticsDashboardPage } from '@/features/admin/analytics'

const NAV_LINKS = [
  { to: '/admin', label: 'Analytics', end: true },
  { to: '/admin/verification', label: 'Verification' },
  { to: '/admin/categories', label: 'Categories' },
  { to: '/admin/settings', label: 'Settings' },
  { to: '/admin/disputes', label: 'Disputes' },
  { to: '/admin/payouts', label: 'Payouts' },
  { to: '/admin/audit-log', label: 'Audit Log' },
]

function AdminNav() {
  return (
    <nav className="flex gap-1 overflow-x-auto border-b border-gray-200 bg-white px-4 sm:px-6">
      {NAV_LINKS.map((link) => (
        <NavLink
          key={link.to}
          to={link.to}
          end={link.end}
          className={({ isActive }) =>
            cn(
              'whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium',
              isActive
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700',
            )
          }
        >
          {link.label}
        </NavLink>
      ))}
    </nav>
  )
}

export function AdminDashboard() {
  return (
    <div className="min-h-svh bg-gray-50">
      <AdminNav />
      <Routes>
        <Route index element={<AnalyticsDashboardPage />} />
        <Route path="verification" element={<VerificationQueuePage />} />
        <Route path="categories" element={<CategoryTreePage />} />
        <Route path="settings" element={<PlatformSettingsPage />} />
        <Route path="disputes" element={<DisputeQueuePage />} />
        <Route path="disputes/:disputeId" element={<DisputeDetailPage />} />
        <Route path="payouts" element={<PayoutMonitorPage />} />
        <Route path="audit-log" element={<AuditLogExplorerPage />} />
      </Routes>
    </div>
  )
}
