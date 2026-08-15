import { useState } from 'react'
import toast from 'react-hot-toast'
import { Button, Input } from '@/components'
import { ApiError } from '@/lib/api'
import { BusinessHoursInput } from './BusinessHoursInput'
import { useUpdateBusinessProfile } from '../hooks/useBusiness'
import type { Business } from '../types'

interface ProfileStepProps {
  business: Business
  onNext: () => void
}

export function ProfileStep({ business, onNext }: ProfileStepProps) {
  const [legalName, setLegalName] = useState(business.legal_name)
  const [maxBookingsPerDay, setMaxBookingsPerDay] = useState(business.max_bookings_per_day)
  const [businessHours, setBusinessHours] = useState(business.business_hours)
  const { mutateAsync, isPending } = useUpdateBusinessProfile()

  const handleSubmit = async () => {
    try {
      await mutateAsync({
        legal_name: legalName,
        max_bookings_per_day: maxBookingsPerDay,
        business_hours: businessHours,
      })
      onNext()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not save your profile.')
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <Input
        label="Legal business name"
        value={legalName}
        onChange={(e) => setLegalName(e.target.value)}
      />
      <Input
        label="Max bookings per day"
        type="number"
        min={1}
        value={maxBookingsPerDay}
        onChange={(e) => setMaxBookingsPerDay(Number(e.target.value))}
      />
      <BusinessHoursInput value={businessHours} onChange={setBusinessHours} />
      <Button type="button" isLoading={isPending} onClick={handleSubmit} className="w-full">
        Continue
      </Button>
    </div>
  )
}
