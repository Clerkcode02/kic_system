import { useEffect, useRef, useState } from 'react'
import { EmptyState, Skeleton } from '@/components'
import { useInfiniteProjects } from '../hooks/useProjects'
import type { ProjectListFilters } from '../types'
import { ProjectCard } from './ProjectCard'
import { ProjectFilterBar } from './ProjectFilterBar'

export function ProjectBrowsePage() {
  const [category, setCategory] = useState('')
  const [budgetMinInput, setBudgetMinInput] = useState('')
  const [budgetMaxInput, setBudgetMaxInput] = useState('')
  const [appliedBudget, setAppliedBudget] = useState<{ min?: number; max?: number }>({})
  const sentinelRef = useRef<HTMLDivElement | null>(null)

  const filters: ProjectListFilters = {
    ...(category ? { category } : {}),
    ...(appliedBudget.min !== undefined ? { budget_min: appliedBudget.min } : {}),
    ...(appliedBudget.max !== undefined ? { budget_max: appliedBudget.max } : {}),
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

  const handleApplyBudget = () => {
    setAppliedBudget({
      min: budgetMinInput ? Number(budgetMinInput) : undefined,
      max: budgetMaxInput ? Number(budgetMaxInput) : undefined,
    })
  }

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <h1 className="text-lg font-semibold text-gray-900">Browse projects</h1>

      <ProjectFilterBar
        category={category}
        budgetMin={budgetMinInput}
        budgetMax={budgetMaxInput}
        onCategoryChange={setCategory}
        onBudgetMinChange={setBudgetMinInput}
        onBudgetMaxChange={setBudgetMaxInput}
        onApplyBudget={handleApplyBudget}
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
          description="Try widening your budget range or clearing the category filter."
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
