export type DayWrapCheck = {
  completed: boolean
  still_open: boolean
  overdue: boolean
  progress: boolean | null
  notes: boolean | null
  next_booked: boolean
  money_ok: boolean | null
}

export type DayWrapCta = {
  label: string
  path: string
}

export type DayWrapLesson = {
  id: number
  learner_id: number
  learner_name: string | null
  starts_at_time: string
  duration_minutes: number
  status: 'scheduled' | 'completed' | 'cancelled' | 'no_show'
  status_label: string
  checks: DayWrapCheck
  detail_line: string
  outstanding_pence: number
  cta: DayWrapCta | null
}

export type DayWrapHeadline = {
  lessons_line: string
  money_line: string | null
  open_admin_line: string | null
}

export type DayWrapStat = {
  key: string
  label: string
  value: string
  tone: 'good' | 'warn' | 'neutral' | string
}

export type DayWrapCelebration = {
  title: string
  subtitle: string
}

export type DayWrapTotals = {
  completed_count: number
  no_show_count: number
  still_open_count: number
  teaching_minutes: number
  taught_value_pence: number
  outstanding_pence: number
  open_admin_count: number
  teaching_label?: string
  taught_value_label?: string
  outstanding_label?: string
}

export type DayWrapResponse = {
  date: string
  date_display: string
  timezone: string
  empty: boolean
  day_complete?: boolean
  celebration?: DayWrapCelebration | null
  headline: DayWrapHeadline
  stats?: DayWrapStat[]
  totals: DayWrapTotals
  lessons: DayWrapLesson[]
}

export function useDayWrap() {
  async function fetchDayWrap(): Promise<DayWrapResponse> {
    return await apiFetch<DayWrapResponse>('/day-wrap')
  }

  return { fetchDayWrap }
}
