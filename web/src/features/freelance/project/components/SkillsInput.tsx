import { useId, useMemo, useState } from 'react'

const MAX_SKILLS = 20
const MAX_SKILL_LENGTH = 50

interface SkillsInputProps {
  value: string[]
  onChange: (skills: string[]) => void
  /**
   * Spellings already in use by freelancers. Offered as suggestions so
   * clients converge on them — skills are free text on both sides, so
   * "React" and "ReactJS" stay distinct skills and a mismatch means the
   * project never surfaces in that freelancer's filter.
   */
  suggestions?: string[]
  disabled?: boolean
}

export function SkillsInput({
  value,
  onChange,
  suggestions = [],
  disabled = false,
}: SkillsInputProps) {
  const [draft, setDraft] = useState('')
  const inputId = useId()
  const listId = useId()

  const atCapacity = value.length >= MAX_SKILLS

  // The server lowercases and collapses whitespace; mirroring that here
  // means the chip the client sees is the value that gets stored.
  const normalize = (raw: string) => raw.trim().replace(/\s+/g, ' ').toLowerCase()

  const unusedSuggestions = useMemo(
    () => suggestions.map(normalize).filter((s) => !value.includes(s)),
    [suggestions, value],
  )

  const add = (raw: string) => {
    const skill = normalize(raw).slice(0, MAX_SKILL_LENGTH)
    if (!skill || value.includes(skill) || atCapacity) {
      setDraft('')
      return
    }
    onChange([...value, skill])
    setDraft('')
  }

  const remove = (skill: string) => onChange(value.filter((s) => s !== skill))

  return (
    <div className="flex flex-col gap-2">
      <label htmlFor={inputId} className="text-sm font-medium text-gray-700">
        Required skills <span className="font-normal text-gray-400">(optional)</span>
      </label>

      {value.length > 0 && (
        <ul className="flex flex-wrap gap-2" aria-label="Selected skills">
          {value.map((skill) => (
            <li key={skill}>
              <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 py-1 pl-3 pr-1 text-sm text-blue-800">
                {skill}
                <button
                  type="button"
                  onClick={() => remove(skill)}
                  disabled={disabled}
                  aria-label={`Remove ${skill}`}
                  className="flex h-5 w-5 items-center justify-center rounded-full text-blue-500 hover:bg-blue-100 hover:text-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-600 disabled:opacity-50"
                >
                  <span aria-hidden="true">&times;</span>
                </button>
              </span>
            </li>
          ))}
        </ul>
      )}

      <input
        id={inputId}
        list={listId}
        type="text"
        value={draft}
        disabled={disabled || atCapacity}
        maxLength={MAX_SKILL_LENGTH}
        placeholder={
          atCapacity ? `Limit of ${MAX_SKILLS} skills reached` : 'e.g. react, then Enter'
        }
        onChange={(event) => setDraft(event.target.value)}
        onKeyDown={(event) => {
          // Enter and comma both commit. Enter must not submit the
          // surrounding form — the field is part of a larger project form.
          if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault()
            add(draft)
          }
          if (event.key === 'Backspace' && draft === '' && value.length > 0) {
            remove(value[value.length - 1])
          }
        }}
        onBlur={() => draft && add(draft)}
        className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50"
      />

      <datalist id={listId}>
        {unusedSuggestions.map((skill) => (
          <option key={skill} value={skill} />
        ))}
      </datalist>

      <p className="text-xs text-gray-400">
        Press Enter or comma to add. Freelancers can filter projects by these skills.
      </p>
    </div>
  )
}
