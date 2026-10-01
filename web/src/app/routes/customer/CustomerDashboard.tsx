import { Navigate, NavLink, Route, Routes, useParams } from 'react-router-dom'
import { cn } from '@/lib/cn'
import { LogoutButton } from '@/components/LogoutButton'
import { BookingListPage, BookingDetailPage } from '@/features/booking'
import { ClientContractDetailPage, ClientContractListPage } from '@/features/freelance/contract'
import {
  ClientProjectDetailPage,
  MyProjectsPage,
  PostProjectPage,
} from '@/features/freelance/project'

const NAV_LINKS = [
  // Browsing and booking live on the public routes for everyone (SRS §6.1)
  // — the catalog and the wizard are actor-agnostic, so a signed-in
  // customer and a guest use the same pages. Duplicating them under
  // /customer/* would mean two URLs for one screen and links that break
  // depending on who follows them.
  { to: '/services', label: 'Browse services' },
  { to: '/customer/bookings', label: 'My bookings' },
  // The freelance side requires an account (CLAUDE.md §1), so unlike
  // browsing services this lives under /customer/* rather than the public
  // group — there is no guest path into posting a project.
  { to: '/customer/projects', label: 'My projects' },
  { to: '/customer/contracts', label: 'My contracts' },
]

function CustomerNav() {
  return (
    <nav className="flex items-center justify-between gap-1 border-b border-gray-200 bg-white px-4 sm:px-6">
      <div className="flex gap-1 overflow-x-auto">
        {NAV_LINKS.map((link) => (
          <NavLink
            key={link.to}
            to={link.to}
            className={({ isActive }) =>
              cn(
                'shrink-0 border-b-2 px-3 py-3 text-sm font-medium',
                isActive
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700',
              )
            }
          >
            {link.label}
          </NavLink>
        ))}
      </div>
      <LogoutButton className="text-gray-500 hover:text-gray-700 focus-visible:outline-blue-600" />
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
        <Route path="projects" element={<MyProjectsPage />} />
        <Route path="projects/new" element={<PostProjectPage />} />
        <Route path="projects/:projectId" element={<ClientProjectDetailPage />} />
        <Route path="contracts" element={<ClientContractListPage />} />
        <Route path="contracts/:contractId" element={<ClientContractDetailPage />} />
      </Routes>
    </div>
  )
}
