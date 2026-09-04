/**
 * Time-grid calendar helpers — consistent vertical scale for day/week views.
 */

export type GridBounds = {
  /** Minutes from midnight — inclusive start of visible rail */
  startMinutes: number
  /** Minutes from midnight — exclusive end of visible rail */
  endMinutes: number
  /** Pixels per minute */
  pxPerMinute: number
}

export type TimedBlock = {
  id: number
  startMinutes: number
  endMinutes: number
}

export type PositionedBlock<T extends TimedBlock> = T & {
  top: number
  height: number
  column: number
  columnCount: number
  leftPct: number
  widthPct: number
}

export function parseHm(value: string | null | undefined): number {
  if (!value) return 0
  const m = /^(\d{1,2}):(\d{2})/.exec(value.trim())
  if (!m) return 0
  const h = Number(m[1])
  const min = Number(m[2])
  if (!Number.isFinite(h) || !Number.isFinite(min)) return 0
  return h * 60 + min
}

export function formatHm(totalMinutes: number): string {
  const mins = ((Math.round(totalMinutes) % (24 * 60)) + 24 * 60) % (24 * 60)
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

/** Compact hour label for the time rail (Apple-style quiet chrome). */
export function formatHourLabel(totalMinutes: number): string {
  const mins = ((Math.round(totalMinutes) % (24 * 60)) + 24 * 60) % (24 * 60)
  const h = Math.floor(mins / 60)
  const m = mins % 60
  if (m === 0) return String(h)
  return `${h}:${String(m).padStart(2, '0')}`
}

/** Soft pastel pairs for lesson blocks — darker text on light wash. */
export const LESSON_PASTELS = [
  { bg: '#e7f6ee', fg: '#0f6b42' }, // OwnLane mint
  { bg: '#e5f3ff', fg: '#1a5f8a' }, // sky
  { bg: '#f3eaff', fg: '#5a3d8a' }, // lilac
  { bg: '#ffe8dc', fg: '#a14a1f' }, // peach
  { bg: '#fff1d6', fg: '#8a6518' }, // sand
  { bg: '#e0f7f3', fg: '#1a6b63' }, // teal
  { bg: '#ffe3ec', fg: '#9a3d5c' }, // rose
] as const

export function pastelForLearner(seed: string | number | null | undefined): {
  bg: string
  fg: string
} {
  const raw = String(seed ?? '0')
  let hash = 0
  for (let i = 0; i < raw.length; i++) {
    hash = (hash * 31 + raw.charCodeAt(i)) >>> 0
  }
  return LESSON_PASTELS[hash % LESSON_PASTELS.length]!
}

export function localDateTime(date: string, minutes: number): string {
  return `${date}T${formatHm(minutes)}`
}

/** Prefer showing a little before/after working hours when lessons spill. */
export function resolveGridBounds(opts: {
  workStart: string
  workEnd: string
  lessonStarts?: Array<string | null | undefined>
  lessonEnds?: Array<string | null | undefined>
  padMinutes?: number
}): GridBounds {
  const pad = opts.padMinutes ?? 30
  let start = parseHm(opts.workStart) - pad
  let end = parseHm(opts.workEnd) + pad
  for (const t of opts.lessonStarts ?? []) {
    if (!t) continue
    start = Math.min(start, parseHm(t) - 15)
  }
  for (const t of opts.lessonEnds ?? []) {
    if (!t) continue
    end = Math.max(end, parseHm(t) + 15)
  }
  start = Math.max(0, Math.floor(start / 30) * 30)
  end = Math.min(24 * 60, Math.ceil(end / 30) * 30)
  if (end <= start) {
    start = 8 * 60
    end = 18 * 60
  }
  return { startMinutes: start, endMinutes: end, pxPerMinute: 1.15 }
}

/** Full midnight–midnight day (Apple-style). Work hours only affect outside shading. */
export function fullDayGridBounds(pxPerMinute = 1.2): GridBounds {
  return {
    startMinutes: 0,
    endMinutes: 24 * 60,
    pxPerMinute,
  }
}

export function hourMarks(bounds: GridBounds): number[] {
  const marks: number[] = []
  const startHour = Math.ceil(bounds.startMinutes / 60) * 60
  for (let m = startHour; m < bounds.endMinutes; m += 60) {
    marks.push(m)
  }
  if (bounds.startMinutes % 60 === 0 && marks[0] !== bounds.startMinutes) {
    marks.unshift(bounds.startMinutes)
  }
  return marks
}

/** Half-hour grid lines between major hours (excludes hour marks). */
export function halfHourMarks(bounds: GridBounds): number[] {
  const marks: number[] = []
  const start = Math.ceil(bounds.startMinutes / 30) * 30
  for (let m = start; m < bounds.endMinutes; m += 30) {
    if (m % 60 !== 0) marks.push(m)
  }
  return marks
}

export function formatDuration(minutes: number): string {
  const m = Math.max(0, Math.round(minutes))
  if (m < 60) return `${m}m`
  const h = Math.floor(m / 60)
  const rem = m % 60
  return rem === 0 ? `${h}h` : `${h}h ${rem}m`
}

export type ContentTier = 'minimal' | 'compact' | 'comfortable' | 'spacious'

export function contentTierForHeight(heightPx: number): ContentTier {
  if (heightPx < 28) return 'minimal'
  if (heightPx < 44) return 'compact'
  if (heightPx < 68) return 'comfortable'
  return 'spacious'
}

export function lessonAriaLabel(lesson: {
  learner_name?: string | null
  starts_at_time?: string | null
  ends_at_time?: string | null
  status?: string
  settlement?: string | null
}): string {
  const name = lesson.learner_name || 'Lesson'
  const when = lesson.starts_at_time && lesson.ends_at_time
    ? `${lesson.starts_at_time} to ${lesson.ends_at_time}`
    : lesson.starts_at_time || ''
  const parts = [name, when].filter(Boolean)
  if (lesson.settlement === 'outstanding') parts.push('unpaid')
  else if (lesson.settlement === 'paid') parts.push('paid')
  else if (lesson.settlement === 'package') parts.push('package credit')
  if (lesson.status === 'cancelled') parts.push('cancelled')
  if (lesson.status === 'no_show') parts.push('no-show')
  return parts.join(', ')
}

export function blockTop(startMinutes: number, bounds: GridBounds): number {
  return (startMinutes - bounds.startMinutes) * bounds.pxPerMinute
}

export function blockHeight(startMinutes: number, endMinutes: number, bounds: GridBounds): number {
  const raw = (endMinutes - startMinutes) * bounds.pxPerMinute
  return Math.max(raw, 22)
}

export function gridHeight(bounds: GridBounds): number {
  return (bounds.endMinutes - bounds.startMinutes) * bounds.pxPerMinute
}

/**
 * Classic calendar overlap packing — overlapping lessons share the lane side-by-side.
 */
export function layoutOverlaps<T extends TimedBlock>(
  items: T[],
  bounds: GridBounds,
): PositionedBlock<T>[] {
  const sorted = [...items].sort((a, b) =>
    a.startMinutes - b.startMinutes || a.endMinutes - b.endMinutes || a.id - b.id,
  )

  type Active = { item: T; column: number }
  const result: PositionedBlock<T>[] = []
  let cluster: Active[] = []
  let clusterEnd = -1
  let maxCol = 0

  const flush = () => {
    if (!cluster.length) return
    const cols = maxCol + 1
    for (const { item, column } of cluster) {
      const widthPct = 100 / cols
      result.push({
        ...item,
        top: blockTop(item.startMinutes, bounds),
        height: blockHeight(item.startMinutes, item.endMinutes, bounds),
        column,
        columnCount: cols,
        leftPct: column * widthPct,
        widthPct,
      })
    }
    cluster = []
    clusterEnd = -1
    maxCol = 0
  }

  for (const item of sorted) {
    if (cluster.length && item.startMinutes >= clusterEnd) {
      flush()
    }
    const used = new Set(cluster.filter(c => c.item.endMinutes > item.startMinutes).map(c => c.column))
    let col = 0
    while (used.has(col)) col++
    cluster.push({ item, column: col })
    maxCol = Math.max(maxCol, col)
    clusterEnd = Math.max(clusterEnd, item.endMinutes)
  }
  flush()

  return result
}

/** Short place label for dense week cells — "Bletchley · MK3" when possible. */
export function shortPlace(address: string | null | undefined): string | null {
  if (!address) return null
  const cleaned = address.replace(/\s+/g, ' ').trim()
  if (!cleaned) return null

  const postcode = cleaned.match(/\b([A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2})\b/i)
    ?? cleaned.match(/\b([A-Z]{1,2}\d[A-Z\d]?)\b/i)
  const pc = postcode?.[1]?.toUpperCase().replace(/\s+/, ' ') ?? null

  const first = cleaned.split(',')[0]?.trim() ?? cleaned
  let area = first
  // Drop house numbers for week density.
  area = area.replace(/^\d+[a-z]?\s+/i, '')
  if (area.length > 18) area = `${area.slice(0, 16)}…`

  if (pc) {
    const outcode = pc.split(/\s+/)[0]
    if (!area.toUpperCase().includes(outcode)) {
      return `${area} · ${outcode}`
    }
  }
  return area
}

export function minutesBetween(startHm: string, endHm: string): number {
  return Math.max(0, parseHm(endHm) - parseHm(startHm))
}

export function snapMinutes(raw: number, step = 15): number {
  return Math.round(raw / step) * step
}

export function yToMinutes(clientY: number, gridTop: number, bounds: GridBounds, step = 15): number {
  const y = Math.max(0, clientY - gridTop)
  const mins = bounds.startMinutes + y / bounds.pxPerMinute
  return Math.min(bounds.endMinutes - step, Math.max(bounds.startMinutes, snapMinutes(mins, step)))
}
