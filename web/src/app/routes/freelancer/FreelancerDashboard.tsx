import { Navigate, NavLink, Route, Routes, useParams } from 'react-router-dom'
import { cn } from '@/lib/cn'
import { LogoutButton } from '@/components/LogoutButton'
import { OnboardingWizard } from '@/features/freelance/onboarding/components/OnboardingWizard'
import { FreelancerDashboardHome } from '@/features/freelance/dashboard'
import { MyProposalsPage } from '@/features/freelance/proposal'
import { ContractDetailPage, ContractListPage } from '@/features/freelance/contract'
import { EarningsPage } from '@/features/freelance/earnings'

const NAV_LINKS = [
  { to: '/freelancer', label: 'Dashboard', end: true },
  // Project browsing lives on the public routes for everyone, the same way
  // service browsing does for customers (see CustomerDashboard): the pages
  // are actor-agnostic, so duplicating them under /freelancer/* would mean
  // two URLs for one screen.
  { to: '/projects', label: 'Browse projects' },
  { to: '/freelancer/proposals', label: 'My proposals' },
  { to: '/freelancer/contracts', label: 'Contracts' },
  { to: '/freelancer/earnings', label: 'Earnings' },
]

function LegacyProjectRedirect() {
  const { projectId } = useParams<{ projectId: string }>()
  return <Navigate to={`/projects/${projectId}`} replace />
}

function FreelancerNav() {
  return (
    <nav className="flex items-center justify-between gap-1 border-b border-docket-line bg-docket-well px-4 sm:px-6">
      <div className="flex items-center gap-1 overflow-x-auto">
        <span className="mr-4 shrink-0 py-3 font-mono text-xs font-bold uppercase tracking-widest text-docket-soft">
          Docket
        </span>
        {NAV_LINKS.map((link) => (
          <NavLink
            key={link.to}
            to={link.to}
            end={link.end}
            className={({ isActive }) =>
              cn(
                'shrink-0 border-b-[3px] px-3 py-3 text-sm font-semibold transition-colors',
                isActive
                  ? 'border-action text-action'
                  : 'border-transparent text-docket-soft hover:text-docket-ink',
              )
            }
          >
            {link.label}
          </NavLink>
        ))}
      </div>
      <LogoutButton className="text-docket-soft hover:text-docket-ink focus-visible:outline-action" />
    </nav>
  )
}

export function FreelancerDashboard() {
  return (
    <div className="docket-scope min-h-svh bg-docket-paper">
      <FreelancerNav />
      <Routes>
        <Route index element={<FreelancerDashboardHome />} />
        <Route path="onboarding" element={<OnboardingWizard />} />
        {/* Legacy dashboard URLs kept as redirects so existing links and
            bookmarks keep working now that browsing is public. */}
        <Route path="projects" element={<Navigate to="/projects" replace />} />
        <Route path="projects/:projectId" element={<LegacyProjectRedirect />} />
        <Route path="proposals" element={<MyProposalsPage />} />
        <Route path="contracts" element={<ContractListPage />} />
        <Route path="contracts/:contractId" element={<ContractDetailPage />} />
        <Route path="earnings" element={<EarningsPage />} />
      </Routes>
    </div>
  )
}
