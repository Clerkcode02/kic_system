import { useState } from 'react'
import toast from 'react-hot-toast'
import { Button, Modal } from '@/components'
import { ApiError } from '@/lib/api'
import { useCancelBooking } from '../hooks/useCancelBooking'
import { FEE_APPLIES_STATUSES, type BookingStatus } from '../types'

interface CancelBookingModalProps {
  bookingId: string
  status: BookingStatus
  isOpen: boolean
  onClose: () => void
}

export function CancelBookingModal({
  bookingId,
  status,
  isOpen,
  onClose,
}: CancelBookingModalProps) {
  const [reason, setReason] = useState('')
  const { mutateAsync, isPending } = useCancelBooking(bookingId)
  const feeApplies = FEE_APPLIES_STATUSES.includes(status)

  const handleCancel = async () => {
    try {
      await mutateAsync(reason || undefined)
      toast.success('Booking cancelled.')
      onClose()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not cancel this booking.')
    }
  }

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="Cancel booking">
      <div className="flex flex-col gap-4">
        {feeApplies && (
          <p className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
            This booking has already been accepted. Cancelling now may incur a cancellation fee.
          </p>
        )}
        <div className="flex flex-col gap-1">
          <label htmlFor="cancel-reason" className="text-sm font-medium text-gray-700">
            Reason (optional)
          </label>
          <textarea
            id="cancel-reason"
            rows={3}
            maxLength={500}
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
        <div className="flex gap-3">
          <Button
            type="button"
            variant="secondary"
            onClick={onClose}
            disabled={isPending}
            className="flex-1"
          >
            Keep booking
          </Button>
          <Button
            type="button"
            variant="danger"
            isLoading={isPending}
            onClick={handleCancel}
            className="flex-1"
          >
            Cancel booking
          </Button>
        </div>
      </div>
    </Modal>
  )
}
