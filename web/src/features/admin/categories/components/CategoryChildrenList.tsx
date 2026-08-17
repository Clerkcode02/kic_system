import { DndContext, closestCenter, PointerSensor, useSensor, useSensors } from '@dnd-kit/core'
import type { DragEndEvent } from '@dnd-kit/core'
import { SortableContext, verticalListSortingStrategy, arrayMove } from '@dnd-kit/sortable'
import { SortableCategoryRow } from './SortableCategoryRow'
import type { Category } from '../types'

interface CategoryChildrenListProps {
  children: Category[]
  onReorder: (ordered: Category[]) => void
  onEdit: (category: Category) => void
  onToggleActive: (category: Category) => void
  onDelete: (category: Category) => void
  isMutating: boolean
}

export function CategoryChildrenList({
  children,
  onReorder,
  onEdit,
  onToggleActive,
  onDelete,
  isMutating,
}: CategoryChildrenListProps) {
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 4 } }))

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event
    if (!over || active.id === over.id) return
    const oldIndex = children.findIndex((child) => child.id === active.id)
    const newIndex = children.findIndex((child) => child.id === over.id)
    if (oldIndex === -1 || newIndex === -1) return
    onReorder(arrayMove(children, oldIndex, newIndex))
  }

  if (children.length === 0) return null

  return (
    <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
      <SortableContext items={children.map((child) => child.id)} strategy={verticalListSortingStrategy}>
        <div className="mt-2 flex flex-col gap-2">
          {children.map((child) => (
            <SortableCategoryRow
              key={child.id}
              category={child}
              indent
              onEdit={() => onEdit(child)}
              onToggleActive={() => onToggleActive(child)}
              onDelete={() => onDelete(child)}
              isMutating={isMutating}
            />
          ))}
        </div>
      </SortableContext>
    </DndContext>
  )
}
