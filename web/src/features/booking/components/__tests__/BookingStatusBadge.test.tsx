import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { BookingStatusBadge, BOOKING_STATUS_LABELS } from '../BookingStatusBadge'
import type { BookingStatus } from '../../types'

describe('BookingStatusBadge', () => {
  it('renders the human-readable label for every booking status', () => {
    for (const status of Object.keys(BOOKING_STATUS_LABELS) as BookingStatus[]) {
      const { unmount } = render(<BookingStatusBadge status={status} />)
      expect(screen.getByText(BOOKING_STATUS_LABELS[status])).toBeInTheDocument()
      unmount()
    }
  })
})
