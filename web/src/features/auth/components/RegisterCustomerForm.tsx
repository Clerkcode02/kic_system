import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useNavigate } from 'react-router-dom'
import { Button, Input } from '@/components'
import { registerCustomer } from '../api/authApi'
import { registerCustomerSchema, type RegisterCustomerFormValues } from '../schemas'
import { useSubmitWithApiErrors } from '../hooks/useSubmitWithApiErrors'

export function RegisterCustomerForm() {
  const navigate = useNavigate()
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<RegisterCustomerFormValues>({ resolver: zodResolver(registerCustomerSchema) })
  const submitWithApiErrors = useSubmitWithApiErrors(setError)

  const onSubmit = (values: RegisterCustomerFormValues) =>
    submitWithApiErrors(async () => {
      await registerCustomer(values)
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
      <Button type="submit" isLoading={isSubmitting} className="w-full">
        Create account
      </Button>
    </form>
  )
}
