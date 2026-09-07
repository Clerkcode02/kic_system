/**
 * Date helpers for the booking wizard. Everything here works on the
 * `YYYY-MM-DD` calendar-date strings the API exchanges (`scheduled_date`),
 * never on `Date` instants — parsing "2026-03-05" with `new Date()` yields
 * UTC midnight, which renders as the *previous* day for anyone west of
 * Greenwich. Splitting the string keeps the day the user picked the day
 * they see and the day that is submitted.
 */

const MONTHS = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
] as const

const MONTHS_SHORT = MONTHS.map((month) => month.slice(0, 3))

const WEEKDAYS = [
  'Sunday',
  'Monday',
  'Tuesday',
  'Wednesday',
  'Thursday',
  'Friday',
  'Saturday',
] as const

/** Today in the browser's local timezone, as `YYYY-MM-DD`. */
export function todayIso(): string {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${now.getFullYear()}-${month}-${day}`
}

/** Renders `2026-03-05` as `Thursday, March 5, 2026`. */
export function formatBookingDate(date: string): string {
  const [yearStr, monthStr, dayStr] = date.split('-')
  const year = Number(yearStr)
  const month = Number(monthStr)
  const day = Number(dayStr)
  if (!year || !month || !day) return date

  // Local-component construction, so the weekday matches the calendar date.
  const weekday = WEEKDAYS[new Date(year, month - 1, day).getDay()]
  return `${weekday}, ${MONTHS[month - 1]} ${day}, ${year}`
}

/**
 * Renders `2026-03-05` as `Mar 5, 2026` — for dense list rows where the
 * date sits inline alongside other fields and the weekday is noise.
 */
export function formatBookingDateShort(date: string): string {
  const [yearStr, monthStr, dayStr] = date.split('-')
  const year = Number(yearStr)
  const month = Number(monthStr)
  const day = Number(dayStr)
  if (!year || !month || !day) return date

  return `${MONTHS_SHORT[month - 1]} ${day}, ${year}`
}
