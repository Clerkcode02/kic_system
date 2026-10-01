export type ProposalStatus = 'submitted' | 'shortlisted' | 'accepted' | 'rejected' | 'withdrawn'

export const WITHDRAWABLE_STATUSES: ProposalStatus[] = ['submitted', 'shortlisted']

/** Proposals the client can still act on. Mirrors HireFreelancer's guard. */
export const HIREABLE_STATUSES: ProposalStatus[] = ['submitted', 'shortlisted']

/** Allow-list mirrored from ListProposalsForProjectQuery::SORTS. */
export type ProposalSort = 'newest' | 'amount_asc' | 'amount_desc' | 'delivery_asc' | 'rating_desc'

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
    // Cast to float server-side (ProposalResource), so a number in JSON —
    // not the string the money fields use.
    rating_avg: number
    name: string | null
  }
  created_at: string | null
  updated_at: string | null
}

/**
 * What `POST /proposals/{id}/hire` returns. Only the fields the hire flow
 * needs to navigate — the full contract is fetched by the contract feature.
 */
export interface HiredContract {
  id: string
  project_id: string
  status: string
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
