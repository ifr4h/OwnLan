/** Common notice presets instructors tap when logging a pupil cancel. */
export const CANCELLATION_NOTICE_OPTIONS: ReadonlyArray<{ hours: number; label: string }> = [
  { hours: 6, label: '6 hours' },
  { hours: 12, label: '12 hours' },
  { hours: 24, label: '1 day' },
  { hours: 48, label: '2 days' },
  { hours: 72, label: 'More than 2 days' },
]

export function formatCancellationNoticeLabel(hours: number | null | undefined): string | null {
  if (hours == null || Number.isNaN(hours)) return null
  if (hours < 1) return 'less than an hour’s notice'
  if (hours === 1) return '1 hour’s notice'
  if (hours < 24) return `${hours} hours’ notice`
  const days = Math.floor(hours / 24)
  const remainder = hours % 24
  if (remainder === 0) {
    return days === 1 ? '1 day’s notice' : `${days} days’ notice`
  }
  return `${hours} hours’ notice`
}
