import { Link } from 'react-router-dom'

const COLUMNS = [
  {
    heading: 'Customers',
    links: [
      { to: '/services', label: 'Browse services' },
      { to: '/track', label: 'Track a booking' },
      { to: '/register/customer', label: 'Create an account' },
    ],
  },
  {
    heading: 'Providers',
    links: [
      { to: '/register/business', label: 'List your business' },
      { to: '/login', label: 'Provider sign in' },
    ],
  },
  {
    heading: 'Freelancers',
    links: [
      { to: '/projects', label: 'Find projects' },
      { to: '/register/freelancer', label: 'Join as a freelancer' },
    ],
  },
]

export function PublicFooter() {
  return (
    <footer className="border-t border-gray-200 bg-gray-50">
      <div className="mx-auto w-full max-w-6xl px-4 py-10 sm:px-6">
        <div className="grid gap-8 sm:grid-cols-2 md:grid-cols-4">
          <div>
            <p className="text-lg font-semibold tracking-tight text-gray-900">
              KIC<span className="text-blue-600">.</span>
            </p>
            <p className="mt-2 max-w-xs text-sm text-gray-600">
              Verified local service providers and freelance talent, across Canada.
            </p>
          </div>

          {COLUMNS.map((column) => (
            <nav key={column.heading} aria-labelledby={`footer-${column.heading}`}>
              <h2
                id={`footer-${column.heading}`}
                className="text-sm font-semibold text-gray-900"
              >
                {column.heading}
              </h2>
              <ul className="mt-3 flex flex-col gap-2">
                {column.links.map((link) => (
                  <li key={link.to}>
                    <Link
                      to={link.to}
                      className="rounded text-sm text-gray-600 hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                    >
                      {link.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </nav>
          ))}
        </div>

        <p className="mt-10 border-t border-gray-200 pt-6 text-sm text-gray-500">
          © {new Date().getFullYear()} KIC. Serving customers across Canada.
        </p>
      </div>
    </footer>
  )
}
