import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '@/app/providers/useAuth'
import { cn } from '@/lib/cn'

interface LogoutButtonProps {
  className?: string
}

/**
 * Shared across all four dashboard navs. No default text color — callers
 * supply one that matches their nav's palette (see CustomerNav/ProviderNav/
 * AdminNav vs. the docket-themed FreelancerNav).
 */
export function LogoutButton({ className }: LogoutButtonProps) {
  const { logout } = useAuth()
  const navigate = useNavigate()
  const [isLoggingOut, setIsLoggingOut] = useState(false)

  const handleLogout = async () => {
    setIsLoggingOut(true)
    try {
      await logout()
    } finally {
      navigate('/login', { replace: true })
    }
  }

  return (
    <button
      type="button"
      onClick={handleLogout}
      disabled={isLoggingOut}
      className={cn(
        'shrink-0 rounded px-3 py-3 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50',
        'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
        className,
      )}
    >
      Log out
    </button>
  )
}
