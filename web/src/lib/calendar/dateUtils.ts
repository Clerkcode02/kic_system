/**
 * Builds a YYYY-MM-DD string from a Date's local components. `toISOString()`
 * converts to UTC first and can land on the wrong calendar day depending on
 * the browser's timezone offset — FullCalendar's `datesSet` gives back a
 * local-time Date, so this avoids that conversion entirely.
 */
export function toLocalDateString(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}
