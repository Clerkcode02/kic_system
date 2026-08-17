import { useState } from 'react'
import toast from 'react-hot-toast'
import { DndContext, closestCenter, PointerSensor, useSensor, useSensors } from '@dnd-kit/core'
import type { DragEndEvent } from '@dnd-kit/core'
import { SortableContext, verticalListSortingStrategy, arrayMove } from '@dnd-kit/sortable'
import { Button, Card, EmptyState, Skeleton } from '@/components'
import { ApiError } from '@/lib/api'
import {
  useCategoryTree,
  useDeleteCategory,
  useReorderCategories,
  useUpdateCategory,
} from '../hooks/useCategories'
import type { Category } from '../types'
import { SortableCategoryRow } from './SortableCategoryRow'
import { CategoryChildrenList } from './CategoryChildrenList'
import { CategoryFormModal } from './CategoryFormModal'

export function CategoryTreePage() {
  const { data: categories, isLoading } = useCategoryTree()
  const { mutateAsync: reorder } = useReorderCategories()
  const { mutateAsync: update, isPending: isUpdating } = useUpdateCategory()
  const { mutateAsync: remove, isPending: isDeleting } = useDeleteCategory()

  const [isFormOpen, setIsFormOpen] = useState(false)
  const [editingCategory, setEditingCategory] = useState<Category | null>(null)
  const [formParentId, setFormParentId] = useState<string | null>(null)

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 4 } }))

  const openAddModal = (parentId: string | null) => {
    setEditingCategory(null)
    setFormParentId(parentId)
    setIsFormOpen(true)
  }

  const openEditModal = (category: Category, parentId: string | null) => {
    setEditingCategory(category)
    setFormParentId(parentId)
    setIsFormOpen(true)
  }

  const handleToggleActive = async (category: Category) => {
    try {
      await update({ id: category.id, payload: { is_active: !category.is_active } })
      toast.success(category.is_active ? 'Category deactivated.' : 'Category activated.')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not update this category.')
    }
  }

  const handleDelete = async (category: Category) => {
    if (!window.confirm(`Delete "${category.name}"? This cannot be undone.`)) return
    try {
      await remove(category.id)
      toast.success('Category deleted.')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not delete this category.')
    }
  }

  const handleTopLevelDragEnd = async (event: DragEndEvent) => {
    if (!categories) return
    const { active, over } = event
    if (!over || active.id === over.id) return
    const oldIndex = categories.findIndex((c) => c.id === active.id)
    const newIndex = categories.findIndex((c) => c.id === over.id)
    if (oldIndex === -1 || newIndex === -1) return
    const ordered = arrayMove(categories, oldIndex, newIndex)
    try {
      await reorder(ordered.map((c, index) => ({ id: c.id, sort_order: index })))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not reorder categories.')
    }
  }

  const handleChildrenReorder = async (ordered: Category[]) => {
    try {
      await reorder(ordered.map((c, index) => ({ id: c.id, sort_order: index })))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not reorder categories.')
    }
  }

  if (isLoading) {
    return (
      <div className="p-4 sm:p-6">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="mt-4 h-96 w-full" />
      </div>
    )
  }

  const topLevel = categories ?? []

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <div className="flex items-center justify-between">
        <h1 className="text-lg font-semibold text-gray-900">Categories</h1>
        <Button type="button" onClick={() => openAddModal(null)}>
          Add category
        </Button>
      </div>

      {topLevel.length === 0 ? (
        <EmptyState
          title="No categories yet"
          description="Create your first top-level category to get started."
          action={
            <Button type="button" onClick={() => openAddModal(null)}>
              Add category
            </Button>
          }
        />
      ) : (
        <Card>
          <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragEnd={handleTopLevelDragEnd}
          >
            <SortableContext
              items={topLevel.map((c) => c.id)}
              strategy={verticalListSortingStrategy}
            >
              <div className="flex flex-col gap-3">
                {topLevel.map((category) => (
                  <SortableCategoryRow
                    key={category.id}
                    category={category}
                    isMutating={isUpdating || isDeleting}
                    onAddChild={() => openAddModal(category.id)}
                    onEdit={() => openEditModal(category, null)}
                    onToggleActive={() => handleToggleActive(category)}
                    onDelete={() => handleDelete(category)}
                  >
                    <CategoryChildrenList
                      children={category.children ?? []}
                      isMutating={isUpdating || isDeleting}
                      onReorder={handleChildrenReorder}
                      onEdit={(child) => openEditModal(child, category.id)}
                      onToggleActive={handleToggleActive}
                      onDelete={handleDelete}
                    />
                  </SortableCategoryRow>
                ))}
              </div>
            </SortableContext>
          </DndContext>
        </Card>
      )}

      <CategoryFormModal
        isOpen={isFormOpen}
        onClose={() => setIsFormOpen(false)}
        category={editingCategory}
        initialParentId={formParentId}
        topLevelCategories={topLevel}
      />
    </div>
  )
}
