import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Input } from '@/components'
import { forgotPassword } from '../api/authApi'
import { forgotPasswordSchema, type ForgotPasswordFormValues } from '../schemas'
import { useSubmitWithApiErrors } from '../hooks/useSubmitWithApiErrors'

export function ForgotPasswordForm() {
  const [isSent, setIsSent] = useState(false)
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<ForgotPasswordFormValues>({ resolver: zodResolver(forgotPasswordSchema) })
  const submitWithApiErrors = useSubmitWithApiErrors(setError)

  const onSubmit = (values: ForgotPasswordFormValues) =>
    submitWithApiErrors(async () => {
      await forgotPassword(values)
      setIsSent(true)
    }, 'Could not send the reset link. Please try again.')

  if (isSent) {
    return (
      <p className="text-sm text-gray-700">
        If an account exists for that email, a password reset link is on its way.
      </p>
    )
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
      <Input
        label="Email"
        type="email"
        autoComplete="email"
        error={errors.email?.message}
        {...register('email')}
      />
      <Button type="submit" isLoading={isSubmitting} className="w-full">
        Send reset link
      </Button>
    </form>
  )
}
