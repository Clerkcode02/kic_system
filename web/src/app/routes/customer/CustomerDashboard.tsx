import { NavLink, Route, Routes } from 'react-router-dom'
import { cn } from '@/lib/cn'
import { CatalogBrowsePage, ServiceDetailView } from '@/features/catalog'
import { BookingWizard, BookingListPage, BookingDetailPage } from '@/features/booking'

const NAV_LINKS = [
  { to: '/customer/services', label: 'Browse services' },
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

export function CustomerDashboard() {
  return (
    <div className="min-h-svh bg-gray-50">
      <CustomerNav />
      <Routes>
        <Route index element={<CatalogBrowsePage />} />
        <Route path="services" element={<CatalogBrowsePage />} />
        <Route path="services/:serviceId" element={<ServiceDetailView />} />
        <Route path="book/:serviceId" element={<BookingWizard />} />
        <Route path="bookings" element={<BookingListPage />} />
        <Route path="bookings/:bookingId" element={<BookingDetailPage />} />
      </Routes>
    </div>
  )
}
