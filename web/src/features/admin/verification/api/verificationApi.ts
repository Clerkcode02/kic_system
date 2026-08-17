import { apiClient } from '@/lib/api'
import type {
  BulkActionResult,
  BusinessVerification,
  CursorPage,
  FreelancerVerification,
  SignedUrl,
} from '../types'

// ---- Business verification -------------------------------------------------

export async function fetchBusinessVerificationQueue(
  status: string,
  cursor?: string,
): Promise<CursorPage<BusinessVerification>> {
  const { data } = await apiClient.get<CursorPage<BusinessVerification>>(
    '/admin/businesses/verification-queue',
    { params: { status, cursor } },
  )
  return data
}

export async function fetchBusinessVerification(businessId: string): Promise<BusinessVerification> {
  const { data } = await apiClient.get<{ data: BusinessVerification }>(
    `/admin/businesses/${businessId}/verification`,
  )
  return data.data
}

export async function approveBusinessVerification(businessId: string): Promise<BusinessVerification> {
  const { data } = await apiClient.post<{ data: BusinessVerification }>(
    `/admin/businesses/${businessId}/verification/approve`,
  )
  return data.data
}

export async function rejectBusinessVerification(
  businessId: string,
  reason: string,
): Promise<BusinessVerification> {
  const { data } = await apiClient.post<{ data: BusinessVerification }>(
    `/admin/businesses/${businessId}/verification/reject`,
    { reason },
  )
  return data.data
}

export async function bulkApproveBusinessVerifications(ids: string[]): Promise<BulkActionResult> {
  const { data } = await apiClient.post<{ data: BulkActionResult }>(
    '/admin/businesses/verification/bulk-approve',
    { ids },
  )
  return data.data
}

export async function bulkRejectBusinessVerifications(
  ids: string[],
  reason: string,
): Promise<BulkActionResult> {
  const { data } = await apiClient.post<{ data: BulkActionResult }>(
    '/admin/businesses/verification/bulk-reject',
    { ids, reason },
  )
  return data.data
}

export async function fetchBusinessDocumentUrl(documentId: string): Promise<SignedUrl> {
  const { data } = await apiClient.get<{ data: SignedUrl }>(
    `/admin/businesses/documents/${documentId}/url`,
  )
  return data.data
}

// ---- Freelancer verification ------------------------------------------------

export async function fetchFreelancerVerificationQueue(
  status: string,
  cursor?: string,
): Promise<CursorPage<FreelancerVerification>> {
  const { data } = await apiClient.get<CursorPage<FreelancerVerification>>(
    '/admin/freelancers/verification-queue',
    { params: { status, cursor } },
  )
  return data
}

export async function fetchFreelancerVerification(
  freelancerId: string,
): Promise<FreelancerVerification> {
  const { data } = await apiClient.get<{ data: FreelancerVerification }>(
    `/admin/freelancers/${freelancerId}/verification`,
  )
  return data.data
}

export async function approveFreelancerVerification(
  freelancerId: string,
): Promise<FreelancerVerification> {
  const { data } = await apiClient.post<{ data: FreelancerVerification }>(
    `/admin/freelancers/${freelancerId}/verification/approve`,
  )
  return data.data
}

export async function rejectFreelancerVerification(
  freelancerId: string,
  reason: string,
): Promise<FreelancerVerification> {
  const { data } = await apiClient.post<{ data: FreelancerVerification }>(
    `/admin/freelancers/${freelancerId}/verification/reject`,
    { reason },
  )
  return data.data
}

export async function bulkApproveFreelancerVerifications(ids: string[]): Promise<BulkActionResult> {
  const { data } = await apiClient.post<{ data: BulkActionResult }>(
    '/admin/freelancers/verification/bulk-approve',
    { ids },
  )
  return data.data
}

export async function bulkRejectFreelancerVerifications(
  ids: string[],
  reason: string,
): Promise<BulkActionResult> {
  const { data } = await apiClient.post<{ data: BulkActionResult }>(
    '/admin/freelancers/verification/bulk-reject',
    { ids, reason },
  )
  return data.data
}

export async function fetchPortfolioItemUrl(portfolioItemId: string): Promise<SignedUrl> {
  const { data } = await apiClient.get<{ data: SignedUrl }>(
    `/admin/freelancers/portfolio-items/${portfolioItemId}/url`,
  )
  return data.data
}
