import { Routes, Route } from 'react-router-dom'
import { Card } from '@/components'
import { OnboardingWizard } from '@/features/freelance/onboarding/components/OnboardingWizard'

function FreelancerHome() {
  return (
    <div className="p-6">
      <Card>
        <h1 className="text-lg font-semibold text-gray-900">Freelancer dashboard</h1>
        <p className="mt-1 text-sm text-gray-500">Projects, proposals, and contracts go here.</p>
      </Card>
    </div>
  )
}

export function FreelancerDashboard() {
  return (
    <Routes>
      <Route index element={<FreelancerHome />} />
      <Route path="onboarding" element={<OnboardingWizard />} />
    </Routes>
  )
}
