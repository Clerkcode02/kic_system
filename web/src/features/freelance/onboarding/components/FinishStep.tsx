import { useNavigate } from 'react-router-dom'
import { Button, EmptyState } from '@/components'

interface FinishStepProps {
  onBack: () => void
}

export function FinishStep({ onBack }: FinishStepProps) {
  const navigate = useNavigate()

  return (
    <div className="flex flex-col gap-4">
      <EmptyState
        title="You're all set"
        description="Your profile is complete. Our team reviews new freelancer profiles before you can submit proposals."
      />
      <div className="flex gap-3">
        <Button type="button" variant="secondary" onClick={onBack} className="flex-1">
          Back
        </Button>
        <Button
          type="button"
          onClick={() => navigate('/freelancer', { replace: true })}
          className="flex-1"
        >
          Go to dashboard
        </Button>
      </div>
    </div>
  )
}
