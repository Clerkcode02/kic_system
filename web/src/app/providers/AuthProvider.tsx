import { useCallback, useEffect, useMemo, type ReactNode } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import {
  fetchCurrentUser,
  login as loginRequest,
  logout as logoutRequest,
} from '@/features/auth/api/authApi'
import type { AuthUser } from '@/features/auth/types'
import { onUnauthorized } from '@/lib/api'
import { AuthContext, type AuthContextValue } from './auth-context'

const ME_QUERY_KEY = ['auth', 'me'] as const

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()

  const { data: user, isLoading } = useQuery({
    queryKey: ME_QUERY_KEY,
    queryFn: fetchCurrentUser,
    retry: false,
    staleTime: 5 * 60 * 1000,
  })

  const clearSession = useCallback(() => {
    queryClient.setQueryData<AuthUser | null>(ME_QUERY_KEY, null)
  }, [queryClient])

  useEffect(() => {
    onUnauthorized(clearSession)
  }, [clearSession])

  const login = useCallback(
    async (payload: Parameters<AuthContextValue['login']>[0]) => {
      const authedUser = await loginRequest(payload)
      queryClient.setQueryData<AuthUser>(ME_QUERY_KEY, authedUser)
      return authedUser
    },
    [queryClient],
  )

  const logout = useCallback(async () => {
    await logoutRequest()
    clearSession()
  }, [clearSession])

  const value = useMemo<AuthContextValue>(
    () => ({
      user: user ?? null,
      isLoading,
      isAuthenticated: Boolean(user),
      login,
      logout,
    }),
    [user, isLoading, login, logout],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
