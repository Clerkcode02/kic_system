/**
 * Canada-only market scope (CLAUDE.md §5): CAD is the only currency, so this
 * is the single formatter every feature uses — no currency prop threaded
 * through components, no per-locale switching.
 */
const formatter = new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD' })

export function formatMoney(amount: string | number): string {
  const value = typeof amount === 'string' ? Number.parseFloat(amount) : amount
  return formatter.format(Number.isFinite(value) ? value : 0)
}
