import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import toast from 'react-hot-toast'
import { normalizeApiError } from '@/lib/api/errors'
import { fetchFailedTransfers, fetchPayouts, retryFailedTransfer } from '../api/payoutApi'

const PAYOUTS_QUERY_KEY = ['admin', 'payouts'] as const
const FAILED_TRANSFERS_QUERY_KEY = ['admin', 'payouts', 'failed-transfers'] as const

export function usePayouts(cursor?: string) {
  return useQuery({
    queryKey: [...PAYOUTS_QUERY_KEY, cursor] as const,
    queryFn: () => fetchPayouts(cursor),
  })
}

export function useFailedTransfers(cursor?: string) {
  return useQuery({
    queryKey: [...FAILED_TRANSFERS_QUERY_KEY, cursor] as const,
    queryFn: () => fetchFailedTransfers(cursor),
  })
}

export function useRetryFailedTransfer() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (paymentId: string) => retryFailedTransfer(paymentId),
    onSuccess: () => {
      toast.success('Transfer retry initiated.')
      queryClient.invalidateQueries({ queryKey: FAILED_TRANSFERS_QUERY_KEY })
    },
    onError: (error) => {
      toast.error(normalizeApiError(error).message)
    },
  })
}
