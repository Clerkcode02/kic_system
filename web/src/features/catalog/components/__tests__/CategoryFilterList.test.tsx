import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { CategoryFilterList } from '../CategoryFilterList'

const useCategoriesMock = vi.hoisted(() => vi.fn())

vi.mock('../../hooks/useCategories', () => ({ useCategories: useCategoriesMock }))

describe('CategoryFilterList', () => {
  it('calls onSelect(null) for "All categories" and onSelect(slug) for a category', async () => {
    useCategoriesMock.mockReturnValue({
      data: [
        {
          id: 'c1',
          name: 'Plumbing',
          slug: 'plumbing',
          icon: null,
          is_active: true,
          sort_order: 0,
          children: [],
        },
        {
          id: 'c2',
          name: 'Electrical',
          slug: 'electrical',
          icon: null,
          is_active: true,
          sort_order: 1,
          children: [],
        },
      ],
      isLoading: false,
    })

    const onSelect = vi.fn()
    const user = userEvent.setup()

    render(<CategoryFilterList selectedSlug={null} onSelect={onSelect} />)

    await user.click(screen.getByRole('button', { name: 'Electrical' }))
    expect(onSelect).toHaveBeenCalledWith('electrical')

    await user.click(screen.getByRole('button', { name: 'All categories' }))
    expect(onSelect).toHaveBeenCalledWith(null)
  })

  it('shows loading placeholders while categories are fetching', () => {
    useCategoriesMock.mockReturnValue({ data: undefined, isLoading: true })

    render(<CategoryFilterList selectedSlug={null} onSelect={vi.fn()} />)

    expect(screen.queryByRole('button', { name: 'All categories' })).not.toBeInTheDocument()
  })
})
