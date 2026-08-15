import { AxiosError } from 'axios'

export type ApiErrorKind = 'validation' | 'forbidden' | 'conflict' | 'rate_limited' | 'unknown'

export class ApiError extends Error {
  readonly kind: ApiErrorKind
  readonly status: number | undefined
  readonly fieldErrors: Record<string, string[]>

  constructor(
    message: string,
    kind: ApiErrorKind,
    status: number | undefined,
    fieldErrors: Record<string, string[]> = {},
  ) {
    super(message)
    this.name = 'ApiError'
    this.kind = kind
    this.status = status
    this.fieldErrors = fieldErrors
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
      { message?: string; errors?: Record<string, string[]> } | undefined
    const kind = kindForStatus(status)
    const message = body?.message ?? error.message ?? 'Something went wrong. Please try again.'
    return new ApiError(message, kind, status, body?.errors ?? {})
  }

  const message = error instanceof Error ? error.message : 'Something went wrong. Please try again.'
  return new ApiError(message, 'unknown', undefined)
}
