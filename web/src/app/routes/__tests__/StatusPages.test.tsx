import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { VerifyPendingPage } from '../StatusPages'

function renderVerifyPending(email?: string) {
  return render(
    <MemoryRouter
      initialEntries={[
        {
          pathname: '/verify-pending',
          state: email ? { email } : null,
        },
      ]}
    >
      <Routes>
        <Route path="/verify-pending" element={<VerifyPendingPage />} />
      </Routes>
    </MemoryRouter>,
  )
}

describe('VerifyPendingPage', () => {
  it('prefills the resend email from registration navigation state', () => {
    renderVerifyPending('provider@example.com')

    expect(screen.getByLabelText(/resend to/i)).toHaveValue('provider@example.com')
  })

  it('owns a light page surface for readable auth status UI', () => {
    const { container } = renderVerifyPending()

    expect(container.firstChild).toHaveClass('bg-gray-50', 'text-gray-900')
  })
})
