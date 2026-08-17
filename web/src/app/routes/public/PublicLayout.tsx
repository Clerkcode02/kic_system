import { Outlet } from 'react-router-dom'
import { PublicFooter } from './PublicFooter'
import { PublicHeader } from './PublicHeader'

/**
 * The public shell (SRS §6.1). Three properties matter here and are easy to
 * break:
 *
 * 1. **It renders with no session.** Nothing below waits on `/auth/me`, and
 *    a 401 from it is the normal anonymous state, not an error to handle.
 * 2. **It never redirects to /login.** A guest completing a booking must
 *    never be bounced to a sign-in page they don't need an account for.
 * 3. **It has real landmarks.** One `<main>`, a skip link, and a single
 *    `<h1>` owned by each page.
 */
export function PublicLayout() {
  return (
    <div className="flex min-h-svh flex-col bg-white">
      <a
        href="#main"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-blue-600 focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white"
      >
        Skip to main content
      </a>

      <PublicHeader />

      <main id="main" className="flex-1">
        <Outlet />
      </main>

      <PublicFooter />
    </div>
  )
}
