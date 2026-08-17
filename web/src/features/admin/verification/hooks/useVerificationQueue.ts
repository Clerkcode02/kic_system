import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  approveBusinessVerification,
  approveFreelancerVerification,
  bulkApproveBusinessVerifications,
  bulkApproveFreelancerVerifications,
  bulkRejectBusinessVerifications,
  bulkRejectFreelancerVerifications,
  fetchBusinessDocumentUrl,
  fetchBusinessVerification,
  fetchBusinessVerificationQueue,
  fetchFreelancerVerification,
  fetchFreelancerVerificationQueue,
  fetchPortfolioItemUrl,
  rejectBusinessVerification,
  rejectFreelancerVerification,
} from '../api/verificationApi'

const BUSINESS_QUEUE_KEY = ['admin', 'verification', 'business', 'queue'] as const
const FREELANCER_QUEUE_KEY = ['admin', 'verification', 'freelancer', 'queue'] as const

// ---- Business ----------------------------------------------------------

export function useBusinessVerificationQueue(status: string, cursor: string | undefined) {
  return useQuery({
    queryKey: [...BUSINESS_QUEUE_KEY, status, cursor] as const,
    queryFn: () => fetchBusinessVerificationQueue(status, cursor),
  })
}

export function useBusinessVerification(businessId: string | undefined) {
  return useQuery({
    queryKey: ['admin', 'verification', 'business', businessId] as const,
    queryFn: () => fetchBusinessVerification(businessId as string),
    enabled: Boolean(businessId),
  })
}

export function useApproveBusinessVerification() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (businessId: string) => approveBusinessVerification(businessId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: BUSINESS_QUEUE_KEY })
    },
  })
}

export function useRejectBusinessVerification() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ businessId, reason }: { businessId: string; reason: string }) =>
      rejectBusinessVerification(businessId, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: BUSINESS_QUEUE_KEY })
    },
  })
}

export function useBulkApproveBusinessVerifications() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (ids: string[]) => bulkApproveBusinessVerifications(ids),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: BUSINESS_QUEUE_KEY })
    },
  })
}

export function useBulkRejectBusinessVerifications() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ ids, reason }: { ids: string[]; reason: string }) =>
      bulkRejectBusinessVerifications(ids, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: BUSINESS_QUEUE_KEY })
    },
  })
}

// ---- Freelancer ----------------------------------------------------------

export function useFreelancerVerificationQueue(status: string, cursor: string | undefined) {
  return useQuery({
    queryKey: [...FREELANCER_QUEUE_KEY, status, cursor] as const,
    queryFn: () => fetchFreelancerVerificationQueue(status, cursor),
  })
}

export function useFreelancerVerification(freelancerId: string | undefined) {
  return useQuery({
    queryKey: ['admin', 'verification', 'freelancer', freelancerId] as const,
    queryFn: () => fetchFreelancerVerification(freelancerId as string),
    enabled: Boolean(freelancerId),
  })
}

export function useApproveFreelancerVerification() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (freelancerId: string) => approveFreelancerVerification(freelancerId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: FREELANCER_QUEUE_KEY })
    },
  })
}

export function useRejectFreelancerVerification() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ freelancerId, reason }: { freelancerId: string; reason: string }) =>
      rejectFreelancerVerification(freelancerId, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: FREELANCER_QUEUE_KEY })
    },
  })
}

export function useBulkApproveFreelancerVerifications() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (ids: string[]) => bulkApproveFreelancerVerifications(ids),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: FREELANCER_QUEUE_KEY })
    },
  })
}

export function useBulkRejectFreelancerVerifications() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ ids, reason }: { ids: string[]; reason: string }) =>
      bulkRejectFreelancerVerifications(ids, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: FREELANCER_QUEUE_KEY })
    },
  })
}

// ---- Signed document URLs (fetched on demand, never prefetched) --------

export async function getBusinessDocumentUrl(documentId: string): Promise<string> {
  const { url } = await fetchBusinessDocumentUrl(documentId)
  return url
}

export async function getPortfolioItemUrl(portfolioItemId: string): Promise<string> {
  const { url } = await fetchPortfolioItemUrl(portfolioItemId)
  return url
}
