import { useEffect } from 'react'
import { useFieldArray, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useNavigate, useParams } from 'react-router-dom'
import toast from 'react-hot-toast'
import { Button, Card, EmptyState, Input, Select, Skeleton } from '@/components'
import { ApiError } from '@/lib/api'
import { useCategories } from '@/features/catalog/hooks/useCategories'
import { useService } from '../hooks/useServices'
import { useCreateService, useUpdateService } from '../hooks/useServiceMutations'
import { serviceFormSchema, type ServiceFormValues } from '../schemas'

const PRICING_TYPE_OPTIONS = [
  { value: 'fixed', label: 'Fixed price' },
  { value: 'hourly', label: 'Hourly' },
  { value: 'package', label: 'Package' },
  { value: 'inspection', label: 'Inspection' },
]

const EMPTY_VALUES: ServiceFormValues = {
  category_id: '',
  title: '',
  description: '',
  pricing_type: 'fixed',
  base_price: 0,
  estimated_duration_minutes: 60,
  pricing_tiers: [],
}

export function ServiceFormPage() {
  const { serviceId } = useParams<{ serviceId: string }>()
  const isEditing = Boolean(serviceId)
  const navigate = useNavigate()
  const { data: categories, isLoading: isLoadingCategories } = useCategories()
  const { data: service, isLoading: isLoadingService } = useService(serviceId)
  const { mutateAsync: create, isPending: isCreating } = useCreateService()
  const { mutateAsync: update, isPending: isUpdating } = useUpdateService(serviceId ?? '')

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<ServiceFormValues>({
    resolver: zodResolver(serviceFormSchema),
    defaultValues: EMPTY_VALUES,
  })
  const { fields, append, remove } = useFieldArray({ control, name: 'pricing_tiers' })

  useEffect(() => {
    if (!service) return
    reset({
      category_id: service.category.id,
      title: service.title,
      description: service.description,
      pricing_type: service.pricing_type,
      base_price: Number(service.base_price),
      estimated_duration_minutes: service.estimated_duration_minutes,
      pricing_tiers: (service.pricing_tiers ?? []).map((tier) => ({
        tier_name: tier.tier_name,
        description: tier.description ?? undefined,
        price: Number(tier.price),
        estimated_duration_minutes: tier.estimated_duration_minutes ?? undefined,
      })),
    })
  }, [service, reset])

  if (isEditing && isLoadingService) {
    return (
      <div className="p-6">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="mt-4 h-64 w-full" />
      </div>
    )
  }

  if (isEditing && !isLoadingService && !service) {
    return (
      <div className="p-6">
        <EmptyState title="Service not found" description="It may not exist or you may not have access." />
      </div>
    )
  }

  const onSubmit = async (values: ServiceFormValues) => {
    try {
      if (isEditing && serviceId) {
        await update(values)
        toast.success('Service updated.')
      } else {
        await create(values)
        toast.success('Service created.')
      }
      navigate('/provider/services')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not save this service.')
    }
  }

  const categoryOptions = (categories ?? []).map((category) => ({
    value: category.id,
    label: category.name,
  }))

  return (
    <div className="mx-auto max-w-2xl p-4 sm:p-6">
      <Card className="flex flex-col gap-4">
        <h1 className="text-lg font-semibold text-gray-900">
          {isEditing ? 'Edit service' : 'Add service'}
        </h1>
        <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
          <Select
            label="Category"
            options={categoryOptions}
            placeholder={isLoadingCategories ? 'Loading…' : 'Select a category'}
            error={errors.category_id?.message}
            {...register('category_id')}
          />
          <Input label="Title" error={errors.title?.message} {...register('title')} />
          <div className="flex flex-col gap-1">
            <label htmlFor="description" className="text-sm font-medium text-gray-700">
              Description
            </label>
            <textarea
              id="description"
              rows={4}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              {...register('description')}
            />
            {errors.description?.message && (
              <p className="text-sm text-red-600">{errors.description.message}</p>
            )}
          </div>
          <Select
            label="Pricing type"
            options={PRICING_TYPE_OPTIONS}
            error={errors.pricing_type?.message}
            {...register('pricing_type')}
          />
          <div className="grid grid-cols-2 gap-3">
            <Input
              label="Base price (CAD)"
              type="number"
              min={0}
              step="0.01"
              error={errors.base_price?.message}
              {...register('base_price')}
            />
            <Input
              label="Estimated duration (minutes)"
              type="number"
              min={1}
              error={errors.estimated_duration_minutes?.message}
              {...register('estimated_duration_minutes')}
            />
          </div>

          <div className="flex flex-col gap-2">
            <div className="flex items-center justify-between">
              <span className="text-sm font-medium text-gray-700">Pricing tiers (optional)</span>
              <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={() => append({ tier_name: '', price: 0 })}
              >
                Add tier
              </Button>
            </div>
            {fields.map((field, index) => (
              <div key={field.id} className="grid grid-cols-[1fr_6rem_6rem_auto] items-end gap-2">
                <Input
                  label={index === 0 ? 'Tier name' : undefined}
                  error={errors.pricing_tiers?.[index]?.tier_name?.message}
                  {...register(`pricing_tiers.${index}.tier_name` as const)}
                />
                <Input
                  label={index === 0 ? 'Price' : undefined}
                  type="number"
                  min={0}
                  step="0.01"
                  error={errors.pricing_tiers?.[index]?.price?.message}
                  {...register(`pricing_tiers.${index}.price` as const)}
                />
                <Input
                  label={index === 0 ? 'Duration (min)' : undefined}
                  type="number"
                  min={1}
                  error={errors.pricing_tiers?.[index]?.estimated_duration_minutes?.message}
                  {...register(`pricing_tiers.${index}.estimated_duration_minutes` as const)}
                />
                <Button type="button" variant="ghost" size="sm" onClick={() => remove(index)}>
                  Remove
                </Button>
              </div>
            ))}
          </div>

          <div className="flex gap-3">
            <Button
              type="button"
              variant="secondary"
              onClick={() => navigate('/provider/services')}
              className="flex-1"
            >
              Cancel
            </Button>
            <Button type="submit" isLoading={isCreating || isUpdating} className="flex-1">
              {isEditing ? 'Save changes' : 'Create service'}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  )
}
