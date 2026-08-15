import { useEffect } from 'react'
import { useFieldArray, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import toast from 'react-hot-toast'
import { Button, Card, Input, Skeleton } from '@/components'
import { ApiError } from '@/lib/api'
import { toLocalDateString } from '@/lib/calendar'
import { useAvailability, useReplaceAvailability } from '../hooks/useProviderAvailability'
import { availabilityFormSchema, type AvailabilityFormValues } from '../schemas'
import type { AvailabilityConfig } from '../types'

const DAY_LABELS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']

function toFormValues(config: AvailabilityConfig): AvailabilityFormValues {
  const weekly = DAY_LABELS.map((_, dayOfWeek) => {
    const existing = config.weekly.find((row) => row.day_of_week === dayOfWeek)
    return {
      day_of_week: dayOfWeek,
      start_time: existing?.start_time.slice(0, 5) ?? '09:00',
      end_time: existing?.end_time.slice(0, 5) ?? '17:00',
      is_active: existing?.is_active ?? false,
    }
  })
  const overrides = config.overrides.map((row) => ({
    date: row.date,
    is_blackout: row.is_blackout,
    start_time: row.start_time?.slice(0, 5) ?? '',
    end_time: row.end_time?.slice(0, 5) ?? '',
  }))
  return { weekly, overrides }
}

/**
 * Weekly recurring hours + date overrides/blackouts editor, PUT-replacing
 * the whole config each save (ReplaceProviderAvailability has no partial-
 * update path). Doesn't reuse `lib/calendar/AvailabilityCalendar` directly —
 * that component renders a single day's bookable *slots* for the customer
 * booking wizard, which doesn't fit a weekly-recurring-hours editor. It does
 * reuse the lib's `toLocalDateString` helper for the override date picker,
 * consistent with CLAUDE.md §9.9 keeping calendar-day logic behind `lib/`.
 */
export function AvailabilityEditorPage() {
  const { data: availability, isLoading, isError } = useAvailability()
  const { mutateAsync: save, isPending: isSaving } = useReplaceAvailability()

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<AvailabilityFormValues>({
    resolver: zodResolver(availabilityFormSchema),
    defaultValues: { weekly: [], overrides: [] },
  })
  const { fields: weeklyFields } = useFieldArray({ control, name: 'weekly' })
  const {
    fields: overrideFields,
    append: appendOverride,
    remove: removeOverride,
  } = useFieldArray({ control, name: 'overrides' })

  useEffect(() => {
    if (availability) reset(toFormValues(availability))
  }, [availability, reset])

  if (isLoading) {
    return (
      <div className="p-6">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="mt-4 h-64 w-full" />
      </div>
    )
  }

  if (isError) {
    return (
      <div className="p-6">
        <p className="text-sm text-red-600">Couldn't load your availability. Please try again.</p>
      </div>
    )
  }

  const onSubmit = async (values: AvailabilityFormValues) => {
    try {
      await save({
        weekly: values.weekly.map((row) => ({
          day_of_week: row.day_of_week,
          start_time: row.start_time,
          end_time: row.end_time,
          is_active: row.is_active,
        })),
        overrides: values.overrides.map((row) => ({
          date: row.date,
          is_blackout: row.is_blackout,
          start_time: row.is_blackout ? null : row.start_time || null,
          end_time: row.is_blackout ? null : row.end_time || null,
        })),
      })
      toast.success('Availability updated.')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not save your availability.')
    }
  }

  return (
    <div className="mx-auto flex max-w-2xl flex-col gap-4 p-4 sm:p-6">
      <h1 className="text-lg font-semibold text-gray-900">Availability</h1>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Card className="flex flex-col gap-3">
          <h2 className="text-sm font-semibold text-gray-900">Weekly hours</h2>
          <div className="flex flex-col gap-2">
            {weeklyFields.map((field, index) => (
              <div key={field.id} className="grid grid-cols-[7rem_1fr_1fr_auto] items-end gap-2">
                <p className="pb-2 text-sm text-gray-700">{DAY_LABELS[index]}</p>
                <Input
                  type="time"
                  error={errors.weekly?.[index]?.start_time?.message}
                  {...register(`weekly.${index}.start_time` as const)}
                />
                <Input
                  type="time"
                  error={errors.weekly?.[index]?.end_time?.message}
                  {...register(`weekly.${index}.end_time` as const)}
                />
                <label className="flex items-center gap-1 pb-2 text-sm text-gray-600">
                  <input type="checkbox" {...register(`weekly.${index}.is_active` as const)} />
                  Active
                </label>
              </div>
            ))}
          </div>
        </Card>

        <Card className="flex flex-col gap-3">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-semibold text-gray-900">Date overrides &amp; blackouts</h2>
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={() =>
                appendOverride({
                  date: toLocalDateString(new Date()),
                  is_blackout: true,
                  start_time: '',
                  end_time: '',
                })
              }
            >
              Add override
            </Button>
          </div>
          {overrideFields.length === 0 && (
            <p className="text-sm text-gray-500">No date overrides yet.</p>
          )}
          <div className="flex flex-col gap-2">
            {overrideFields.map((field, index) => (
              <div key={field.id} className="grid grid-cols-[8rem_1fr_1fr_5rem_auto] items-end gap-2">
                <Input
                  type="date"
                  label={index === 0 ? 'Date' : undefined}
                  error={errors.overrides?.[index]?.date?.message}
                  {...register(`overrides.${index}.date` as const)}
                />
                <Input
                  type="time"
                  label={index === 0 ? 'Start' : undefined}
                  error={errors.overrides?.[index]?.start_time?.message}
                  {...register(`overrides.${index}.start_time` as const)}
                />
                <Input
                  type="time"
                  label={index === 0 ? 'End' : undefined}
                  {...register(`overrides.${index}.end_time` as const)}
                />
                <label className="flex items-center gap-1 pb-2 text-sm text-gray-600">
                  <input type="checkbox" {...register(`overrides.${index}.is_blackout` as const)} />
                  Blackout
                </label>
                <Button type="button" variant="ghost" size="sm" onClick={() => removeOverride(index)}>
                  Remove
                </Button>
              </div>
            ))}
          </div>
        </Card>

        <Button type="submit" isLoading={isSaving} className="self-start">
          Save availability
        </Button>
      </form>
    </div>
  )
}
