export type SkillProficiencyLevel = 'beginner' | 'intermediate' | 'expert'

export type FreelancerApprovalStatus = 'pending' | 'approved' | 'rejected'

export interface FreelancerSkill {
  id: string
  skill_name: string
  proficiency_level: SkillProficiencyLevel
}

export interface FreelancerProfile {
  id: string
  headline: string
  bio: string
  hourly_rate: number
  years_experience: number | null
  approval_status: FreelancerApprovalStatus
  skills: FreelancerSkill[]
}

export interface UpdateFreelancerProfilePayload {
  headline?: string
  bio?: string
  hourly_rate?: number
  years_experience?: number
}

export interface PortfolioItem {
  id: string
  title: string
  description: string | null
  file_path: string
  project_url: string | null
  sort_order: number
}

export interface PortfolioUploadUrl {
  path: string
  url: string
  headers: Record<string, string>
  expires_at: string
}

export interface ConfirmPortfolioItemPayload {
  title: string
  description?: string
  file_path: string
  project_url?: string
}
