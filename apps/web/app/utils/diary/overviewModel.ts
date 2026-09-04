import type {
  DiaryDay,
  DiaryGap,
  DiaryLesson,
  DiaryResponse,
  TravelWarning,
} from '~/composables/useLessons'
import { parseHm } from '~/utils/calendar/timeGrid'

export type OverviewMode = 'day' | 'week' | 'month'

export type RhythmSegment =
  | { kind: 'teach'; start: number; end: number }
  | { kind: 'travel'; start: number; end: number; warning?: boolean }
  | { kind: 'open'; start: number; end: number }

export type DayRhythm = {
  startMinutes: number
  endMinutes: number
  segments: RhythmSegment[]
}

export type WeekDayBar = {
  date: string
  label: string
  teachingMinutes: number
  lessonCount: number
  isToday: boolean
  isBusiest: boolean
}

export type MonthWeekBar = {
  key: string
  label: string
  teachingMinutes: number
  lessonCount: number
}

export type PeriodComparison = {
  currentLabel: string
  currentMinutes: number
  priorLabel: string
  priorMinutes: number
  /** Signed delta minutes (current − prior). Neutral presentation. */
  deltaMinutes: number
  likeForLike: boolean
  likeForLikeNote: string | null
}

export type OverviewObservation =
  | {
      id: string
      kind: 'tight_turnaround'
      fromName: string
      toName: string
      availableMinutes: number
      travelMinutes: number
      shortfallMinutes: number
    }
  | {
      id: string
      kind: 'opening'
      date: string
      weekday: string
      startDisplay: string
      endDisplay: string
      durationMinutes: number
      matchCount: number
      matchInitials: string[]
    }
  | {
      id: string
      kind: 'busiest_day'
      weekday: string
      date: string
      teachingMinutes: number
      lessonCount: number
    }
  | {
      id: string
      kind: 'tests'
      items: Array<{
        name: string
        daysUntil: number
        countdownLabel: string
        lessonsBefore: number | null
      }>
    }
  | {
      id: string
      kind: 'span'
      firstTime: string
      lastTime: string
      spanMinutes: number
      teachingMinutes: number
    }
  | {
      id: string
      kind: 'travel_total'
      estimatedMinutes: number
      priorMinutes: number | null
      deltaMinutes: number | null
    }
  | {
      id: string
      kind: 'cancellations'
      currentCount: number
      priorCount: number | null
    }
  | {
      id: string
      kind: 'tight_count'
      count: number
    }
  | {
      id: string
      kind: 'continuity'
      pupils: Array<{
        name: string
        initials: string
        cadence: string | null
      }>
    }

export type DiaryOverviewExtras = {
  /** Recent same-weekday teaching minutes for day "usual" comparison (≥4 samples). */
  usualWeekdayMinutes?: number[]
  continuityPupils?: Array<{
    learner_id: number
    learner_name: string
    usual_cadence: string | null
  }>
}

export type DiaryOverviewModel = {
  mode: OverviewMode
  periodLabel: string
  isToday: boolean
  lessonCount: number
  pupilCount: number
  teachingMinutes: number
  teachingDays: number
  dayRhythm: DayRhythm | null
  weekBars: WeekDayBar[]
  monthBars: MonthWeekBar[]
  facts: Array<{ value: string; label: string }>
  comparison: PeriodComparison | null
  observations: OverviewObservation[]
}

const TEACHING_STATUSES = new Set(['scheduled', 'completed'])

export function formatOverviewHours(minutes: number): string {
  if (minutes <= 0) return '0h'
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  if (h === 0) return `${m}m`
  if (m === 0) return `${h}h`
  return `${h}h ${m}m`
}

export function initialsFromName(name: string | null | undefined): string {
  const parts = (name || '').trim().split(/\s+/).filter(Boolean)
  if (!parts.length) return '?'
  if (parts.length === 1) return parts[0]!.slice(0, 2).toUpperCase()
  return `${parts[0]![0] ?? ''}${parts[parts.length - 1]![0] ?? ''}`.toUpperCase()
}

