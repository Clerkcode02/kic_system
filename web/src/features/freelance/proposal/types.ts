export type ProposalStatus = 'submitted' | 'shortlisted' | 'accepted' | 'rejected' | 'withdrawn'

export const WITHDRAWABLE_STATUSES: ProposalStatus[] = ['submitted', 'shortlisted']

export interface ProposalListItem {
  id: string
  project_id: string
  proposed_amount: string
  currency: string
  delivery_days: number
  status: ProposalStatus
  project?: { id: string; title: string; status: string }
  created_at: string | null
}

export interface Proposal {
  id: string
  project_id: string
  proposed_amount: string
  currency: string
  cover_letter: string
  delivery_days: number
  status: ProposalStatus
  freelancer: {
    id: string
    user_id: string
    headline: string
    rating_avg: string
    name: string | null
  }
  created_at: string | null
  updated_at: string | null
}

export interface SubmitProposalPayload {
  proposed_amount: number
  cover_letter: string
  delivery_days: number
}

export interface CursorPage<T> {
  data: T[]
  meta: {
    next_cursor: string | null
    prev_cursor: string | null
  }
}
