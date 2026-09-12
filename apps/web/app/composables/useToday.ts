export type TravelToNext = {
  severity: 'ok' | 'tight' | 'impossible'
  available_minutes: number
  travel_minutes: number
  message: string
  is_warning: boolean
}

export type LessonWeather = {
  kind:
    | 'clear'
    | 'partly_cloudy'
    | 'cloudy'
    | 'fog'
    | 'drizzle'
    | 'light_rain'
    | 'rain'
    | 'heavy_rain'
    | 'wintry'
    | 'thunder'
    | 'windy'
  label: string
  hint: string
  temperature_c: number | null
  precipitation_probability: number | null
  windy: boolean
  hour: string
}

export type TodayLesson = {
  id: number
  learner_id: number
  learner_name: string | null
  learner_mobile?: string | null
  starts_at: string
  starts_at_local: string
  starts_at_display: string
  starts_at_time: string
  ends_at: string
  ends_at_local: string
  ends_at_time: string
  timezone: string
  duration_minutes: number
  pickup_address: string | null
  status: 'scheduled' | 'completed' | 'cancelled' | 'no_show'
  can_complete?: boolean
  can_mark_no_show?: boolean
  is_current: boolean
  is_next: boolean
  can_complete: boolean
  instructor_notes?: string | null
  learner_summary?: string | null
  next_focus?: string | null
  learner_next_focus?: string | null
  learner_last_lesson_summary?: string | null
  test_journey?: import('./usePupils').TestJourneyCompact | import('./usePupils').TestJourney | null
  travel_to_next?: TravelToNext | null
  finance?: import('./useFinance').FinanceSnapshot | null
  weather?: LessonWeather | null
}

export type NeedsAttentionItem = {
  learner_id: number
  learner_name: string
  kind: 'pattern_overdue' | 'no_future_booking' | string
  days_since_last_lesson: number
  usual_cadence: string | null
  reasons: string[]
}

export type NeedsYouAction = {
  id: string
  kind: 'empty_seat' | 'rebook' | 'test_gap' | string
  priority: number
  title: string
  detail: string
  cta_label: string
  cta_path: string
  learner_id: number | null
  lesson_id: number | null
  match_count?: number
  best_match?: import('./useLessons').GapMatch | null
  reasons?: string[]
  days_until_test?: number
}

export type DaySummary = {
  lesson_count: number
  window_start: string | null
  window_end: string | null
  window_label: string | null
  line: string
}

export type TodayResponse = {
  date: string
  date_display: string
  timezone: string
  now_time?: string
  lesson_count: number
  summary?: DaySummary
  focus: TodayLesson | null
  remaining: TodayLesson[]
  lessons: TodayLesson[]
  needs_you?: NeedsYouAction[]
  needs_attention: NeedsAttentionItem[]
}

export function mapsUrl(address: string): string {
  return `https://maps.google.com/?q=${encodeURIComponent(address)}`
}

export async function copyText(value: string): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(value)
    return true
  } catch {
    return false
  }
}

export function useToday() {
  async function fetchToday(): Promise<TodayResponse> {
    return await apiFetch<TodayResponse>('/today')
  }

  return { fetchToday, mapsUrl, copyText }
}
