import {
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { Card, EmptyState, Skeleton } from '@/components'
import { formatMoney } from '@/lib/format/money'
import { useDashboardMetrics } from '../hooks/useAnalytics'
import type { AnalyticsSnapshot } from '../types'

/**
 * Categorical palette per the dataviz skill (references/palette.md), fixed
 * slot order — never cycled or reassigned per chart interaction.
 */
const SERIES_COLOR = {
  blue: '#2a78d6',
  orange: '#eb6834',
  red: '#e34948',
} as const

const CHART_CHROME = {
  gridline: '#e1e0d9',
  axis: '#c3c2b7',
  mutedText: '#898781',
} as const

const numberFormatter = new Intl.NumberFormat('en-CA')

function formatCompactNumber(value: number): string {
  return new Intl.NumberFormat('en-CA', { notation: 'compact', maximumFractionDigits: 1 }).format(
    value,
  )
}

function formatSnapshotTime(iso: string): string {
  const date = new Date(iso)
  return date.toLocaleString('en-CA', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

interface StatTileProps {
  label: string
  value: string
}

function StatTile({ label, value }: StatTileProps) {
  return (
    <Card className="flex flex-col gap-1 p-4 sm:p-4">
      <span className="text-sm text-gray-500">{label}</span>
      <span className="text-2xl font-semibold text-gray-900">{value}</span>
    </Card>
  )
}

interface SignupsTileProps {
  customer: number
  provider: number
  freelancer: number
}

function SignupsTile({ customer, provider, freelancer }: SignupsTileProps) {
  return (
    <Card className="flex flex-col gap-2 p-4 sm:p-4">
      <span className="text-sm text-gray-500">New signups (24h)</span>
      <div className="flex items-baseline gap-4">
        <div className="flex flex-col">
          <span className="text-lg font-semibold text-gray-900">{numberFormatter.format(customer)}</span>
          <span className="text-xs text-gray-500">Customer</span>
        </div>
        <div className="flex flex-col">
          <span className="text-lg font-semibold text-gray-900">{numberFormatter.format(provider)}</span>
          <span className="text-xs text-gray-500">Provider</span>
        </div>
        <div className="flex flex-col">
          <span className="text-lg font-semibold text-gray-900">
            {numberFormatter.format(freelancer)}
          </span>
          <span className="text-xs text-gray-500">Freelancer</span>
        </div>
      </div>
    </Card>
  )
}

interface ChartDatum {
  snapshot_at: string
  label: string
  gmv_24h: number
  payout_volume_24h: number
  bookings_active_24h: number
  open_disputes: number
}

function toChartData(snapshots: AnalyticsSnapshot[]): ChartDatum[] {
  return snapshots.map((snapshot) => ({
    snapshot_at: snapshot.snapshot_at,
    label: formatSnapshotTime(snapshot.snapshot_at),
    gmv_24h: snapshot.metrics.gmv_24h,
    payout_volume_24h: snapshot.metrics.payout_volume_24h,
    bookings_active_24h: snapshot.metrics.bookings_active_24h,
    open_disputes: snapshot.metrics.open_disputes,
  }))
}

function MoneyTrendChart({ data }: { data: ChartDatum[] }) {
  return (
    <Card className="p-4 sm:p-6">
      <h2 className="mb-4 text-sm font-medium text-gray-900">GMV vs. payout volume (24h, per snapshot)</h2>
      <ResponsiveContainer width="100%" height={280}>
        <LineChart data={data} margin={{ top: 4, right: 12, left: 4, bottom: 4 }}>
          <CartesianGrid stroke={CHART_CHROME.gridline} vertical={false} />
          <XAxis
            dataKey="label"
            stroke={CHART_CHROME.axis}
            tick={{ fill: CHART_CHROME.mutedText, fontSize: 12 }}
            tickLine={false}
          />
          <YAxis
            stroke={CHART_CHROME.axis}
            tick={{ fill: CHART_CHROME.mutedText, fontSize: 12 }}
            tickLine={false}
            axisLine={false}
            tickFormatter={(value: number) => formatCompactNumber(value)}
          />
          <Tooltip
            formatter={(value) => formatMoney(Number(value))}
            contentStyle={{ borderRadius: 8, borderColor: CHART_CHROME.gridline, fontSize: 13 }}
          />
          <Legend wrapperStyle={{ fontSize: 13 }} />
          <Line
            type="monotone"
            dataKey="gmv_24h"
            name="GMV (24h)"
            stroke={SERIES_COLOR.blue}
            strokeWidth={2}
            dot={{ r: 4, fill: SERIES_COLOR.blue, stroke: '#fcfcfb', strokeWidth: 2 }}
          />
          <Line
            type="monotone"
            dataKey="payout_volume_24h"
            name="Payout volume (24h)"
            stroke={SERIES_COLOR.orange}
            strokeWidth={2}
            dot={{ r: 4, fill: SERIES_COLOR.orange, stroke: '#fcfcfb', strokeWidth: 2 }}
          />
        </LineChart>
      </ResponsiveContainer>
    </Card>
  )
}

function ActivityTrendChart({ data }: { data: ChartDatum[] }) {
  return (
    <Card className="p-4 sm:p-6">
      <h2 className="mb-4 text-sm font-medium text-gray-900">Active bookings vs. open disputes (per snapshot)</h2>
      <ResponsiveContainer width="100%" height={280}>
        <LineChart data={data} margin={{ top: 4, right: 12, left: 4, bottom: 4 }}>
          <CartesianGrid stroke={CHART_CHROME.gridline} vertical={false} />
          <XAxis
            dataKey="label"
            stroke={CHART_CHROME.axis}
            tick={{ fill: CHART_CHROME.mutedText, fontSize: 12 }}
            tickLine={false}
          />
          <YAxis
            stroke={CHART_CHROME.axis}
            tick={{ fill: CHART_CHROME.mutedText, fontSize: 12 }}
            tickLine={false}
            axisLine={false}
            allowDecimals={false}
          />
          <Tooltip
            formatter={(value) => numberFormatter.format(Number(value))}
            contentStyle={{ borderRadius: 8, borderColor: CHART_CHROME.gridline, fontSize: 13 }}
          />
          <Legend wrapperStyle={{ fontSize: 13 }} />
          <Line
            type="monotone"
            dataKey="bookings_active_24h"
            name="Active bookings (24h)"
            stroke={SERIES_COLOR.blue}
            strokeWidth={2}
            dot={{ r: 4, fill: SERIES_COLOR.blue, stroke: '#fcfcfb', strokeWidth: 2 }}
          />
          <Line
            type="monotone"
            dataKey="open_disputes"
            name="Open disputes"
            stroke={SERIES_COLOR.red}
            strokeWidth={2}
            dot={{ r: 4, fill: SERIES_COLOR.red, stroke: '#fcfcfb', strokeWidth: 2 }}
          />
        </LineChart>
      </ResponsiveContainer>
    </Card>
  )
}

function StatTilesSkeleton() {
  return (
    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
      {Array.from({ length: 7 }).map((_, index) => (
        <Skeleton key={index} className="h-20 rounded-lg" />
      ))}
    </div>
  )
}

export function AnalyticsDashboardPage() {
  const { data: snapshots, isLoading, isError, error } = useDashboardMetrics()

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <h1 className="text-lg font-semibold text-gray-900">Analytics</h1>

      {isLoading && (
        <>
          <StatTilesSkeleton />
          <Skeleton className="h-72 rounded-lg" />
          <Skeleton className="h-72 rounded-lg" />
        </>
      )}

      {!isLoading && isError && (
        <EmptyState
          title="Couldn't load analytics"
          description={error instanceof Error ? error.message : 'Something went wrong. Please try again.'}
        />
      )}

      {!isLoading && !isError && (!snapshots || snapshots.length === 0) && (
        <EmptyState
          title="No analytics data yet"
          description="The first snapshot runs within the hour."
        />
      )}

      {!isLoading && !isError && snapshots && snapshots.length > 0 && (
        <>
          {(() => {
            const latest = snapshots[snapshots.length - 1]
            const chartData = toChartData(snapshots)
            return (
              <>
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                  <StatTile
                    label="Total bookings"
                    value={numberFormatter.format(latest.metrics.bookings_total)}
                  />
                  <StatTile
                    label="Active bookings (24h)"
                    value={numberFormatter.format(latest.metrics.bookings_active_24h)}
                  />
                  <StatTile label="GMV (24h)" value={formatMoney(latest.metrics.gmv_24h)} />
                  <StatTile
                    label="Payout volume (24h)"
                    value={formatMoney(latest.metrics.payout_volume_24h)}
                  />
                  <StatTile
                    label="Verification queue"
                    value={numberFormatter.format(latest.metrics.verification_queue_depth)}
                  />
                  <StatTile
                    label="Open disputes"
                    value={numberFormatter.format(latest.metrics.open_disputes)}
                  />
                  <SignupsTile
                    customer={latest.metrics.new_signups_24h.customer}
                    provider={latest.metrics.new_signups_24h.provider}
                    freelancer={latest.metrics.new_signups_24h.freelancer}
                  />
                </div>

                <MoneyTrendChart data={chartData} />
                <ActivityTrendChart data={chartData} />
              </>
            )
          })()}
        </>
      )}
    </div>
  )
}
