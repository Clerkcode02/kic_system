import { Routes, Route } from 'react-router-dom'
import { Card } from '@/components'
import { OnboardingEntry } from '@/features/business/components/OnboardingWizard'
import { PendingVerificationScreen } from '@/features/business/components/PendingVerificationScreen'

function ProviderHome() {
  return (
    <div className="p-6">
      <Card>
        <h1 className="text-lg font-semibold text-gray-900">Provider dashboard</h1>
        <p className="mt-1 text-sm text-gray-500">Booking requests and quotations go here.</p>
      </Card>
    </div>
  )
}

export function ProviderDashboard() {
  return (
    <Routes>
      <Route index element={<ProviderHome />} />
      <Route path="onboarding" element={<OnboardingEntry />} />
      <Route path="pending" element={<PendingVerificationScreen />} />
    </Routes>
  )
}
