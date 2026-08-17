import { Navigate, NavLink, Route, Routes, useParams } from 'react-router-dom'
import { cn } from '@/lib/cn'
import { BookingListPage, BookingDetailPage } from '@/features/booking'
import { ContractDetailPage } from '@/features/freelance/contract'

const NAV_LINKS = [
  // Browsing and booking live on the public routes for everyone (SRS §6.1)
  // — the catalog and the wizard are actor-agnostic, so a signed-in
  // customer and a guest use the same pages. Duplicating them under
  // /customer/* would mean two URLs for one screen and links that break
  // depending on who follows them.
  { to: '/services', label: 'Browse services' },
  { to: '/customer/bookings', label: 'My bookings' },
]

function CustomerNav() {
  return (
    <nav className="flex gap-1 border-b border-gray-200 bg-white px-4 sm:px-6">
      {NAV_LINKS.map((link) => (
        <NavLink
          key={link.to}
          to={link.to}
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

function LegacyServiceRedirect() {
  const { serviceId } = useParams<{ serviceId: string }>()
  return <Navigate to={`/services/${serviceId}`} replace />
}

function LegacyBookRedirect() {
  const { serviceId } = useParams<{ serviceId: string }>()
  return <Navigate to={`/book/${serviceId}`} replace />
}

export function CustomerDashboard() {
  return (
    <div className="min-h-svh bg-gray-50">
      <CustomerNav />
      <Routes>
        <Route index element={<BookingListPage />} />
        {/* Legacy dashboard URLs kept as redirects so existing links and
            bookmarks keep working after browsing moved to the public group. */}
        <Route path="services" element={<Navigate to="/services" replace />} />
        <Route path="services/:serviceId" element={<LegacyServiceRedirect />} />
        <Route path="book/:serviceId" element={<LegacyBookRedirect />} />
        <Route path="bookings" element={<BookingListPage />} />
        <Route path="bookings/:bookingId" element={<BookingDetailPage />} />
        {/* No customer-facing project/contract list exists yet (CLAUDE.md §11 — no such
            endpoint to build a list from); this detail route exists so a contract link
            from elsewhere (e.g. a future project page) resolves for the client role. */}
        <Route path="contracts/:contractId" element={<ContractDetailPage />} />
      </Routes>
    </div>
  )
}