function isTeachingLesson(lesson: DiaryLesson): boolean {
  return TEACHING_STATUSES.has(lesson.status)
}

function lessonsForPeriod(diary: DiaryResponse): DiaryLesson[] {
  if (diary.lessons?.length) return diary.lessons.filter(isTeachingLesson)
  return diary.days.flatMap(d => (d.lessons ?? []).filter(isTeachingLesson))
}

function teachingMinutesFromLessons(lessons: DiaryLesson[]): number {
  return lessons.reduce((n, l) => n + (l.duration_minutes || 0), 0)
}

function teachingMinutesForDay(day: DiaryDay): number {
  if (typeof day.teaching_minutes === 'number') return day.teaching_minutes
  return teachingMinutesFromLessons((day.lessons ?? []).filter(isTeachingLesson))
}

function lessonCountForDay(day: DiaryDay): number {
  if (day.lessons?.length) return day.lessons.filter(isTeachingLesson).length
  return day.lesson_count || 0
}

function pupilCount(lessons: DiaryLesson[]): number {
  return new Set(lessons.map(l => l.learner_id).filter(Boolean)).size
}

function parseYmd(ymd: string): Date | null {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(ymd)
  if (!m) return null
  return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]))
}

function formatYmd(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

export function addDaysYmd(ymd: string, days: number): string {
  const d = parseYmd(ymd)
  if (!d) return ymd
  d.setDate(d.getDate() + days)
  return formatYmd(d)
}

function weekdayShort(ymd: string): string {
  const d = parseYmd(ymd)
  if (!d) return ymd
  return d.toLocaleDateString('en-GB', { weekday: 'short' })
}

function buildDayRhythm(day: DiaryDay | undefined): DayRhythm | null {
  if (!day) return null
  const lessons = (day.lessons ?? [])
    .filter(isTeachingLesson)
    .map(l => ({
      start: parseHm(l.starts_at_time || l.starts_at_local?.slice(11, 16)),
      end: parseHm(l.ends_at_time)
        || parseHm(l.starts_at_time || l.starts_at_local?.slice(11, 16)) + (l.duration_minutes || 0),
      lesson: l,
    }))
    .filter(l => l.end > l.start)
    .sort((a, b) => a.start - b.start)

  if (!lessons.length) return null

  const startMinutes = Math.max(0, lessons[0]!.start - 30)
  const endMinutes = Math.min(24 * 60, lessons[lessons.length - 1]!.end + 30)
  const segments: RhythmSegment[] = []

  for (let i = 0; i < lessons.length; i++) {
    const cur = lessons[i]!
    if (i > 0) {
      const prev = lessons[i - 1]!
      if (cur.start > prev.end) {
        const travel = prev.lesson.travel_to_next
        if (travel && travel.travel_minutes != null && travel.travel_minutes > 0) {
          const travelEnd = Math.min(cur.start, prev.end + travel.travel_minutes)
          if (travelEnd > prev.end) {
            segments.push({
              kind: 'travel',
              start: prev.end,
              end: travelEnd,
              warning: travel.is_warning,
            })
          }
          if (cur.start > travelEnd) {
            segments.push({ kind: 'open', start: travelEnd, end: cur.start })
          }
        } else {
          segments.push({ kind: 'open', start: prev.end, end: cur.start })
        }
      }
    }
    segments.push({ kind: 'teach', start: cur.start, end: cur.end })
  }

  return { startMinutes, endMinutes, segments }
}

function estimatedTravelMinutes(lessons: DiaryLesson[]): number {
  let total = 0
  let any = false
  for (const lesson of lessons) {
    const t = lesson.travel_to_next
    if (t && typeof t.travel_minutes === 'number' && t.travel_minutes > 0) {
      total += t.travel_minutes
      any = true
    }
  }
  return any ? total : 0
}

function travelWarnings(lessons: DiaryLesson[]): TravelWarning[] {
  const out: TravelWarning[] = []
  for (const lesson of lessons) {
    const t = lesson.travel_to_next
    if (t?.is_warning) out.push(t)
  }
  return out
}

function bestOpening(days: DiaryDay[]): OverviewObservation | null {
  let best: { gap: DiaryGap; day: DiaryDay } | null = null
  for (const day of days) {
    for (const gap of day.gaps ?? []) {
      if (gap.duration_minutes < 60) continue
      if (!best || gap.duration_minutes > best.gap.duration_minutes) {
        best = { gap, day }
      }
    }
  }
  if (!best) return null
  const matches = best.gap.matches ?? []
  return {
    id: `opening-${best.gap.date}-${best.gap.starts_at_local}`,
    kind: 'opening',
    date: best.gap.date,
    weekday: best.day.weekday || weekdayShort(best.gap.date),
    startDisplay: best.gap.starts_at_display || best.gap.starts_at_local.slice(11, 16),
    endDisplay: best.gap.ends_at_display || best.gap.ends_at_local.slice(11, 16),
    durationMinutes: best.gap.duration_minutes,
    matchCount: matches.length,
    matchInitials: matches.slice(0, 4).map(m => initialsFromName(m.learner_name)),
  }
}

function tightObservation(lessons: DiaryLesson[]): OverviewObservation | null {
  const warnings = travelWarnings(lessons)
    .filter(w => typeof w.shortfall_minutes === 'number' && (w.shortfall_minutes ?? 0) > 0)
    .sort((a, b) => (b.shortfall_minutes ?? 0) - (a.shortfall_minutes ?? 0))

  if (!warnings.length) return null
  if (warnings.length > 1 && lessons.length > 6) {
    return { id: 'tight-count', kind: 'tight_count', count: warnings.length }
  }

  const w = warnings[0]!
  return {
    id: `tight-${w.from_lesson_id ?? 0}-${w.to_lesson_id ?? 0}`,
    kind: 'tight_turnaround',
    fromName: w.from_learner_name || 'Lesson',
    toName: w.to_learner_name || 'Next',
    availableMinutes: w.available_minutes ?? 0,
    travelMinutes: w.travel_minutes ?? 0,
    shortfallMinutes: w.shortfall_minutes ?? 0,
  }
}

function testObservation(lessons: DiaryLesson[]): OverviewObservation | null {
  const items = lessons
    .filter(l => l.test_journey && typeof (l.test_journey as { days_until?: number }).days_until === 'number')
    .map((l) => {
      const tj = l.test_journey as {
        days_until: number
        countdown_label?: string
        lessons_booked_before_test?: number | null
      }
      return {
        name: l.learner_name || 'Pupil',
        daysUntil: tj.days_until,
        countdownLabel: tj.countdown_label || `${tj.days_until}d`,
        lessonsBefore: tj.lessons_booked_before_test ?? null,
      }
    })
    .sort((a, b) => a.daysUntil - b.daysUntil)
    .slice(0, 3)

  if (!items.length) return null
  return { id: 'tests', kind: 'tests', items }
}

function spanObservation(lessons: DiaryLesson[], teachingMinutes: number): OverviewObservation | null {
  if (lessons.length < 2) return null
  const sorted = [...lessons].sort((a, b) =>
    (a.starts_at_time || '').localeCompare(b.starts_at_time || ''),
  )
  const first = sorted[0]!
  const last = sorted[sorted.length - 1]!
  const firstMins = parseHm(first.starts_at_time)
  const lastMins = parseHm(last.ends_at_time)
    || parseHm(last.starts_at_time) + (last.duration_minutes || 0)
  const span = lastMins - firstMins
  if (span <= teachingMinutes + 30) return null
  return {
    id: 'span',
    kind: 'span',
    firstTime: first.starts_at_time || '',
    lastTime: last.ends_at_time || formatHmLocal(lastMins),
    spanMinutes: span,
    teachingMinutes,
  }
}

function formatHmLocal(totalMinutes: number): string {
  const mins = ((Math.round(totalMinutes) % (24 * 60)) + 24 * 60) % (24 * 60)
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

function buildWeekBars(days: DiaryDay[]): WeekDayBar[] {
  const teaching = days.map(d => teachingMinutesForDay(d))
  const max = Math.max(0, ...teaching)
  return days.map((day, i) => ({
    date: day.date,
    label: (day.weekday || weekdayShort(day.date)).slice(0, 1),
    teachingMinutes: teaching[i]!,
    lessonCount: lessonCountForDay(day),
    isToday: !!day.is_today,
    isBusiest: max > 0 && teaching[i] === max,
  }))
}

function buildMonthBars(days: DiaryDay[]): MonthWeekBar[] {
  const inMonth = days.filter(d => d.in_month !== false)
  const weeks = new Map<string, { teaching: number; lessons: number; start: string }>()
  for (const day of inMonth) {
    const d = parseYmd(day.date)
    if (!d) continue
    const dow = (d.getDay() + 6) % 7
    const monday = new Date(d)
    monday.setDate(d.getDate() - dow)
    const key = formatYmd(monday)
    const cur = weeks.get(key) || { teaching: 0, lessons: 0, start: key }
    cur.teaching += teachingMinutesForDay(day)
    cur.lessons += lessonCountForDay(day)
    weeks.set(key, cur)
  }
  return [...weeks.entries()]
    .sort((a, b) => a[0].localeCompare(b[0]))
    .map(([, w], i) => ({
      key: w.start,
      label: `W${i + 1}`,
      teachingMinutes: w.teaching,
      lessonCount: w.lessons,
    }))
}

function periodLabel(diary: DiaryResponse): string {
  if (diary.view === 'day') {
    if (diary.is_today) return 'Today'
    const d = parseYmd(diary.date)
    return d
      ? d.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'short' })
      : diary.label
  }
  if (diary.view === 'week') {
    return diary.is_today || diary.days.some(d => d.is_today) ? 'This week' : diary.label
  }
  const d = parseYmd(diary.date)
  return d
    ? d.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }).toUpperCase()
    : diary.label
}

