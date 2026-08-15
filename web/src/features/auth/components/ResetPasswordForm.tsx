import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useNavigate, useSearchParams } from 'react-router-dom'
import toast from 'react-hot-toast'
import { Button, Input } from '@/components'
import { resetPassword } from '../api/authApi'
import { resetPasswordSchema, type ResetPasswordFormValues } from '../schemas'
import { useSubmitWithApiErrors } from '../hooks/useSubmitWithApiErrors'

export function ResetPasswordForm() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const token = searchParams.get('token') ?? ''
  const email = searchParams.get('email') ?? ''

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<ResetPasswordFormValues>({ resolver: zodResolver(resetPasswordSchema) })
  const submitWithApiErrors = useSubmitWithApiErrors(setError)

  const onSubmit = (values: ResetPasswordFormValues) =>
    submitWithApiErrors(async () => {
      await resetPassword({ ...values, token, email })
      toast.success('Password reset. Please sign in.')
      navigate('/login', { replace: true })
    }, 'Could not reset your password. The link may have expired.')

  if (!token || !email) {
    return <p className="text-sm text-red-600">This reset link is invalid or incomplete.</p>
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
      <Input
        label="New password"
        type="password"
        autoComplete="new-password"
        error={errors.password?.message}
        {...register('password')}
      />
      <Input
        label="Confirm new password"
        type="password"
        autoComplete="new-password"
        error={errors.password_confirmation?.message}
        {...register('password_confirmation')}
      />
      <Button type="submit" isLoading={isSubmitting} className="w-full">
        Reset password
      </Button>
    </form>
  )
}
