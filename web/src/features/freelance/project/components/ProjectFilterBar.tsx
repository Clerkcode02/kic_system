import { Button, Input, Select } from '@/components'
import { useProjectCategories } from '../hooks/useProjects'

interface ProjectFilterBarProps {
  category: string
  budgetMin: string
  budgetMax: string
  onCategoryChange: (category: string) => void
  onBudgetMinChange: (value: string) => void
  onBudgetMaxChange: (value: string) => void
  onApplyBudget: () => void
}

export function ProjectFilterBar({
  category,
  budgetMin,
  budgetMax,
  onCategoryChange,
  onBudgetMinChange,
  onBudgetMaxChange,
  onApplyBudget,
}: ProjectFilterBarProps) {
  const { data: categories, isLoading } = useProjectCategories()

  const categoryOptions = [
    { value: '', label: 'All categories' },
    ...(categories ?? []).map((c) => ({ value: c.id, label: c.name })),
  ]

  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:flex-wrap">
      <Select
        label="Category"
        aria-label="Filter by category"
        options={categoryOptions}
        value={category}
        disabled={isLoading}
        onChange={(event) => onCategoryChange(event.target.value)}
      />
      <Input
        label="Min budget (CAD)"
        type="number"
        min={0}
        step="0.01"
        value={budgetMin}
        onChange={(event) => onBudgetMinChange(event.target.value)}
      />
      <Input
        label="Max budget (CAD)"
        type="number"
        min={0}
        step="0.01"
        value={budgetMax}
        onChange={(event) => onBudgetMaxChange(event.target.value)}
      />
      <Button type="button" variant="secondary" onClick={onApplyBudget}>
        Apply
      </Button>
    </div>
  )
}
