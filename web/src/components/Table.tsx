import type { ReactNode } from 'react'
import { cn } from '@/lib/cn'

export interface TableColumn<T> {
  key: string
  header: string
  render: (row: T) => ReactNode
  className?: string
}

interface TableProps<T> {
  columns: TableColumn<T>[]
  rows: T[]
  getRowKey: (row: T) => string
  emptyMessage?: string
  /** 'ledger' is the Trade Docket world's statement treatment (freelancer area only). */
  variant?: 'default' | 'ledger'
}

const containerClasses = {
  default: 'overflow-x-auto rounded-lg border border-gray-200',
  ledger: 'overflow-x-auto rounded-sm border border-docket-line',
}

const headClasses = {
  default: 'bg-gray-50',
  ledger: 'bg-docket-well',
}

const tableClasses = {
  default: 'min-w-full divide-y divide-gray-200 text-sm',
  ledger: 'min-w-full divide-y divide-docket-rule text-sm',
}

const thClasses = {
  default: 'px-4 py-3 text-left font-medium text-gray-500',
  ledger: 'px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-docket-soft',
}

const tbodyClasses = {
  default: 'divide-y divide-gray-100 bg-white',
  ledger: 'divide-y divide-docket-rule bg-docket-paper',
}

const tdClasses = {
  default: 'px-4 py-3 text-gray-900',
  ledger: 'px-4 py-2.5 text-docket-ink',
}

export function Table<T>({
  columns,
  rows,
  getRowKey,
  emptyMessage = 'No records found.',
  variant = 'default',
}: TableProps<T>) {
  return (
    <div className={containerClasses[variant]}>
      <table className={tableClasses[variant]}>
        <thead className={headClasses[variant]}>
          <tr>
            {columns.map((column) => (
              <th key={column.key} scope="col" className={thClasses[variant]}>
                {column.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className={tbodyClasses[variant]}>
          {rows.length === 0 ? (
            <tr>
              <td
                colSpan={columns.length}
                className={cn(
                  'px-4 py-8 text-center',
                  variant === 'ledger' ? 'text-docket-soft' : 'text-gray-500',
                )}
              >
                {emptyMessage}
              </td>
            </tr>
          ) : (
            rows.map((row) => (
              <tr key={getRowKey(row)}>
                {columns.map((column) => (
                  <td key={column.key} className={cn(tdClasses[variant], column.className)}>
                    {column.render(row)}
                  </td>
                ))}
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  )
}
