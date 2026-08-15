import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useNavigate } from 'react-router-dom'
import { Button, Input } from '@/components'
import { registerFreelancer } from '../api/authApi'
import { registerFreelancerSchema, type RegisterFreelancerFormValues } from '../schemas'
import { useSubmitWithApiErrors } from '../hooks/useSubmitWithApiErrors'

export function RegisterFreelancerForm() {
  const navigate = useNavigate()
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<RegisterFreelancerFormValues>({ resolver: zodResolver(registerFreelancerSchema) })
  const submitWithApiErrors = useSubmitWithApiErrors(setError)

  const onSubmit = (values: RegisterFreelancerFormValues) =>
    submitWithApiErrors(async () => {
      await registerFreelancer(values)
      navigate('/verify-pending', { replace: true })
    }, 'Registration failed. Please try again.')

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
      <Input
        label="Full name"
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

      <Input label="Headline" error={errors.headline?.message} {...register('headline')} />
      <div className="flex flex-col gap-1">
        <label htmlFor="bio" className="text-sm font-medium text-gray-700">
          Bio
        </label>
        <textarea
          id="bio"
          rows={4}
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          {...register('bio')}
        />
        {errors.bio?.message && <p className="text-sm text-red-600">{errors.bio.message}</p>}
      </div>
      <Input
        label="Hourly rate (CAD)"
        type="number"
        min={0}
        step="0.01"
        error={errors.hourly_rate?.message}
        {...register('hourly_rate')}
      />
      <Input
        label="Years of experience (optional)"
        type="number"
        min={0}
        error={errors.years_experience?.message}
        {...register('years_experience')}
      />

      <Button type="submit" isLoading={isSubmitting} className="w-full">
        Create freelancer account
      </Button>
    </form>
  )
}
