import { Routes, Route } from 'react-router-dom'
import { RoleGuard } from './RoleGuard'
import { LoginPage } from './LoginPage'
import { HomePage } from './HomePage'
import { UnauthorizedPage, VerifyPendingPage, SuspendedPage, NotFoundPage } from './StatusPages'
import { RegisterChoicePage } from './auth/RegisterChoicePage'
import { RegisterCustomerPage } from './auth/RegisterCustomerPage'
import { RegisterBusinessPage } from './auth/RegisterBusinessPage'
import { RegisterFreelancerPage } from './auth/RegisterFreelancerPage'
import { ForgotPasswordPage } from './auth/ForgotPasswordPage'
import { ResetPasswordPage } from './auth/ResetPasswordPage'
import { VerifyEmailPage } from './auth/VerifyEmailPage'
import { CustomerDashboard } from './customer/CustomerDashboard'
import { ProviderDashboard } from './provider/ProviderDashboard'
import { FreelancerDashboard } from './freelancer/FreelancerDashboard'
import { AdminDashboard } from './admin/AdminDashboard'

export function AppRouter() {
  return (
    <Routes>
      <Route path="/" element={<HomePage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterChoicePage />} />
      <Route path="/register/customer" element={<RegisterCustomerPage />} />
      <Route path="/register/business" element={<RegisterBusinessPage />} />
      <Route path="/register/freelancer" element={<RegisterFreelancerPage />} />
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
  )
}
