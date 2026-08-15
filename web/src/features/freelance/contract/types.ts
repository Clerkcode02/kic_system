export type ContractStatus = 'active' | 'completed' | 'terminated'

export type MilestoneStatus = 'pending' | 'submitted' | 'approved' | 'paid' | 'disputed'

/** Statuses where the freelancer can upload deliverables and submit for approval. */
export const SUBMITTABLE_STATUSES: MilestoneStatus[] = ['pending', 'disputed']

export interface Milestone {
  id: string
  contract_id: string
  title: string
  amount: string
  currency: string
  due_date: string
  status: MilestoneStatus
  rejection_reason: string | null
  created_at: string | null
  updated_at: string | null
}

export interface ContractSummary {
  id: string
  project_id: string
  proposal_id: string
  total_amount: string
  currency: string
  status: ContractStatus
  created_at: string | null
  project?: { id: string; title: string; status: string }
  milestones?: Milestone[]
}

export type ContractDetail = ContractSummary

export interface Deliverable {
  id: string
  milestone_id: string
  mime_type: string | null
  size_bytes: number | null
  description: string | null
  submitted_at: string
  scanned: boolean
  download_url: string | null
}

export interface DeliverableUploadUrl {
  path: string
  url: string
  headers: Record<string, string>
  expires_at: string
}

export interface ConfirmDeliverablePayload {
  file_path: string
  mime_type: string
  size_bytes: number
  description?: string
}

export interface CursorPage<T> {
  data: T[]
  meta: {
    next_cursor: string | null
    prev_cursor: string | null
  }
}
