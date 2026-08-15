import { NavLink, Route, Routes } from 'react-router-dom'
import { cn } from '@/lib/cn'
import { OnboardingEntry } from '@/features/business/components/OnboardingWizard'
import { PendingVerificationScreen } from '@/features/business/components/PendingVerificationScreen'
import { ProviderDashboardHome } from '@/features/provider/dashboard'
import { ProviderBookingListPage, ProviderBookingDetailPage } from '@/features/provider/bookings'
import { ServiceListPage, ServiceFormPage } from '@/features/provider/catalog'
import { AvailabilityEditorPage } from '@/features/provider/availability'
import { EarningsPage } from '@/features/provider/earnings'

const NAV_LINKS = [
  { to: '/provider', label: 'Dashboard', end: true },
  { to: '/provider/bookings', label: 'Bookings' },
  { to: '/provider/services', label: 'Services' },
  { to: '/provider/availability', label: 'Availability' },
  { to: '/provider/earnings', label: 'Earnings' },
]

function ProviderNav() {
  return (
    <nav className="flex gap-1 border-b border-gray-200 bg-white px-4 sm:px-6">
      {NAV_LINKS.map((link) => (
        <NavLink
          key={link.to}
          to={link.to}
          end={link.end}
          className={({ isActive }) =>
            cn(
              'border-b-2 px-3 py-3 text-sm font-medium',
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

export function ProviderDashboard() {
  return (
    <div className="min-h-svh bg-gray-50">
      <ProviderNav />
      <Routes>
        <Route index element={<ProviderDashboardHome />} />
        <Route path="onboarding" element={<OnboardingEntry />} />
        <Route path="pending" element={<PendingVerificationScreen />} />
        <Route path="bookings" element={<ProviderBookingListPage />} />
        <Route path="bookings/:bookingId" element={<ProviderBookingDetailPage />} />
        <Route path="services" element={<ServiceListPage />} />
        <Route path="services/new" element={<ServiceFormPage />} />
        <Route path="services/:serviceId/edit" element={<ServiceFormPage />} />
        <Route path="availability" element={<AvailabilityEditorPage />} />
        <Route path="earnings" element={<EarningsPage />} />
      </Routes>
    </div>
  )
}