function sliceDaysThrough(days: DiaryDay[], throughDate: string): DiaryDay[] {
  return days.filter(d => d.date <= throughDate)
}

function teachingInDays(days: DiaryDay[]): number {
  return days.reduce((n, d) => n + teachingMinutesForDay(d), 0)
}

/**
 * Build comparison with like-for-like for partial current periods.
 * Day: current day vs same weekday one week prior (needs compare diary for that day).
 * Week: Mon–today vs prior week Mon–same weekday when current week includes today.
 * Month: 1..today vs prior month 1..same DOM when current month is in progress.
 */
export function buildComparison(
  current: DiaryResponse,
  prior: DiaryResponse | null,
  todayYmd: string,
  usualWeekdayMinutes?: number[],
): PeriodComparison | null {
  if (current.view === 'day') {
    const cur = teachingMinutesFromLessons(lessonsForPeriod(current))
    const d = parseYmd(current.date)
    const weekday = d ? d.toLocaleDateString('en-GB', { weekday: 'long' }) : 'day'
    const samples = (usualWeekdayMinutes ?? []).filter(m => m >= 0)
    const taughtSamples = samples.filter(m => m > 0)
    if (taughtSamples.length >= 4) {
      const avg = Math.round(taughtSamples.reduce((a, b) => a + b, 0) / taughtSamples.length)
      if (cur === 0 && avg === 0) return null
      return {
        currentLabel: current.is_today ? 'Today' : weekday,
        currentMinutes: cur,
        priorLabel: `Usual ${weekday}`,
        priorMinutes: avg,
        deltaMinutes: cur - avg,
        likeForLike: true,
        likeForLikeNote: `${taughtSamples.length} recent ${weekday}s`,
      }
    }
    if (!prior) return null
    const prev = teachingMinutesFromLessons(lessonsForPeriod(prior))
    if (cur === 0 && prev === 0) return null
    return {
      currentLabel: current.is_today ? 'Today' : weekday,
      currentMinutes: cur,
      priorLabel: `Last ${weekday}`,
      priorMinutes: prev,
      deltaMinutes: cur - prev,
      likeForLike: true,
      likeForLikeNote: null,
    }
  }

  if (!prior) return null

  if (current.view === 'week') {
    const inProgress = current.days.some(d => d.is_today) && todayYmd >= current.range_start && todayYmd <= current.range_end
    if (inProgress) {
      const curDays = sliceDaysThrough(current.days, todayYmd)
      const priorThrough = addDaysYmd(prior.range_start, curDays.length - 1)
      const prevDays = sliceDaysThrough(prior.days, priorThrough)
      const cur = teachingInDays(curDays)
      const prev = teachingInDays(prevDays)
      if (cur === 0 && prev === 0) return null
      return {
        currentLabel: 'This week so far',
        currentMinutes: cur,
        priorLabel: 'Last week so far',
        priorMinutes: prev,
        deltaMinutes: cur - prev,
        likeForLike: true,
        likeForLikeNote: `Mon–${weekdayShort(todayYmd)}`,
      }
    }
    const cur = teachingInDays(current.days)
    const prev = teachingInDays(prior.days)
    if (cur === 0 && prev === 0) return null
    return {
      currentLabel: 'This week',
      currentMinutes: cur,
      priorLabel: 'Last week',
      priorMinutes: prev,
      deltaMinutes: cur - prev,
      likeForLike: true,
      likeForLikeNote: null,
    }
  }

  // month
  const curInMonth = current.days.filter(d => d.in_month !== false)
  const prevInMonth = prior.days.filter(d => d.in_month !== false)
  const monthStart = current.range_start
  const inProgress = todayYmd >= monthStart && todayYmd <= current.range_end
    && todayYmd.slice(0, 7) === current.date.slice(0, 7)

  if (inProgress) {
    const dayNum = Number(todayYmd.slice(8, 10))
    const curDays = curInMonth.filter(d => Number(d.date.slice(8, 10)) <= dayNum)
    const prevDays = prevInMonth.filter(d => Number(d.date.slice(8, 10)) <= dayNum)
    const cur = teachingInDays(curDays)
    const prev = teachingInDays(prevDays)
    if (cur === 0 && prev === 0) return null
    const priorMonth = parseYmd(prior.date)
    const priorName = priorMonth
      ? priorMonth.toLocaleDateString('en-GB', { month: 'short' })
      : 'Prior'
    return {
      currentLabel: 'So far',
      currentMinutes: cur,
      priorLabel: `${priorName} 1–${dayNum}`,
      priorMinutes: prev,
      deltaMinutes: cur - prev,
      likeForLike: true,
      likeForLikeNote: `1–${dayNum}`,
    }
  }

  const cur = teachingInDays(curInMonth)
  const prev = teachingInDays(prevInMonth)
  if (cur === 0 && prev === 0) return null
  const curMonth = parseYmd(current.date)
  const priorMonth = parseYmd(prior.date)
  return {
    currentLabel: curMonth
      ? curMonth.toLocaleDateString('en-GB', { month: 'long' })
      : 'This month',
    currentMinutes: cur,
    priorLabel: priorMonth
      ? priorMonth.toLocaleDateString('en-GB', { month: 'long' })
      : 'Last month',
    priorMinutes: prev,
    deltaMinutes: cur - prev,
    likeForLike: true,
    likeForLikeNote: null,
  }
}

