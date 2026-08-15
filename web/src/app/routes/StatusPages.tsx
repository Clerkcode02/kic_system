import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import toast from 'react-hot-toast'
import { Button, EmptyState, Input } from '@/components'
import { ApiError } from '@/lib/api'
import { resendEmailVerification } from '@/features/auth/api/authApi'

const resendSchema = z.object({ email: z.string().email('Enter a valid email address') })
type ResendFormValues = z.infer<typeof resendSchema>

export function UnauthorizedPage() {
  return (
    <div className="flex min-h-svh items-center justify-center px-4">
      <EmptyState
        title="You don't have access to this page"
        description="Your account role doesn't permit this action. If you think this is a mistake, contact support."
        action={
          <Link to="/" className="text-sm font-medium text-blue-600 hover:underline">
            Back home
          </Link>
        }
      />
    </div>
  )
}

export function VerifyPendingPage() {
  const [isSent, setIsSent] = useState(false)
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<ResendFormValues>({ resolver: zodResolver(resendSchema) })

  const onSubmit = async (values: ResendFormValues) => {
    try {
      await resendEmailVerification(values.email)
      setIsSent(true)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not resend the email.')
    }
  }

  return (
    <div className="flex min-h-svh items-center justify-center px-4">
      <div className="w-full max-w-sm">
        <EmptyState
          title="Check your email"
          description="We've sent a verification link to your inbox. Verify your email, then sign in."
        />
        {isSent ? (
          <p className="mt-4 text-center text-sm text-gray-500">Verification email sent.</p>
        ) : (
          <form onSubmit={handleSubmit(onSubmit)} className="mt-4 flex flex-col gap-3">
            <Input
              label="Didn't get it? Resend to"
              type="email"
              autoComplete="email"
              error={errors.email?.message}
              {...register('email')}
            />
            <Button type="submit" variant="secondary" isLoading={isSubmitting} className="w-full">
              Resend verification email
            </Button>
          </form>
        )}
        <p className="mt-6 text-center text-sm">
          <Link to="/login" className="font-medium text-blue-600 hover:underline">
            Back to sign in
          </Link>
        </p>
      </div>
    </div>
  )
}

export function SuspendedPage() {
  return (
    <div className="flex min-h-svh items-center justify-center px-4">
      <EmptyState
        title="Account suspended"
        description="Your account has been suspended. Contact support for more information."
      />
    </div>
  )
}

export function NotFoundPage() {
  return (
    <div className="flex min-h-svh items-center justify-center px-4">
      <EmptyState
        title="Page not found"
        action={
          <Link to="/" className="text-sm font-medium text-blue-600 hover:underline">
            Back home
          </Link>
        }
      />
    </div>
  )
}
