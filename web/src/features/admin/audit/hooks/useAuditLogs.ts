import { useQuery } from '@tanstack/react-query'
import { fetchAuditLogs } from '../api/auditLogApi'
import type { AuditLogFilters } from '../types'

export function useAuditLogs(filters: AuditLogFilters, cursor: string | undefined) {
  return useQuery({
    queryKey: ['admin', 'audit-logs', filters, cursor] as const,
    queryFn: () => fetchAuditLogs({ ...filters, cursor }),
    placeholderData: (previous) => previous,
  })
}
