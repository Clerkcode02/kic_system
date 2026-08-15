import { z } from 'zod'

/** Mirrors UpdateProviderAvailabilityRequest (ReplaceProviderAvailability action). */
export const weeklyRowSchema = z.object({
  day_of_week: z.number().int().min(0).max(6),
  start_time: z.string().min(1, 'Required'),
  end_time: z.string().min(1, 'Required'),
  is_active: z.boolean(),
})

export const overrideRowSchema = z
  .object({
    date: z.string().min(1, 'Date is required'),
    is_blackout: z.boolean(),
    start_time: z.string().optional(),
    end_time: z.string().optional(),
  })
  .refine((row) => row.is_blackout || (row.start_time && row.end_time), {
    message: 'Start and end time are required unless this date is a blackout',
    path: ['start_time'],
  })

export const availabilityFormSchema = z.object({
  weekly: z.array(weeklyRowSchema),
  overrides: z.array(overrideRowSchema),
})

export type AvailabilityFormValues = z.infer<typeof availabilityFormSchema>
