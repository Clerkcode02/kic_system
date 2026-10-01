import { Button, Input, Select } from '@/components'
import { useProjectCategories } from '../hooks/useProjects'

interface ProjectFilterBarProps {
  category: string
  budgetMin: string
  budgetMax: string
  skills: string
  onCategoryChange: (category: string) => void
  onBudgetMinChange: (value: string) => void
  onBudgetMaxChange: (value: string) => void
  onSkillsChange: (value: string) => void
  onApplyBudget: () => void
}

export function ProjectFilterBar({
  category,
  budgetMin,
  budgetMax,
  skills,
  onCategoryChange,
  onBudgetMinChange,
  onBudgetMaxChange,
  onSkillsChange,
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
      {/*
        Comma-separated free text rather than a picker: project skills are
        free text, so there's no fixed vocabulary to offer. The server
        matches *any* of the listed skills and normalizes casing/spacing.
      */}
      <Input
        label="Skills"
        name="skills"
        placeholder="react, figma"
        value={skills}
        onChange={(event) => onSkillsChange(event.target.value)}
      />
      <Button type="button" variant="secondary" onClick={onApplyBudget}>
        Apply
      </Button>
    </div>
  )
}
