import { useState } from 'react'
import toast from 'react-hot-toast'
import { Button, Input, Select } from '@/components'
import { ApiError } from '@/lib/api'
import { useReplaceMySkills } from '../hooks/useFreelancerProfile'
import type { FreelancerProfile, SkillProficiencyLevel } from '../types'

interface SkillsStepProps {
  profile: FreelancerProfile
  onNext: () => void
  onBack: () => void
}

interface DraftSkill {
  skill_name: string
  proficiency_level: SkillProficiencyLevel
}

const PROFICIENCY_OPTIONS: { value: SkillProficiencyLevel; label: string }[] = [
  { value: 'beginner', label: 'Beginner' },
  { value: 'intermediate', label: 'Intermediate' },
  { value: 'expert', label: 'Expert' },
]

export function SkillsStep({ profile, onNext, onBack }: SkillsStepProps) {
  const [skills, setSkills] = useState<DraftSkill[]>(
    profile.skills.map((skill) => ({
      skill_name: skill.skill_name,
      proficiency_level: skill.proficiency_level,
    })),
  )
  const [name, setName] = useState('')
  const [level, setLevel] = useState<SkillProficiencyLevel>('intermediate')
  const { mutateAsync, isPending } = useReplaceMySkills()

  const addSkill = () => {
    if (!name.trim()) return
    setSkills((prev) => [...prev, { skill_name: name.trim(), proficiency_level: level }])
    setName('')
  }

  const removeSkill = (index: number) => {
    setSkills((prev) => prev.filter((_, i) => i !== index))
  }

  const handleSubmit = async () => {
    try {
      await mutateAsync(skills)
      onNext()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not save your skills.')
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
        <div className="flex-1">
          <Input label="Skill" value={name} onChange={(e) => setName(e.target.value)} />
        </div>
        <div className="w-40">
          <Select
            label="Level"
            options={PROFICIENCY_OPTIONS}
            value={level}
            onChange={(e) => setLevel(e.target.value as SkillProficiencyLevel)}
          />
        </div>
        <Button type="button" variant="secondary" onClick={addSkill}>
          Add
        </Button>
      </div>

      {skills.length > 0 && (
        <ul className="flex flex-wrap gap-2">
          {skills.map((skill, index) => (
            <li
              key={`${skill.skill_name}-${index}`}
              className="flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700"
            >
              {skill.skill_name} <span className="text-gray-400">({skill.proficiency_level})</span>
              <button
                type="button"
                onClick={() => removeSkill(index)}
                className="text-gray-400 hover:text-gray-600"
                aria-label={`Remove ${skill.skill_name}`}
              >
                ×
              </button>
            </li>
          ))}
        </ul>
      )}

      <div className="flex gap-3">
        <Button type="button" variant="secondary" onClick={onBack} className="flex-1">
          Back
        </Button>
        <Button type="button" isLoading={isPending} onClick={handleSubmit} className="flex-1">
          Continue
        </Button>
      </div>
    </div>
  )
}