function rankObservations(items: Array<OverviewObservation | null>, limit: number): OverviewObservation[] {
  const ranked = items.filter((x): x is OverviewObservation => !!x)
  const priority: Record<OverviewObservation['kind'], number> = {
    tight_turnaround: 1,
    opening: 2,
    continuity: 3,
    tests: 4,
    busiest_day: 5,
    travel_total: 6,
    span: 7,
    tight_count: 2,
    cancellations: 8,
  }
  return ranked
    .sort((a, b) => priority[a.kind] - priority[b.kind])
    .slice(0, limit)
}

export function buildDiaryOverview(
  diary: DiaryResponse,
  prior: DiaryResponse | null,
  todayYmd: string,
  extras: DiaryOverviewExtras = {},
): DiaryOverviewModel {
  const mode = diary.view
  const lessons = lessonsForPeriod(diary)
  const teachingMinutes = mode === 'month'
    ? teachingInDays(diary.days.filter(d => d.in_month !== false))
    : teachingMinutesFromLessons(lessons)
  const lessonCount = mode === 'month'
    ? diary.days.filter(d => d.in_month !== false).reduce((n, d) => n + (d.lesson_count || 0), 0)
    : lessons.length
  const pupils = pupilCount(lessons)
  const teachingDays = mode === 'month'
    ? diary.days.filter(d => d.in_month !== false && teachingMinutesForDay(d) > 0).length
    : diary.days.filter(d => teachingMinutesForDay(d) > 0).length

  const travelMins = estimatedTravelMinutes(lessons)
  const priorLessons = prior ? lessonsForPeriod(prior) : []
  const priorTravel = prior ? estimatedTravelMinutes(priorLessons) : null

  const facts: DiaryOverviewModel['facts'] = []
  facts.push({ value: String(lessonCount), label: lessonCount === 1 ? 'lesson' : 'lessons' })
  if (mode !== 'day' && pupils > 0) {
    facts.push({ value: String(pupils), label: pupils === 1 ? 'pupil' : 'pupils' })
  }
  if (mode === 'month') {
    facts.push({ value: String(teachingDays), label: teachingDays === 1 ? 'teaching day' : 'teaching days' })
  }
  if (travelMins > 0) {
    facts.push({ value: formatOverviewHours(travelMins), label: 'est. travel' })
  }
  if (mode === 'day' && lessons.length) {
    const sorted = [...lessons].sort((a, b) =>
      (a.starts_at_time || '').localeCompare(b.starts_at_time || ''),
    )
    const first = sorted[0]?.starts_at_time
    const last = sorted[sorted.length - 1]?.ends_at_time
    if (first) facts.push({ value: first, label: 'first' })
    if (last) facts.push({ value: last, label: 'last' })
  }

  const observations: Array<OverviewObservation | null> = []

  if (mode === 'day') {
    observations.push(tightObservation(lessons))
    observations.push(bestOpening(diary.days))
    observations.push(spanObservation(lessons, teachingMinutes))
    if (travelMins > 0) {
      observations.push({
        id: 'travel-day',
        kind: 'travel_total',
        estimatedMinutes: travelMins,
        priorMinutes: priorTravel && priorTravel > 0 ? priorTravel : null,
        deltaMinutes: priorTravel && priorTravel > 0 ? travelMins - priorTravel : null,
      })
    }
    observations.push(testObservation(lessons))
  }

  if (mode === 'week') {
    const bars = buildWeekBars(diary.days)
    const busiest = bars.filter(b => b.isBusiest && b.teachingMinutes > 0)
    if (busiest.length === 1) {
      const b = busiest[0]!
      const day = diary.days.find(d => d.date === b.date)
      observations.push({
        id: `busy-${b.date}`,
        kind: 'busiest_day',
        weekday: day?.weekday || weekdayShort(b.date),
        date: b.date,
        teachingMinutes: b.teachingMinutes,
        lessonCount: b.lessonCount,
      })
    }
    observations.push(bestOpening(diary.days))
    observations.push(tightObservation(lessons))
    observations.push(testObservation(lessons))
    if (travelMins > 0) {
      observations.push({
        id: 'travel-week',
        kind: 'travel_total',
        estimatedMinutes: travelMins,
        priorMinutes: priorTravel && priorTravel > 0 ? priorTravel : null,
        deltaMinutes: priorTravel && priorTravel > 0 ? travelMins - priorTravel : null,
      })
    }
    const continuity = extras.continuityPupils?.filter(p => p.usual_cadence) ?? []
    if (continuity.length) {
      observations.push({
        id: 'continuity',
        kind: 'continuity',
        pupils: continuity.slice(0, 4).map(p => ({
          name: p.learner_name,
          initials: initialsFromName(p.learner_name),
          cadence: p.usual_cadence,
        })),
      })
    }
  }

  if (mode === 'month') {
    const byDow = new Map<string, { minutes: number; count: number }>()
    for (const day of diary.days.filter(d => d.in_month !== false)) {
      const key = (day.weekday || weekdayShort(day.date)).slice(0, 3)
      const cur = byDow.get(key) || { minutes: 0, count: 0 }
      cur.minutes += teachingMinutesForDay(day)
      cur.count += 1
      byDow.set(key, cur)
    }
    let busiestDow: { key: string; avg: number } | null = null
    for (const [key, v] of byDow) {
      if (v.count < 2) continue
      const avg = v.minutes / v.count
      if (!busiestDow || avg > busiestDow.avg) busiestDow = { key, avg }
    }
    if (busiestDow && busiestDow.avg >= 60) {
      observations.push({
        id: `dow-${busiestDow.key}`,
        kind: 'busiest_day',
        weekday: busiestDow.key,
        date: '',
        teachingMinutes: Math.round(busiestDow.avg),
        lessonCount: 0,
      })
    }

    const cancelCur = diary.days.filter(d => d.in_month !== false && d.has_cancellation).length
    const cancelPrior = prior
      ? prior.days.filter(d => d.in_month !== false && d.has_cancellation).length
      : null
    if (cancelCur >= 2 || (cancelPrior != null && cancelPrior >= 2)) {
      observations.push({
        id: 'cancels',
        kind: 'cancellations',
        currentCount: cancelCur,
        priorCount: cancelPrior,
      })
    }

    if (travelMins > 0 || (priorTravel && priorTravel > 0)) {
      observations.push({
        id: 'travel-month',
        kind: 'travel_total',
        estimatedMinutes: travelMins,
        priorMinutes: priorTravel && priorTravel > 0 ? priorTravel : null,
        deltaMinutes: priorTravel && priorTravel > 0 ? travelMins - priorTravel : null,
      })
    }

    const continuity = extras.continuityPupils?.filter(p => p.usual_cadence) ?? []
    if (continuity.length) {
      observations.push({
        id: 'continuity-month',
        kind: 'continuity',
        pupils: continuity.slice(0, 4).map(p => ({
          name: p.learner_name,
          initials: initialsFromName(p.learner_name),
          cadence: p.usual_cadence,
        })),
      })
    }
  }

  const limit = 3

  return {
    mode,
    periodLabel: periodLabel(diary),
    isToday: !!diary.is_today || diary.days.some(d => d.is_today),
    lessonCount,
    pupilCount: pupils,
    teachingMinutes,
    teachingDays,
    dayRhythm: mode === 'day'
      ? buildDayRhythm(diary.days.find(d => d.date === diary.date) || diary.days[0])
      : null,
    weekBars: mode === 'week' ? buildWeekBars(diary.days) : [],
    monthBars: mode === 'month' ? buildMonthBars(diary.days) : [],
    facts: facts.slice(0, 4),
    comparison: buildComparison(diary, prior, todayYmd, extras.usualWeekdayMinutes),
    observations: rankObservations(observations, limit),
  }
}

/** Anchor date for the previous comparable period. */
export function priorPeriodAnchor(view: OverviewMode, dateYmd: string): string {
  if (view === 'day') return addDaysYmd(dateYmd, -7)
  if (view === 'week') return addDaysYmd(dateYmd, -7)
  const d = parseYmd(dateYmd)
  if (!d) return dateYmd
  d.setMonth(d.getMonth() - 1)
  return formatYmd(d)
}

export function localTodayYmd(): string {
  const n = new Date()
  return formatYmd(n)
}
