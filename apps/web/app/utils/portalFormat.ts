/** Format ISO timestamp for portal “last synced” labels. */
export function formatSyncedAt(iso: string | null | undefined): string {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  return d.toLocaleString('en-GB', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

/** Seconds → mm:ss or h:mm:ss for recording timer. */
export function formatTimer(seconds: number): string {
  const s = Math.max(0, Math.floor(seconds))
  const h = Math.floor(s / 3600)
  const m = Math.floor((s % 3600) / 60)
  const sec = s % 60
  const pad = (n: number) => String(n).padStart(2, '0')
  if (h > 0) return `${h}:${pad(m)}:${pad(sec)}`
  return `${pad(m)}:${pad(sec)}`
}

/** Metres → short UK-friendly label. */
export function formatDistanceMetres(metres: number | null | undefined): string {
  if (metres == null || metres <= 0) return '—'
  if (metres < 1000) return `${Math.round(metres)} m`
  return `${(metres / 1000).toFixed(1)} km`
}

export function prefersReducedMotion(): boolean {
  if (typeof window === 'undefined') return false
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches
}
