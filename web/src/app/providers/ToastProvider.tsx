import { Toaster } from 'react-hot-toast'
import type { ReactNode } from 'react'

export function ToastProvider({ children }: { children: ReactNode }) {
  return (
    <>
      {children}
      <Toaster position="top-right" toastOptions={{ duration: 4000 }} />
    </>
  )
}
