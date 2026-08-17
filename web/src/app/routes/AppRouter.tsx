import { lazy, Suspense } from 'react'
import { Routes, Route } from 'react-router-dom'
import { RoleGuard } from './RoleGuard'
import { UnauthorizedPage, VerifyPendingPage, SuspendedPage, NotFoundPage } from './StatusPages'
import { PublicLayout } from './public/PublicLayout'
import { LandingPage } from './public/LandingPage'
import { TrackBookingPage } from './public/TrackBookingPage'
import { RouteFallback } from './RouteFallback'

/**
 * Everything below the landing page is code-split.
 *
 * The landing page is the platform's front door and the entry point of the
 * guest booking flow, so it has to paint fast on a phone. Without splitting,
 * a first-time visitor downloads Leaflet, FullCalendar, Stripe, Recharts and
 * all four dashboards before seeing anything — which is most of a ~1.4 MB
 * bundle they will never use.
 *
 * The wizard is split separately from the rest of the public group because
 * it is what pulls in Leaflet (LocationStep) and FullCalendar
 * (ScheduleStep); /track needs neither.
 */
// The auth pages are split too: they pull in react-hook-form and zod,
// which the landing page itself never uses.
const LoginPage = lazy(() => import('./LoginPage').then((m) => ({ default: m.LoginPage })))
const RegisterChoicePage = lazy(() =>
  import('./auth/RegisterChoicePage').then((m) => ({ default: m.RegisterChoicePage })),
)
const RegisterCustomerPage = lazy(() =>
  import('./auth/RegisterCustomerPage').then((m) => ({ default: m.RegisterCustomerPage })),
)
const RegisterBusinessPage = lazy(() =>
  import('./auth/RegisterBusinessPage').then((m) => ({ default: m.RegisterBusinessPage })),
)
const RegisterFreelancerPage = lazy(() =>
  import('./auth/RegisterFreelancerPage').then((m) => ({ default: m.RegisterFreelancerPage })),
)
const ForgotPasswordPage = lazy(() =>
  import('./auth/ForgotPasswordPage').then((m) => ({ default: m.ForgotPasswordPage })),
)
const ResetPasswordPage = lazy(() =>
  import('./auth/ResetPasswordPage').then((m) => ({ default: m.ResetPasswordPage })),
)
const VerifyEmailPage = lazy(() =>
  import('./auth/VerifyEmailPage').then((m) => ({ default: m.VerifyEmailPage })),
)

const CatalogBrowsePage = lazy(() =>
  import('@/features/catalog').then((m) => ({ default: m.CatalogBrowsePage })),
)
const ServiceDetailView = lazy(() =>
  import('@/features/catalog').then((m) => ({ default: m.ServiceDetailView })),
)
const BookingWizard = lazy(() =>
  import('@/features/booking/components/BookingWizard').then((m) => ({ default: m.BookingWizard })),
)
const CustomerDashboard = lazy(() =>
  import('./customer/CustomerDashboard').then((m) => ({ default: m.CustomerDashboard })),
)
const ProviderDashboard = lazy(() =>
  import('./provider/ProviderDashboard').then((m) => ({ default: m.ProviderDashboard })),
)
const FreelancerDashboard = lazy(() =>
  import('./freelancer/FreelancerDashboard').then((m) => ({ default: m.FreelancerDashboard })),
)
const AdminDashboard = lazy(() =>
  import('./admin/AdminDashboard').then((m) => ({ default: m.AdminDashboard })),
)

export function AppRouter() {
  return (
    <Suspense fallback={<RouteFallback />}>
      <Routes>
        {/*
          Public route group (SRS §6.1). Everything here renders with no
          session: nothing blocks first paint on /auth/me, and a 401 from it
          is the normal anonymous state rather than a redirect to /login.
          A guest can go landing → wizard → booking → track without ever
          seeing a sign-in page.
        */}
        <Route element={<PublicLayout />}>
          <Route path="/" element={<LandingPage />} />
          <Route path="/services" element={<CatalogBrowsePage />} />
          <Route path="/services/:serviceId" element={<ServiceDetailView />} />
          <Route path="/book/:serviceId" element={<BookingWizard />} />
          <Route path="/track" element={<TrackBookingPage />} />
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterChoicePage />} />
          <Route path="/register/customer" element={<RegisterCustomerPage />} />
          <Route path="/register/business" element={<RegisterBusinessPage />} />
          <Route path="/register/freelancer" element={<RegisterFreelancerPage />} />
        </Route>

        <Route path="/forgot-password" element={<ForgotPasswordPage />} />
        <Route path="/reset-password" element={<ResetPasswordPage />} />
        <Route path="/verify-email/:id/:hash" element={<VerifyEmailPage />} />
        <Route path="/unauthorized" element={<UnauthorizedPage />} />
        <Route path="/verify-pending" element={<VerifyPendingPage />} />
        <Route path="/suspended" element={<SuspendedPage />} />

        <Route element={<RoleGuard allowedRoles={['customer']} />}>
          <Route path="/customer/*" element={<CustomerDashboard />} />
        </Route>

        <Route element={<RoleGuard allowedRoles={['provider']} requireVerified />}>
          <Route path="/provider/*" element={<ProviderDashboard />} />
        </Route>

        <Route element={<RoleGuard allowedRoles={['freelancer']} requireVerified />}>
          <Route path="/freelancer/*" element={<FreelancerDashboard />} />
        </Route>

        <Route element={<RoleGuard allowedRoles={['admin']} />}>
          <Route path="/admin/*" element={<AdminDashboard />} />
        </Route>

        <Route path="*" element={<NotFoundPage />} />
      </Routes>
    </Suspense>
  )
}
