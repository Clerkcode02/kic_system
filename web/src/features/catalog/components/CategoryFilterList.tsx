import { cn } from '@/lib/cn'
import { Skeleton } from '@/components'
import { useCategories } from '../hooks/useCategories'

interface CategoryFilterListProps {
  selectedSlug: string | null
  onSelect: (slug: string | null) => void
}

export function CategoryFilterList({ selectedSlug, onSelect }: CategoryFilterListProps) {
  const { data: categories, isLoading } = useCategories()

  if (isLoading) {
    return (
      <div className="flex gap-2 overflow-x-auto pb-1">
        {Array.from({ length: 6 }).map((_, index) => (
          <Skeleton key={index} className="h-8 w-24 shrink-0 rounded-full" />
        ))}
      </div>
    )
  }

  return (
    <div className="flex gap-2 overflow-x-auto pb-1" role="listbox" aria-label="Filter by category">
      <button
        type="button"
        onClick={() => onSelect(null)}
        className={cn(
          'shrink-0 rounded-full border px-3 py-1.5 text-sm font-medium',
          selectedSlug === null
            ? 'border-blue-600 bg-blue-600 text-white'
            : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50',
        )}
      >
        All categories
      </button>
      {(categories ?? []).map((category) => (
        <button
          key={category.id}
          type="button"
          onClick={() => onSelect(category.slug)}
          className={cn(
            'shrink-0 rounded-full border px-3 py-1.5 text-sm font-medium',
            selectedSlug === category.slug
              ? 'border-blue-600 bg-blue-600 text-white'
              : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50',
          )}
        >
          {category.name}
        </button>
      ))}
    </div>
  )
}
