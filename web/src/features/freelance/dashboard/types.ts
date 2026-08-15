export type MilestoneStatus = 'pending' | 'submitted' | 'approved' | 'paid' | 'disputed'

export interface AttentionMilestone {
  id: string
  contract_id: string
  title: string
  amount: string
  currency: string
  due_date: string
  status: MilestoneStatus
  rejection_reason: string | null
}

export interface FreelancerDashboardSummary {
  open_proposal_count: number
  active_contract_count: number
  attention_milestones: AttentionMilestone[]
  earnings: { total: string; currency: 'CAD' }
}
