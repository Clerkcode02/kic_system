import { Routes, Route } from 'react-router-dom'
import { RoleGuard } from './RoleGuard'
import { LoginPage } from './LoginPage'
import { HomePage } from './HomePage'
import { UnauthorizedPage, VerifyPendingPage, SuspendedPage, NotFoundPage } from './StatusPages'
import { CustomerDashboard } from './customer/CustomerDashboard'
import { ProviderDashboard } from './provider/ProviderDashboard'
import { FreelancerDashboard } from './freelancer/FreelancerDashboard'
import { AdminDashboard } from './admin/AdminDashboard'

export function AppRouter() {
  return (
    <Routes>
      <Route path="/" element={<HomePage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/unauthorized" element={<UnauthorizedPage />} />
      <Route path="/verify-pending" element={<VerifyPendingPage />} />
      <Route path="/suspended" element={<SuspendedPage />} />

      <Route element={<RoleGuard allowedRoles={['customer']} />}>
        <Route path="/customer/*" element={<CustomerDashboard />} />
      </Route>

      <Route
        element={<RoleGuard allowedRoles={['provider_owner', 'provider_staff']} requireVerified />}
      >
        <Route path="/provider/*" element={<ProviderDashboard />} />
      </Route>

      <Route element={<RoleGuard allowedRoles={['freelancer']} requireVerified />}>
        <Route path="/freelancer/*" element={<FreelancerDashboard />} />
      </Route>

      <Route element={<RoleGuard allowedRoles={['admin', 'super_admin']} />}>
        <Route path="/admin/*" element={<AdminDashboard />} />
      </Route>

      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  )
}
