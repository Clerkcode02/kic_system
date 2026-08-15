import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useNavigate } from 'react-router-dom'
import { Button, Input } from '@/components'
import { BusinessHoursInput } from '@/features/business/components/BusinessHoursInput'
import { EMPTY_BUSINESS_HOURS } from '@/features/business/constants'
import { registerBusiness } from '../api/authApi'
import { registerBusinessSchema, type RegisterBusinessFormValues } from '../schemas'
import { useSubmitWithApiErrors } from '../hooks/useSubmitWithApiErrors'

export function RegisterBusinessForm() {
  const navigate = useNavigate()
  const {
    register,
    control,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<RegisterBusinessFormValues>({
    resolver: zodResolver(registerBusinessSchema),
    defaultValues: { business_hours: EMPTY_BUSINESS_HOURS },
  })
  const submitWithApiErrors = useSubmitWithApiErrors(setError)

  const onSubmit = (values: RegisterBusinessFormValues) =>
    submitWithApiErrors(async () => {
      await registerBusiness(values)
      navigate('/verify-pending', { replace: true })
    }, 'Registration failed. Please try again.')

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
      <Input
        label="Your name"
        autoComplete="name"
        error={errors.name?.message}
        {...register('name')}
      />
      <Input
        label="Email"
        type="email"
        autoComplete="email"
        error={errors.email?.message}
        {...register('email')}
      />
      <Input
        label="Phone"
        type="tel"
        autoComplete="tel"
        error={errors.phone?.message}
        {...register('phone')}
      />
      <Input
        label="Password"
        type="password"
        autoComplete="new-password"
        error={errors.password?.message}
        {...register('password')}
      />
      <Input
        label="Confirm password"
        type="password"
        autoComplete="new-password"
        error={errors.password_confirmation?.message}
        {...register('password_confirmation')}
      />

      <hr className="border-gray-200" />

      <Input
        label="Legal business name"
        error={errors.legal_name?.message}
        {...register('legal_name')}
      />
      <Input
        label="Business registration number"
        error={errors.registration_number?.message}
        {...register('registration_number')}
      />
      <Input
        label="Max bookings per day"
        type="number"
        min={1}
        error={errors.max_bookings_per_day?.message}
        {...register('max_bookings_per_day')}
      />
      <Controller
        control={control}
        name="business_hours"
        render={({ field }) => (
          <BusinessHoursInput
            value={field.value}
            onChange={field.onChange}
            error={errors.business_hours?.message}
          />
        )}
      />

      <Button type="submit" isLoading={isSubmitting} className="w-full">
        Create business account
      </Button>
    </form>
  )
}
