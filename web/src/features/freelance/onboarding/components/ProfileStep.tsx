import { useState } from 'react'
import toast from 'react-hot-toast'
import { Button, Input } from '@/components'
import { ApiError } from '@/lib/api'
import { useUpdateFreelancerProfile } from '../hooks/useFreelancerProfile'
import type { FreelancerProfile } from '../types'

interface ProfileStepProps {
  profile: FreelancerProfile
  onNext: () => void
}

export function ProfileStep({ profile, onNext }: ProfileStepProps) {
  const [headline, setHeadline] = useState(profile.headline)
  const [bio, setBio] = useState(profile.bio)
  const [hourlyRate, setHourlyRate] = useState(profile.hourly_rate)
  const { mutateAsync, isPending } = useUpdateFreelancerProfile()

  const handleSubmit = async () => {
    try {
      await mutateAsync({ headline, bio, hourly_rate: hourlyRate })
      onNext()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not save your profile.')
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <Input label="Headline" value={headline} onChange={(e) => setHeadline(e.target.value)} />
      <div className="flex flex-col gap-1">
        <label htmlFor="freelancer-bio" className="text-sm font-medium text-gray-700">
          Bio
        </label>
        <textarea
          id="freelancer-bio"
          rows={4}
          value={bio}
          onChange={(e) => setBio(e.target.value)}
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      </div>
      <Input
        label="Hourly rate (CAD)"
        type="number"
        min={0}
        step="0.01"
        value={hourlyRate}
        onChange={(e) => setHourlyRate(Number(e.target.value))}
      />
      <Button type="button" isLoading={isPending} onClick={handleSubmit} className="w-full">
        Continue
      </Button>
    </div>
  )
}
