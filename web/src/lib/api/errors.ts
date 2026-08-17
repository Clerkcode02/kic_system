import { AxiosError } from 'axios'

export type ApiErrorKind = 'validation' | 'forbidden' | 'conflict' | 'rate_limited' | 'unknown'

export class ApiError extends Error {
  readonly kind: ApiErrorKind
  readonly status: number | undefined
  readonly fieldErrors: Record<string, string[]>
  /** The backend's machine-readable error code, e.g. "milestone_not_funded" (see ConflictException::$errorCode). */
  readonly code: string | undefined

  constructor(
    message: string,
    kind: ApiErrorKind,
    status: number | undefined,
    fieldErrors: Record<string, string[]> = {},
    code?: string,
  ) {
    super(message)
    this.name = 'ApiError'
    this.kind = kind
    this.status = status
    this.fieldErrors = fieldErrors
    this.code = code
  }
}

function kindForStatus(status: number | undefined): ApiErrorKind {
  switch (status) {
    case 422:
      return 'validation'
    case 403:
      return 'forbidden'
    case 409:
      return 'conflict'
    case 429:
      return 'rate_limited'
    default:
      return 'unknown'
  }
}

export function normalizeApiError(error: unknown): ApiError {
  if (error instanceof ApiError) return error

  if (error instanceof AxiosError) {
    const status = error.response?.status
    const body = error.response?.data as
      { message?: string; errors?: Record<string, string[]>; code?: string } | undefined
    const kind = kindForStatus(status)
    const message = body?.message ?? error.message ?? 'Something went wrong. Please try again.'
    return new ApiError(message, kind, status, body?.errors ?? {}, body?.code)
  }

  const message = error instanceof Error ? error.message : 'Something went wrong. Please try again.'
  return new ApiError(message, 'unknown', undefined)
}
