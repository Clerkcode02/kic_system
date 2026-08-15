import { apiClient } from '@/lib/api'
import type { CursorPage, Payout, StripeConnectStatus } from '../types'

export async function fetchPayouts(cursor: string | undefined): Promise<CursorPage<Payout>> {
  const { data } = await apiClient.get<CursorPage<Payout>>('/provider/me/earnings', {
    params: { cursor },
  })
  return data
}

export async function fetchStripeConnectStatus(): Promise<StripeConnectStatus> {
  const { data } = await apiClient.get<{ data: StripeConnectStatus }>('/provider/me/stripe/status')
  return data.data
}

export async function createStripeOnboardingLink(): Promise<string> {
  const { data } = await apiClient.post<{ data: { url: string } }>(
    '/provider/me/stripe/onboarding-link',
  )
  return data.data.url
}
