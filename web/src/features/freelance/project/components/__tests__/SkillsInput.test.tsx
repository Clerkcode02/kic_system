import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { SkillsInput } from '../SkillsInput'

function setup(value: string[] = []) {
  const onChange = vi.fn()
  render(<SkillsInput value={value} onChange={onChange} />)
  return { onChange, input: screen.getByLabelText(/required skills/i) }
}

describe('SkillsInput', () => {
  it('normalizes a skill the same way the server does', async () => {
    const { onChange, input } = setup()

    await userEvent.type(input, '  React Native  {Enter}')

    // The server lowercases and collapses whitespace; if the chip shown here
    // differed from the stored value the skills filter would silently miss.
    expect(onChange).toHaveBeenCalledWith(['react native'])
  })

  it('commits on comma as well as Enter', async () => {
    const { onChange, input } = setup()

    await userEvent.type(input, 'figma,')

    expect(onChange).toHaveBeenCalledWith(['figma'])
  })

  it('refuses a duplicate that differs only by casing', async () => {
    const { onChange, input } = setup(['react'])

    await userEvent.type(input, 'REACT{Enter}')

    expect(onChange).not.toHaveBeenCalled()
  })

  it('ignores a whitespace-only entry', async () => {
    const { onChange, input } = setup()

    await userEvent.type(input, '   {Enter}')

    expect(onChange).not.toHaveBeenCalled()
  })

  it('removes a skill via its remove button', async () => {
    const onChange = vi.fn()
    render(<SkillsInput value={['react', 'vue']} onChange={onChange} />)

    await userEvent.click(screen.getByRole('button', { name: 'Remove react' }))

    expect(onChange).toHaveBeenCalledWith(['vue'])
  })

  it('removes the last skill on backspace in an empty field', async () => {
    const onChange = vi.fn()
    render(<SkillsInput value={['react', 'vue']} onChange={onChange} />)

    await userEvent.type(screen.getByLabelText(/required skills/i), '{Backspace}')

    expect(onChange).toHaveBeenCalledWith(['react'])
  })

  it('disables the field once the skill cap is reached', () => {
    const atCap = Array.from({ length: 20 }, (_, i) => `skill-${i}`)
    render(<SkillsInput value={atCap} onChange={vi.fn()} />)

    expect(screen.getByLabelText(/required skills/i)).toBeDisabled()
  })

  it('offers only suggestions not already chosen', () => {
    render(<SkillsInput value={['react']} onChange={vi.fn()} suggestions={['React', 'Vue']} />)

    const options = screen.getAllByRole('option', { hidden: true })
    expect(options.map((o) => o.getAttribute('value'))).toEqual(['vue'])
  })
})
