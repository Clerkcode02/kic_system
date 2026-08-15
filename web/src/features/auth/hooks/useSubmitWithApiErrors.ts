import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import toast from 'react-hot-toast'
import { ApiError } from '@/lib/api'

/**
 * Wraps a submit handler so backend 422 responses map onto the matching
 * form fields (mirrors the pattern in LoginForm) and everything else
 * surfaces as a toast instead of an unhandled rejection.
 */
export function useSubmitWithApiErrors<TValues extends FieldValues>(
  setError: UseFormSetError<TValues>,
) {
  return async (submit: () => Promise<void>, fallbackMessage: string) => {
    try {
      await submit()
    } catch (error) {
      if (error instanceof ApiError && error.kind === 'validation') {
        for (const [field, messages] of Object.entries(error.fieldErrors)) {
          setError(field as Path<TValues>, { message: messages[0] })
        }
        return
      }
      toast.error(error instanceof ApiError ? error.message : fallbackMessage)
    }
  }
}
