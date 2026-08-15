import { Card } from '@/components'

export function AdminDashboard() {
  return (
    <div className="p-6">
      <Card>
        <h1 className="text-lg font-semibold text-gray-900">Admin dashboard</h1>
        <p className="mt-1 text-sm text-gray-500">
          Verification queue, disputes, and analytics go here.
        </p>
      </Card>
    </div>
  )
}
