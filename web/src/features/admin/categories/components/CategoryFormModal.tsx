import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import toast from 'react-hot-toast'
import { Button, Input, Modal, Select } from '@/components'
import { ApiError } from '@/lib/api'
import { useCreateCategory, useUpdateCategory } from '../hooks/useCategories'
import type { Category } from '../types'

const categoryFormSchema = z.object({
  name: z.string().min(1, 'Name is required.'),
  slug: z.string().optional(),
  icon: z.string().optional(),
  is_active: z.boolean(),
  platform_fee_percentage: z
    .union([z.string().length(0), z.string().regex(/^\d+(\.\d{1,2})?$/, 'Enter a number like 10.00')])
    .optional(),
  parent_id: z.string().optional(),
})

type CategoryFormValues = z.infer<typeof categoryFormSchema>

const EMPTY_VALUES: CategoryFormValues = {
  name: '',
  slug: '',
  icon: '',
  is_active: true,
  platform_fee_percentage: '',
  parent_id: '',
}

interface CategoryFormModalProps {
  isOpen: boolean
  onClose: () => void
  category?: Category | null
  /** Current or default parent id — null/undefined means top-level. */
  initialParentId?: string | null
  /** Flat list of top-level categories offered in the parent dropdown. */
  topLevelCategories: Category[]
}

export function CategoryFormModal({
  isOpen,
  onClose,
  category,
  initialParentId,
  topLevelCategories,
}: CategoryFormModalProps) {
  const isEditing = Boolean(category)
  const { mutateAsync: create, isPending: isCreating } = useCreateCategory()
  const { mutateAsync: update, isPending: isUpdating } = useUpdateCategory()

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<CategoryFormValues>({
    resolver: zodResolver(categoryFormSchema),
    defaultValues: EMPTY_VALUES,
  })

  useEffect(() => {
    if (!isOpen) return
    if (category) {
      reset({
        name: category.name,
        slug: category.slug,
        icon: category.icon ?? '',
        is_active: category.is_active,
        platform_fee_percentage:
          category.platform_fee_percentage !== undefined
            ? String(category.platform_fee_percentage)
            : '',
        parent_id: initialParentId ?? '',
      })
    } else {
      reset({ ...EMPTY_VALUES, parent_id: initialParentId ?? '' })
    }
  }, [isOpen, category, initialParentId, reset])

  const parentOptions = [
    { value: '', label: 'None (top-level)' },
    ...topLevelCategories
      .filter((top) => top.id !== category?.id)
      .map((top) => ({ value: top.id, label: top.name })),
  ]

  const onSubmit = async (values: CategoryFormValues) => {
    const payload = {
      name: values.name,
      slug: values.slug ? values.slug : undefined,
      icon: values.icon ? values.icon : null,
      is_active: values.is_active,
      platform_fee_percentage: values.platform_fee_percentage
        ? values.platform_fee_percentage
        : undefined,
      parent_id: values.parent_id ? values.parent_id : null,
    }
    try {
      if (isEditing && category) {
        await update({ id: category.id, payload })
        toast.success('Category updated.')
      } else {
        await create(payload)
        toast.success('Category created.')
      }
      onClose()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not save this category.')
    }
  }

  return (
    <Modal isOpen={isOpen} onClose={onClose} title={isEditing ? 'Edit category' : 'Add category'}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input label="Name" error={errors.name?.message} {...register('name')} />
        <Input
          label="Slug (optional, auto-derived if left blank)"
          error={errors.slug?.message}
          {...register('slug')}
        />
        <Input label="Icon (emoji or text)" error={errors.icon?.message} {...register('icon')} />
        <Select
          label="Parent category"
          options={parentOptions}
          error={errors.parent_id?.message}
          {...register('parent_id')}
        />
        <Input
          label="Platform fee override (%)"
          placeholder="e.g. 10.00"
          error={errors.platform_fee_percentage?.message}
          {...register('platform_fee_percentage')}
        />
        <label className="flex items-center gap-2 text-sm font-medium text-gray-700">
          <input type="checkbox" className="h-4 w-4 rounded border-gray-300" {...register('is_active')} />
          Active
        </label>
        <div className="flex gap-3">
          <Button type="button" variant="secondary" onClick={onClose} className="flex-1">
            Cancel
          </Button>
          <Button type="submit" isLoading={isCreating || isUpdating} className="flex-1">
            {isEditing ? 'Save changes' : 'Create category'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
