import { useEffect, useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { Card } from '@/components'
import { ApiError } from '@/lib/api'
import { verifyEmail } from '@/features/auth/api/authApi'

type Status = 'verifying' | 'success' | 'error'

export function VerifyEmailPage() {
  const { id, hash } = useParams<{ id: string; hash: string }>()
  const [searchParams] = useSearchParams()
  const [status, setStatus] = useState<Status>('verifying')
  const [message, setMessage] = useState<string | null>(null)

  useEffect(() => {
    if (!id || !hash) {
      setStatus('error')
      setMessage('This verification link is missing information.')
      return
    }

    const query = searchParams.toString()
    const hashWithQuery = query ? `${hash}?${query}` : hash

    verifyEmail(id, hashWithQuery)
      .then(() => setStatus('success'))
      .catch((error: unknown) => {
        setStatus('error')
        setMessage(
          error instanceof ApiError
            ? error.message
            : 'This verification link is invalid or expired.',
        )
      })
  }, [id, hash, searchParams])

  return (
    <div className="flex min-h-svh items-center justify-center bg-gray-50 px-4">
      <Card className="w-full max-w-sm text-center">
        {status === 'verifying' && <p className="text-sm text-gray-700">Verifying your email…</p>}
        {status === 'success' && (
          <>
            <h1 className="mb-2 text-lg font-semibold text-gray-900">Email verified</h1>
            <p className="mb-4 text-sm text-gray-500">You can now sign in to your account.</p>
            <Link to="/login" className="text-sm font-medium text-blue-600 hover:underline">
              Sign in
            </Link>
          </>
        )}
        {status === 'error' && (
          <>
            <h1 className="mb-2 text-lg font-semibold text-gray-900">Verification failed</h1>
            <p className="text-sm text-red-600">{message}</p>
          </>
        )}
      </Card>
    </div>
  )
}
