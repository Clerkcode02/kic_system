import { describe, expect, it } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { server } from '@/test/mocks/server'
import { authenticatedAs, testCustomer } from '@/test/mocks/handlers'
import { API_BASE_URL, API_VERSION_PATH } from '@/lib/api/config'
import { renderWithProviders } from '@/test/render'
import { ProjectDetailPage } from '../ProjectDetailPage'
import type { ProjectDetail } from '../../types'

const project: ProjectDetail = {
  id: 'proj-1',
  title: 'Rebuild the marketing site',
  description: 'Five pages, responsive, CMS-backed.',
  budget_min: '2000.00',
  budget_max: '5000.00',
  currency: 'CAD',
  deadline: '2026-10-01',
  status: 'open',
  category: { id: 'cat-1', name: 'Web development', slug: 'web-development' },
  client: { id: 'user-9', name: 'Priya Raman' },
  contract: null,
  created_at: '2026-08-01T00:00:00Z',
  updated_at: '2026-08-01T00:00:00Z',
}

function renderPage() {
  server.use(
    http.get(`${API_BASE_URL}${API_VERSION_PATH}/projects/proj-1`, () =>
      HttpResponse.json({ data: project }),
    ),
  )
  return renderWithProviders(<ProjectDetailPage />, {
    route: '/projects/proj-1',
    path: '/projects/:projectId',
  })
}

describe('ProjectDetailPage', () => {
  it('renders the project for an anonymous visitor — browsing is public', async () => {
    renderPage()

    expect(await screen.findByText('Rebuild the marketing site')).toBeInTheDocument()
    expect(screen.getByText(/Five pages, responsive/)).toBeInTheDocument()
  })

  it('offers signup instead of the proposal form when nobody is signed in', async () => {
    renderPage()

    expect(await screen.findByRole('link', { name: 'Join as a freelancer' })).toHaveAttribute(
      'href',
      '/register/freelancer',
    )
    // `?next=` so signing in returns them to the project they were reading.
    expect(screen.getByRole('link', { name: 'Sign in' })).toHaveAttribute(
      'href',
      '/login?next=%2Fprojects%2Fproj-1',
    )
    expect(screen.queryByRole('heading', { name: 'Submit a proposal' })).not.toBeInTheDocument()
  })

  it('does not offer the proposal form to a signed-in customer', async () => {
    server.use(authenticatedAs(testCustomer))
    renderPage()

    expect(
      await screen.findByText(/Only freelancer accounts can submit proposals/),
    ).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Submit a proposal' })).not.toBeInTheDocument()
  })

  it('renders the proposal form for a signed-in freelancer', async () => {
    server.use(authenticatedAs({ ...testCustomer, role: 'freelancer' }))
    renderPage()

    await waitFor(() =>
      expect(screen.getByRole('heading', { name: 'Submit a proposal' })).toBeInTheDocument(),
    )
    expect(screen.queryByRole('link', { name: 'Join as a freelancer' })).not.toBeInTheDocument()
  })
})
