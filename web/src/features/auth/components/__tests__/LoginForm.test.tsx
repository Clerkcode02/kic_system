import { describe, expect, it, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, useLocation } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AuthProvider } from '@/app/providers/AuthProvider'
import { LoginForm } from '../LoginForm'
import type { AuthUser, Role } from '@/features/auth/types'

const login = vi.hoisted(() => vi.fn())
const fetchCurrentUser = vi.hoisted(() => vi.fn())

vi.mock('@/features/auth/api/authApi', () => ({
  login,
  fetchCurrentUser,
  logout: vi.fn(),
}))

function userWithRole(role: Role): AuthUser {
  return {
    id: '0199a1b2-c3d4-7000-8000-000000000001',
    name: 'Test person',
    email: 'test@example.com',
    phone: '+14165550123',
    role,
    status: 'active',
    email_verified_at: '2026-01-01T00:00:00Z',
    created_at: '2026-01-01T00:00:00Z',
  }
}

/** Surfaces the router's current path so the redirect can be asserted. */
function CurrentPath() {
  const { pathname, search } = useLocation()
  return <output data-testid="path">{`${pathname}${search}`}</output>
}

function renderForm(redirectTo?: string) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={['/login']}>
        <AuthProvider>
          <LoginForm redirectTo={redirectTo} />
          <CurrentPath />
        </AuthProvider>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

async function signIn() {
  await userEvent.type(screen.getByLabelText('Email'), 'test@example.com')
  await userEvent.type(screen.getByLabelText('Password'), 'password')
  await userEvent.click(screen.getByRole('button', { name: 'Sign in' }))
}

describe('LoginForm redirect', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchCurrentUser.mockRejectedValue(new Error('unauthenticated'))
  })

  it.each([
    ['customer', '/customer'],
    ['provider', '/provider'],
    ['freelancer', '/freelancer'],
    ['admin', '/admin'],
  ] as Array<[Role, string]>)('sends a %s to their own dashboard', async (role, expected) => {
    login.mockResolvedValue(userWithRole(role))
    renderForm()

    await signIn()

    await waitFor(() => expect(screen.getByTestId('path')).toHaveTextContent(expected))
  })

  it('honours an explicit redirectTo over the dashboard default', async () => {
    login.mockResolvedValue(userWithRole('customer'))
    renderForm('/track?booking=BK-250817ABC123')

    await signIn()

    await waitFor(() =>
      expect(screen.getByTestId('path')).toHaveTextContent('/track?booking=BK-250817ABC123'),
    )
  })

  it('never lands on the public landing page', async () => {
    login.mockResolvedValue(userWithRole('customer'))
    renderForm()

    await signIn()

    await waitFor(() => expect(screen.getByTestId('path')).not.toHaveTextContent('/login'))
    expect(screen.getByTestId('path').textContent).not.toBe('/')
  })
})
