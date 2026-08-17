import type { ReactNode } from 'react'
import { useSortable } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { Badge, Button } from '@/components'
import { cn } from '@/lib/cn'
import type { Category } from '../types'

interface SortableCategoryRowProps {
  category: Category
  onAddChild?: () => void
  onEdit: () => void
  onToggleActive: () => void
  onDelete: () => void
  isMutating: boolean
  indent?: boolean
  children?: ReactNode
}

export function SortableCategoryRow({
  category,
  onAddChild,
  onEdit,
  onToggleActive,
  onDelete,
  isMutating,
  indent = false,
  children,
}: SortableCategoryRowProps) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: category.id,
  })

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  }

  return (
    <div ref={setNodeRef} style={style} className={cn(indent && 'ml-6')}>
      <div
        className={cn(
          'flex flex-wrap items-center gap-3 rounded-md border border-gray-200 bg-white p-3',
          isDragging && 'opacity-50',
        )}
      >
        <button
          type="button"
          aria-label="Drag to reorder"
          className="cursor-grab touch-none text-gray-400 hover:text-gray-600 active:cursor-grabbing"
          {...attributes}
          {...listeners}
        >
          ⠿
        </button>
        <span className="text-lg" aria-hidden="true">
          {category.icon ?? '📁'}
        </span>
        <div className="flex min-w-0 flex-1 flex-col">
          <span className="truncate font-medium text-gray-900">{category.name}</span>
          <span className="truncate text-xs text-gray-500">{category.slug}</span>
        </div>
        <Badge tone={category.is_active ? 'success' : 'neutral'}>
          {category.is_active ? 'Active' : 'Inactive'}
        </Badge>
        <div className="flex flex-wrap items-center gap-2">
          {onAddChild && (
            <Button type="button" variant="ghost" size="sm" onClick={onAddChild}>
              Add child
            </Button>
          )}
          <Button type="button" variant="ghost" size="sm" onClick={onEdit}>
            Edit
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            disabled={isMutating}
            onClick={onToggleActive}
          >
            {category.is_active ? 'Deactivate' : 'Activate'}
          </Button>
          <Button type="button" variant="danger" size="sm" disabled={isMutating} onClick={onDelete}>
            Delete
          </Button>
        </div>
      </div>
      {children}
    </div>
  )
}
