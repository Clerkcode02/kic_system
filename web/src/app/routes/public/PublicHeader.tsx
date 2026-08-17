import { useState } from 'react'
import { Link, NavLink } from 'react-router-dom'
import { useAuth } from '@/app/providers/useAuth'
import type { Role } from '@/features/auth/types'

const ROLE_HOME: Record<Role, string> = {
  customer: '/customer',
  provider: '/provider',
  freelancer: '/freelancer',
  admin: '/admin',
}

const NAV_LINKS = [
  { to: '/services', label: 'Browse services' },
  { to: '/projects', label: 'Freelance projects' },
  { to: '/track', label: 'Track a booking' },
]

/**
 * One header with an anonymous state and an authenticated state — not two
 * forked layouts (SRS §6.1 / CLAUDE.md frontend rules). `isLoading` is
 * treated as "not signed in yet" rather than as a blocking state: the
 * public pages must paint before /auth/me resolves, and a 401 from it is
 * the normal anonymous case, not an error.
 */
export function PublicHeader() {
  const { user, isAuthenticated } = useAuth()
  const [isMenuOpen, setIsMenuOpen] = useState(false)

  return (
    <header className="sticky top-0 z-30 border-b border-gray-200 bg-white/95 backdrop-blur">
      <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <Link
          to="/"
          className="shrink-0 rounded text-lg font-semibold tracking-tight text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
        >
          KIC<span className="text-blue-600">.</span>
        </Link>

        <nav aria-label="Primary" className="hidden md:block">
          <ul className="flex items-center gap-6 text-sm">
            {NAV_LINKS.map((link) => (
              <li key={link.to}>
                <NavLink
                  to={link.to}
                  className={({ isActive }) =>
                    `rounded font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 ${
                      isActive ? 'text-blue-600' : 'text-gray-600 hover:text-gray-900'
                    }`
                  }
                >
                  {link.label}
                </NavLink>
              </li>
            ))}
          </ul>
        </nav>

        <div className="hidden items-center gap-3 md:flex">
          {isAuthenticated && user ? (
            <Link
              to={ROLE_HOME[user.role]}
              className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
            >
              My dashboard
            </Link>
          ) : (
            <>
              <Link
                to="/login"
                className="rounded px-2 py-2 text-sm font-medium text-gray-700 hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
              >
                Sign in
              </Link>
              <Link
                to="/services"
                className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
              >
                Book a service
              </Link>
            </>
          )}
        </div>

        <button
          type="button"
          className="rounded-md p-2 text-gray-600 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 md:hidden"
          aria-expanded={isMenuOpen}
          aria-controls="public-mobile-nav"
          onClick={() => setIsMenuOpen((open) => !open)}
        >
          <span className="sr-only">{isMenuOpen ? 'Close menu' : 'Open menu'}</span>
          <svg aria-hidden="true" viewBox="0 0 24 24" className="h-6 w-6" fill="none">
            <path
              d={isMenuOpen ? 'M6 6l12 12M18 6L6 18' : 'M4 7h16M4 12h16M4 17h16'}
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
            />
          </svg>
        </button>
      </div>

      {isMenuOpen && (
        <nav
          id="public-mobile-nav"
          aria-label="Primary"
          className="border-t border-gray-200 bg-white md:hidden"
        >
          <ul className="flex flex-col px-4 py-2 sm:px-6">
            {NAV_LINKS.map((link) => (
              <li key={link.to}>
                <NavLink
                  to={link.to}
                  onClick={() => setIsMenuOpen(false)}
                  className="block rounded py-3 text-sm font-medium text-gray-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                >
                  {link.label}
                </NavLink>
              </li>
            ))}
            <li className="border-t border-gray-100 pt-2">
              {isAuthenticated && user ? (
                <NavLink
                  to={ROLE_HOME[user.role]}
                  onClick={() => setIsMenuOpen(false)}
                  className="block rounded py-3 text-sm font-medium text-blue-600"
                >
                  My dashboard
                </NavLink>
              ) : (
                <NavLink
                  to="/login"
                  onClick={() => setIsMenuOpen(false)}
                  className="block rounded py-3 text-sm font-medium text-blue-600"
                >
                  Sign in or create an account
                </NavLink>
              )}
            </li>
          </ul>
        </nav>
      )}
    </header>
  )
}
