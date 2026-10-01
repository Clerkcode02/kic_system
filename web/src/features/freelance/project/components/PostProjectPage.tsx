import { useState } from 'react'
import toast from 'react-hot-toast'
import { useNavigate } from 'react-router-dom'
import { Button, Card, Input, Select } from '@/components'
import { ApiError } from '@/lib/api'
import { uploadProjectAttachment } from '../api/projectApi'
import { useCreateProject, useProjectCategories } from '../hooks/useProjects'
import { SkillsInput } from './SkillsInput'

const TITLE_MAX_LENGTH = 255
const DESCRIPTION_MAX_LENGTH = 10000

/** Matches StoreProjectRequest's `after:today`. */
function tomorrow(): string {
  const date = new Date()
  date.setDate(date.getDate() + 1)
  return date.toISOString().slice(0, 10)
}

export function PostProjectPage() {
  const [categoryId, setCategoryId] = useState('')
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [budgetMin, setBudgetMin] = useState('')
  const [budgetMax, setBudgetMax] = useState('')
  const [deadline, setDeadline] = useState('')
  const [skills, setSkills] = useState<string[]>([])
  const [files, setFiles] = useState<File[]>([])
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  const { data: categories, isLoading: categoriesLoading } = useProjectCategories()
  const { mutateAsync, isPending } = useCreateProject()
  const navigate = useNavigate()

  const categoryOptions = [
    { value: '', label: 'Select a category' },
    ...(categories ?? []).map((c) => ({ value: c.id, label: c.name })),
  ]

  /**
   * Mirrors the server rules for immediate feedback. PublishProject
   * re-validates and remains the source of truth — this never replaces it.
   */
  const validate = (): Record<string, string> => {
    const errors: Record<string, string> = {}
    if (!categoryId) errors.category_id = 'Choose a category.'
    if (!title.trim()) errors.title = 'Give your project a title.'
    if (!description.trim()) errors.description = 'Describe the work you need done.'

    const min = Number(budgetMin)
    const max = Number(budgetMax)
    if (!budgetMin || Number.isNaN(min) || min <= 0) {
      errors.budget_min = 'Enter a minimum budget greater than zero.'
    }
    if (!budgetMax || Number.isNaN(max)) {
      errors.budget_max = 'Enter a maximum budget.'
    } else if (max < min) {
      errors.budget_max = 'The maximum must be at least the minimum.'
    }
    if (!deadline) {
      errors.deadline = 'Choose a deadline.'
    } else if (deadline < tomorrow()) {
      errors.deadline = 'The deadline must be in the future.'
    }
    return errors
  }

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault()

    const errors = validate()
    setFieldErrors(errors)
    if (Object.keys(errors).length > 0) return

    try {
      const project = await mutateAsync({
        category_id: categoryId,
        title: title.trim(),
        description: description.trim(),
        budget_min: Number(budgetMin),
        budget_max: Number(budgetMax),
        deadline,
        ...(skills.length > 0 ? { required_skills: skills } : {}),
      })

      // Attachments need the project's id, so they upload after creation.
      // A failed upload must not read as a failed publish — the project is
      // already live at this point.
      if (files.length > 0) {
        const results = await Promise.allSettled(
          files.map((file) => uploadProjectAttachment(project.id, file, () => {})),
        )
        const failed = results.filter((r) => r.status === 'rejected').length
        if (failed > 0) {
          toast.error(
            `Project published, but ${failed} of ${files.length} file(s) failed to upload. You can add them again from the project page.`,
          )
        }
      }

      toast.success('Project published. Freelancers can now send proposals.')
      navigate(`/customer/projects/${project.id}`)
    } catch (error) {
      // fieldErrors is always present (defaults to {}), so emptiness is
      // what distinguishes a 422 from any other failure.
      if (error instanceof ApiError && Object.keys(error.fieldErrors).length > 0) {
        setFieldErrors(
          Object.fromEntries(
            Object.entries(error.fieldErrors).map(([field, messages]) => [field, messages[0]]),
          ),
        )
        toast.error('Please fix the highlighted fields.')
        return
      }
      toast.error(error instanceof ApiError ? error.message : 'Could not publish your project.')
    }
  }

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <div>
        <h1 className="text-lg font-semibold text-gray-900">Post a project</h1>
        <p className="mt-1 text-sm text-gray-500">
          Describe the work and your budget. Freelancers will send you proposals to compare.
        </p>
      </div>

      <form onSubmit={handleSubmit} noValidate>
        <Card>
          <div className="flex flex-col gap-4">
            <Select
              label="Category"
              name="category_id"
              options={categoryOptions}
              value={categoryId}
              disabled={categoriesLoading}
              error={fieldErrors.category_id}
              onChange={(event) => setCategoryId(event.target.value)}
            />

            <Input
              label="Project title"
              name="title"
              maxLength={TITLE_MAX_LENGTH}
              placeholder="Build a marketing site for a local bakery"
              value={title}
              error={fieldErrors.title}
              onChange={(event) => setTitle(event.target.value)}
            />

            <div className="flex flex-col gap-1">
              <label htmlFor="project-description" className="text-sm font-medium text-gray-700">
                Description
              </label>
              <textarea
                id="project-description"
                rows={8}
                maxLength={DESCRIPTION_MAX_LENGTH}
                placeholder="What needs doing, what does done look like, and anything a freelancer should know before quoting."
                value={description}
                onChange={(event) => setDescription(event.target.value)}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              <div className="flex items-center justify-between">
                {fieldErrors.description ? (
                  <p className="text-xs text-red-600">{fieldErrors.description}</p>
                ) : (
                  <span />
                )}
                <p className="text-xs text-gray-400">
                  {description.length}/{DESCRIPTION_MAX_LENGTH}
                </p>
              </div>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Input
                label="Minimum budget (CAD)"
                name="budget_min"
                type="number"
                min={0}
                step="0.01"
                value={budgetMin}
                error={fieldErrors.budget_min}
                onChange={(event) => setBudgetMin(event.target.value)}
              />
              <Input
                label="Maximum budget (CAD)"
                name="budget_max"
                type="number"
                min={0}
                step="0.01"
                value={budgetMax}
                error={fieldErrors.budget_max}
                onChange={(event) => setBudgetMax(event.target.value)}
              />
            </div>

            <Input
              label="Deadline"
              name="deadline"
              type="date"
              min={tomorrow()}
              value={deadline}
              error={fieldErrors.deadline}
              onChange={(event) => setDeadline(event.target.value)}
            />

            <SkillsInput value={skills} onChange={setSkills} disabled={isPending} />

            <div className="flex flex-col gap-1">
              <label htmlFor="project-files" className="text-sm font-medium text-gray-700">
                Brief or reference files{' '}
                <span className="font-normal text-gray-400">(optional)</span>
              </label>
              <input
                id="project-files"
                type="file"
                multiple
                onChange={(event) => setFiles(Array.from(event.target.files ?? []))}
                className="text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200"
              />
              <p className="text-xs text-gray-400">
                Uploaded once the project is published, so your files attach to it.
              </p>
            </div>

            <div className="flex items-center gap-3">
              <Button type="submit" isLoading={isPending}>
                Publish project
              </Button>
              <Button
                type="button"
                variant="secondary"
                disabled={isPending}
                onClick={() => navigate('/customer/projects')}
              >
                Cancel
              </Button>
            </div>
            <p className="text-xs text-gray-400">
              Publishing makes your project visible to freelancers right away.
            </p>
          </div>
        </Card>
      </form>
    </div>
  )
}
