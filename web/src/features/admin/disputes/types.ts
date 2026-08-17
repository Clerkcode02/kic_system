export type DisputableType = 'booking' | 'project' | 'milestone' | 'deliverable'

export type DisputeStatus = 'open' | 'under_review' | 'resolved' | 'closed'

export interface Dispute {
  id: string
  disputable_type: DisputableType
  disputable_id: string
  raised_by: string
  assigned_admin_id: string | null
  status: DisputeStatus
  resolution_notes: string | null
  created_at: string
  updated_at: string
}

export interface ResolveDisputePayload {
  resolution_notes: string
  release_escrow?: boolean
}

export interface IssueRefundPayload {
  amount?: number
  reason?: string
}

export interface Refund {
  id: string
  amount: string
  status: string
}

export interface CursorPage<T> {
  data: T[]
  meta: {
    next_cursor: string | null
    prev_cursor: string | null
  }
}
