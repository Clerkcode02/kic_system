export interface AuditLogEntry {
  id: string
  actor: { id: string; name: string } | null
  action: string
  auditable_type: string
  auditable_id: string
  before_state: Record<string, unknown> | null
  after_state: Record<string, unknown> | null
  ip_address: string | null
  user_agent: string | null
  created_at: string
}

export interface AuditLogFilters {
  actor?: string
  action?: string
  entity?: string
  date_from?: string
  date_to?: string
}

export interface CursorPage<T> {
  data: T[]
  meta: {
    next_cursor: string | null
    prev_cursor: string | null
  }
}
