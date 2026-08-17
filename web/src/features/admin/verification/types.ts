export type BusinessVerificationStatus = 'pending' | 'verified' | 'rejected'

export type FreelancerApprovalStatus = 'pending' | 'approved' | 'rejected'

export interface BusinessDocument {
  id: string
  document_type: string
  issuing_authority: string
  issued_at: string
  expires_at: string
  verification_status: string
}

export interface BusinessVerification {
  id: string
  legal_name: string
  registration_number: string
  verification_status: BusinessVerificationStatus
  city: string
  province: string
  owner?: { id: string; name: string; email: string }
  documents?: BusinessDocument[]
  created_at: string
}

export interface PortfolioItem {
  id: string
  title: string
  description: string
  project_url: string
}

export interface FreelancerVerification {
  id: string
  headline: string
  bio: string
  years_experience: number
  approval_status: FreelancerApprovalStatus
  user?: { id: string; name: string; email: string }
  skills?: string[]
  portfolio_items?: PortfolioItem[]
  created_at: string
}

export interface SignedUrl {
  url: string
  expires_at: string
}

export interface BulkActionResult {
  succeeded: string[]
  failed: { id: string; reason: string }[]
}

export interface CursorPage<T> {
  data: T[]
  meta: {
    next_cursor: string | null
    prev_cursor: string | null
  }
}
