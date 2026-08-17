import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  assignDispute,
  fetchDispute,
  fetchDisputes,
  issueRefund,
  resolveDispute,
} from '../api/disputeApi'
import type { IssueRefundPayload, ResolveDisputePayload } from '../types'

const DISPUTES_QUERY_KEY = ['admin', 'disputes'] as const

export function disputeQueryKey(disputeId: string) {
  return ['admin', 'dispute', disputeId] as const
}

export function useDisputes(cursor?: string) {
  return useQuery({
    queryKey: [...DISPUTES_QUERY_KEY, cursor] as const,
    queryFn: () => fetchDisputes(cursor),
  })
}

export function useDispute(disputeId: string | undefined) {
  return useQuery({
    queryKey: disputeQueryKey(disputeId as string),
    queryFn: () => fetchDispute(disputeId as string),
    enabled: Boolean(disputeId),
  })
}

export function useResolveDispute(disputeId: string | undefined) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: ResolveDisputePayload) => resolveDispute(disputeId as string, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: DISPUTES_QUERY_KEY })
      if (disputeId) {
        queryClient.invalidateQueries({ queryKey: disputeQueryKey(disputeId) })
      }
    },
  })
}

export function useAssignDispute(disputeId: string | undefined) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (adminId: string) => assignDispute(disputeId as string, adminId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: DISPUTES_QUERY_KEY })
      if (disputeId) {
        queryClient.invalidateQueries({ queryKey: disputeQueryKey(disputeId) })
      }
    },
  })
}

export function useIssueRefund() {
  return useMutation({
    mutationFn: ({ paymentId, payload }: { paymentId: string; payload: IssueRefundPayload }) =>
      issueRefund(paymentId, payload),
  })
}
