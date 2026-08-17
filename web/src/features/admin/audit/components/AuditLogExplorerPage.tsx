import { useState } from 'react'
import { Button, Card, EmptyState, Input, Pagination, Skeleton, type TableColumn } from '@/components'
import { useAuditLogs } from '../hooks/useAuditLogs'
import type { AuditLogEntry, AuditLogFilters } from '../types'

const EMPTY_FILTERS: AuditLogFilters = {}

function formatJson(value: Record<string, unknown> | null): string {
  return value === null ? '—' : JSON.stringify(value, null, 2)
}

export function AuditLogExplorerPage() {
  const [filters, setFilters] = useState<AuditLogFilters>(EMPTY_FILTERS)
  const [cursorStack, setCursorStack] = useState<(string | undefined)[]>([undefined])
  const [expandedId, setExpandedId] = useState<string | null>(null)
  const cursor = cursorStack[cursorStack.length - 1]

  const { data, isLoading, isError } = useAuditLogs(filters, cursor)

  const updateFilter = (key: keyof AuditLogFilters, value: string) => {
    setFilters((prev) => ({ ...prev, [key]: value || undefined }))
    setCursorStack([undefined])
    setExpandedId(null)
  }

  const clearFilters = () => {
    setFilters(EMPTY_FILTERS)
    setCursorStack([undefined])
    setExpandedId(null)
  }

  const hasFilters = Object.values(filters).some((value) => Boolean(value))

  const handleNext = () => {
    if (data?.meta.next_cursor) setCursorStack((stack) => [...stack, data.meta.next_cursor!])
  }
  const handlePrevious = () => {
    setCursorStack((stack) => (stack.length > 1 ? stack.slice(0, -1) : stack))
  }

  const columns: TableColumn<AuditLogEntry>[] = [
    {
      key: 'created_at',
      header: 'Date',
      render: (entry) => new Date(entry.created_at).toLocaleString(),
    },
    {
      key: 'actor',
      header: 'Actor',
      render: (entry) => entry.actor?.name ?? 'System',
    },
    {
      key: 'action',
      header: 'Action',
      render: (entry) => entry.action,
    },
    {
      key: 'auditable',
      header: 'Entity',
      render: (entry) => `${entry.auditable_type} · ${entry.auditable_id}`,
    },
    {
      key: 'expand',
      header: '',
      render: (entry) => (
        <Button
          variant="ghost"
          size="sm"
          onClick={() => setExpandedId((current) => (current === entry.id ? null : entry.id))}
        >
          {expandedId === entry.id ? 'Collapse' : 'Expand'}
        </Button>
      ),
    },
  ]

  const rows = data?.data ?? []

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <h1 className="text-lg font-semibold text-gray-900">Audit Log</h1>

      <Card>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
          <Input
            label="Actor ID"
            placeholder="user uuid"
            value={filters.actor ?? ''}
            onChange={(e) => updateFilter('actor', e.target.value)}
          />
          <Input
            label="Action"
            placeholder="e.g. booking.status_changed"
            value={filters.action ?? ''}
            onChange={(e) => updateFilter('action', e.target.value)}
          />
          <Input
            label="Entity type"
            placeholder="e.g. booking"
            value={filters.entity ?? ''}
            onChange={(e) => updateFilter('entity', e.target.value)}
          />
          <Input
            label="From"
            type="date"
            value={filters.date_from ?? ''}
            onChange={(e) => updateFilter('date_from', e.target.value)}
          />
          <Input
            label="To"
            type="date"
            value={filters.date_to ?? ''}
            onChange={(e) => updateFilter('date_to', e.target.value)}
          />
        </div>
        <div className="mt-4 flex justify-end">
          <Button variant="secondary" size="sm" onClick={clearFilters} disabled={!hasFilters}>
            Clear filters
          </Button>
        </div>
      </Card>

      {isLoading && !data ? (
        <div className="flex flex-col gap-2">
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
        </div>
      ) : isError ? (
        <EmptyState title="Failed to load audit log" description="Please try again." />
      ) : (
        <>
          <div className="overflow-x-auto rounded-lg border border-gray-200">
            <table className="min-w-full divide-y divide-gray-200 text-sm">
              <thead className="bg-gray-50">
                <tr>
                  {columns.map((column) => (
                    <th key={column.key} scope="col" className="px-4 py-3 text-left font-medium text-gray-500">
                      {column.header}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 bg-white">
                {rows.length === 0 ? (
                  <tr>
                    <td colSpan={columns.length} className="px-4 py-8 text-center text-gray-500">
                      No audit log entries found.
                    </td>
                  </tr>
                ) : (
                  rows.map((entry) => (
                    <>
                      <tr key={entry.id}>
                        {columns.map((column) => (
                          <td key={column.key} className="px-4 py-3 text-gray-900">
                            {column.render(entry)}
                          </td>
                        ))}
                      </tr>
                      {expandedId === entry.id && (
                        <tr key={`${entry.id}-detail`}>
                          <td colSpan={columns.length} className="bg-gray-50 px-4 py-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                              <div>
                                <p className="mb-1 text-xs font-medium uppercase text-gray-500">
                                  Before
                                </p>
                                <pre className="max-h-64 overflow-auto rounded-md bg-white p-3 text-xs text-gray-800 shadow-sm">
                                  {formatJson(entry.before_state)}
                                </pre>
                              </div>
                              <div>
                                <p className="mb-1 text-xs font-medium uppercase text-gray-500">
                                  After
                                </p>
                                <pre className="max-h-64 overflow-auto rounded-md bg-white p-3 text-xs text-gray-800 shadow-sm">
                                  {formatJson(entry.after_state)}
                                </pre>
                              </div>
                            </div>
                            <div className="mt-3 flex flex-col gap-1 text-xs text-gray-500">
                              <span>IP address: {entry.ip_address ?? '—'}</span>
                              <span>User agent: {entry.user_agent ?? '—'}</span>
                            </div>
                          </td>
                        </tr>
                      )}
                    </>
                  ))
                )}
              </tbody>
            </table>
          </div>

          <Pagination
            hasNextPage={Boolean(data?.meta.next_cursor)}
            hasPreviousPage={cursorStack.length > 1}
            onNext={handleNext}
            onPrevious={handlePrevious}
            isLoading={isLoading}
          />
        </>
      )}
    </div>
  )
}
