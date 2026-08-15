import { useState } from 'react'
import { Card, Skeleton } from '@/components'
import { useMyFreelancerProfile } from '../hooks/useFreelancerProfile'
import { ProfileStep } from './ProfileStep'
import { SkillsStep } from './SkillsStep'
import { PortfolioStep } from './PortfolioStep'
import { FinishStep } from './FinishStep'

const STEPS = ['Profile', 'Skills', 'Portfolio', 'Finish'] as const

export function OnboardingWizard() {
  const { data: profile, isLoading } = useMyFreelancerProfile()
  const [stepIndex, setStepIndex] = useState(0)

  if (isLoading) {
    return (
      <div className="p-6">
        <Skeleton className="h-8 w-48" />
      </div>
    )
  }

  if (!profile) {
    return null
  }

  return (
    <div className="mx-auto flex min-h-svh max-w-lg flex-col justify-center px-4 py-8">
      <Card>
        <ol className="mb-6 flex justify-between text-xs font-medium text-gray-500">
          {STEPS.map((label, index) => (
            <li key={label} className={index === stepIndex ? 'text-blue-600' : undefined}>
              {index + 1}. {label}
            </li>
          ))}
        </ol>

        {stepIndex === 0 && <ProfileStep profile={profile} onNext={() => setStepIndex(1)} />}
        {stepIndex === 1 && (
          <SkillsStep
            profile={profile}
            onNext={() => setStepIndex(2)}
            onBack={() => setStepIndex(0)}
          />
        )}
        {stepIndex === 2 && (
          <PortfolioStep onNext={() => setStepIndex(3)} onBack={() => setStepIndex(1)} />
        )}
        {stepIndex === 3 && <FinishStep onBack={() => setStepIndex(2)} />}
      </Card>
    </div>
  )
}
