/**
 * Slots come back as ISO8601 strings already expressed in the provider's
 * scheduling timezone (Carbon's toIso8601String()). Extracting the
 * HH:MM:SS substring directly, rather than routing through `Date` (which
 * would convert to the browser's local zone), keeps what's displayed and
 * what's submitted as time_slot_start/time_slot_end in agreement.
 */
export function timeOf(iso: string): string {
  return iso.slice(11, 19)
}

/** Formats the HH:MM:SS extracted by `timeOf` as a 12-hour clock string. */
export function formatTime(iso: string): string {
  const [hourStr, minute] = timeOf(iso).split(':')
  const hour = Number(hourStr)
  const period = hour >= 12 ? 'PM' : 'AM'
  const displayHour = hour % 12 === 0 ? 12 : hour % 12
  return `${displayHour}:${minute} ${period}`
}
