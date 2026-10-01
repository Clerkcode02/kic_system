import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { EmptyState, Skeleton } from '@/components'
import { useAuth } from '@/app/providers/useAuth'
import { useInfiniteProjects } from '../hooks/useProjects'
import type { ProjectListFilters } from '../types'
import { ProjectCard } from './ProjectCard'
import { ProjectFilterBar } from './ProjectFilterBar'

export function ProjectBrowsePage() {
  const [category, setCategory] = useState('')
  const [budgetMinInput, setBudgetMinInput] = useState('')
  const [budgetMaxInput, setBudgetMaxInput] = useState('')
  const [skillsInput, setSkillsInput] = useState('')
  const [appliedBudget, setAppliedBudget] = useState<{ min?: number; max?: number }>({})
  const [appliedSkills, setAppliedSkills] = useState<string[]>([])
  const sentinelRef = useRef<HTMLDivElement | null>(null)
  const { isAuthenticated, user } = useAuth()

  const filters: ProjectListFilters = {
    ...(category ? { category } : {}),
    ...(appliedBudget.min !== undefined ? { budget_min: appliedBudget.min } : {}),
    ...(appliedBudget.max !== undefined ? { budget_max: appliedBudget.max } : {}),
    ...(appliedSkills.length > 0 ? { skills: appliedSkills } : {}),
  }

  const { data, isLoading, isError, fetchNextPage, hasNextPage, isFetchingNextPage } =
    useInfiniteProjects(filters)
  const projects = data?.pages.flatMap((page) => page.data) ?? []

  useEffect(() => {
    const sentinel = sentinelRef.current
    if (!sentinel) return
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && hasNextPage && !isFetchingNextPage) fetchNextPage()
      },
      { rootMargin: '200px' },
    )
    observer.observe(sentinel)
    return () => observer.disconnect()
  }, [fetchNextPage, hasNextPage, isFetchingNextPage])

  const handleApplyFilters = () => {
    setAppliedBudget({
      min: budgetMinInput ? Number(budgetMinInput) : undefined,
      max: budgetMaxInput ? Number(budgetMaxInput) : undefined,
    })
    setAppliedSkills(
      skillsInput
        .split(',')
        .map((skill) => skill.trim())
        .filter((skill) => skill !== ''),
    )
  }

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-lg font-semibold text-gray-900">Browse projects</h1>
        {/*
          Secondary entry point into posting (the primary one is the
          customer dashboard nav). Shown only to signed-in customers:
          posting requires an account, and surfacing it to a freelancer or
          an anonymous visitor would lead to a 403 or a login bounce.
        */}
        {isAuthenticated && user?.role === 'customer' && (
          <Link to="/customer/projects/new" className="text-sm font-medium text-blue-600 underline">
            Post a project
          </Link>
        )}
      </div>

      <ProjectFilterBar
        category={category}
        budgetMin={budgetMinInput}
        budgetMax={budgetMaxInput}
        onCategoryChange={setCategory}
        onBudgetMinChange={setBudgetMinInput}
        onBudgetMaxChange={setBudgetMaxInput}
        skills={skillsInput}
        onSkillsChange={setSkillsInput}
        onApplyBudget={handleApplyFilters}
      />

      {isLoading && (
        <div className="flex flex-col gap-3">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} className="h-24 rounded-lg" />
          ))}
        </div>
      )}

      {isError && <EmptyState title="Couldn't load projects" description="Please try again." />}

      {!isLoading && !isError && projects.length === 0 && (
        <EmptyState
          title="No projects match your filters"
          description="Try widening your budget range, or clearing the skills or category filter."
        />
      )}

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        {projects.map((project) => (
          <ProjectCard key={project.id} project={project} />
        ))}
      </div>
      <div ref={sentinelRef} className="h-4" />
      {isFetchingNextPage && (
        <p className="py-2 text-center text-sm text-gray-500">Loading more…</p>
      )}
    </div>
  )
}
