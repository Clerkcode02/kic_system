import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { ProjectCard } from '../ProjectCard'
import type { ProjectListItem } from '../../types'

const project: ProjectListItem = {
  id: 'proj-1',
  title: 'Rebuild the marketing site',
  budget_min: '2000.00',
  budget_max: '5000.00',
  currency: 'CAD',
  deadline: '2026-10-01',
  status: 'open',
  category: { id: 'cat-1', name: 'Web development' },
  created_at: '2026-08-01T00:00:00Z',
}

describe('ProjectCard', () => {
  it('links to the public project URL, not a dashboard-scoped one', () => {
    render(
      <MemoryRouter>
        <ProjectCard project={project} />
      </MemoryRouter>,
    )

    // A /freelancer/* href would 404-or-bounce for the anonymous visitors
    // who can now reach this card from the public /projects page.
    expect(screen.getByRole('link')).toHaveAttribute('href', '/projects/proj-1')
  })
})
