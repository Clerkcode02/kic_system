import { apiClient } from '@/lib/api'
import type { AuditLogEntry, AuditLogFilters, CursorPage } from '../types'

export async function fetchAuditLogs(
  filters: AuditLogFilters & { cursor?: string },
): Promise<CursorPage<AuditLogEntry>> {
  const { data } = await apiClient.get<CursorPage<AuditLogEntry>>('/audit-logs', {
    params: filters,
  })
  return data
}
