import { NavLink, Route, Routes } from 'react-router-dom'
import { cn } from '@/lib/cn'
import { OnboardingWizard } from '@/features/freelance/onboarding/components/OnboardingWizard'
import { FreelancerDashboardHome } from '@/features/freelance/dashboard'
import { ProjectBrowsePage, ProjectDetailPage } from '@/features/freelance/project'
import { MyProposalsPage } from '@/features/freelance/proposal'
import { ContractDetailPage, ContractListPage } from '@/features/freelance/contract'
import { EarningsPage } from '@/features/freelance/earnings'

const NAV_LINKS = [
  { to: '/freelancer', label: 'Dashboard', end: true },
  { to: '/freelancer/projects', label: 'Browse projects' },
  { to: '/freelancer/proposals', label: 'My proposals' },
  { to: '/freelancer/contracts', label: 'Contracts' },
  { to: '/freelancer/earnings', label: 'Earnings' },
]

function FreelancerNav() {
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

export function FreelancerDashboard() {
  return (
    <div className="min-h-svh bg-gray-50">
      <FreelancerNav />
      <Routes>
        <Route index element={<FreelancerDashboardHome />} />
        <Route path="onboarding" element={<OnboardingWizard />} />
        <Route path="projects" element={<ProjectBrowsePage />} />
        <Route path="projects/:projectId" element={<ProjectDetailPage />} />
        <Route path="proposals" element={<MyProposalsPage />} />
        <Route path="contracts" element={<ContractListPage />} />
        <Route path="contracts/:contractId" element={<ContractDetailPage />} />
        <Route path="earnings" element={<EarningsPage />} />
      </Routes>
    </div>
  )
}
