import { z } from 'zod'

const nameSchema = z.string().min(1, 'Name is required').max(255)
const emailSchema = z.string().email('Enter a valid email address').max(255)
const phoneSchema = z.string().min(1, 'Phone number is required').max(32)
const passwordSchema = z.string().min(8, 'Password must be at least 8 characters')

const credentialsSchema = z
  .object({
    name: nameSchema,
    email: emailSchema,
    phone: phoneSchema,
    password: passwordSchema,
    password_confirmation: z.string(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
  })

export const loginSchema = z.object({
  email: emailSchema,
  password: z.string().min(1, 'Password is required'),
})
export type LoginFormValues = z.infer<typeof loginSchema>

export const registerCustomerSchema = credentialsSchema
export type RegisterCustomerFormValues = z.infer<typeof registerCustomerSchema>

const dayHoursSchema = z
  .object({
    open: z.string().min(1, 'Set an opening time'),
    close: z.string().min(1, 'Set a closing time'),
  })
  .nullable()

const businessHoursSchema = z.object({
  monday: dayHoursSchema,
  tuesday: dayHoursSchema,
  wednesday: dayHoursSchema,
  thursday: dayHoursSchema,
  friday: dayHoursSchema,
  saturday: dayHoursSchema,
  sunday: dayHoursSchema,
})

export const registerBusinessSchema = credentialsSchema.and(
  z.object({
    legal_name: z.string().min(1, 'Legal business name is required').max(255),
    registration_number: z.string().min(1, 'Registration number is required').max(255),
    business_hours: businessHoursSchema,
    max_bookings_per_day: z.coerce.number().int().min(1, 'Must allow at least 1 booking per day'),
  }),
)
export type RegisterBusinessFormValues = z.infer<typeof registerBusinessSchema>

export const registerFreelancerSchema = credentialsSchema.and(
  z.object({
    headline: z.string().min(1, 'Headline is required').max(255),
    bio: z.string().min(1, 'Bio is required'),
    hourly_rate: z.coerce.number().min(0, 'Hourly rate must be zero or more'),
    years_experience: z.coerce.number().int().min(0).optional(),
  }),
)
export type RegisterFreelancerFormValues = z.infer<typeof registerFreelancerSchema>

export const forgotPasswordSchema = z.object({
  email: emailSchema,
})
export type ForgotPasswordFormValues = z.infer<typeof forgotPasswordSchema>

export const resetPasswordSchema = z
  .object({
    password: passwordSchema,
    password_confirmation: z.string(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
  })
export type ResetPasswordFormValues = z.infer<typeof resetPasswordSchema>
