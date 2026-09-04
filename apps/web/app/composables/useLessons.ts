export type TravelWarning = {
  severity: 'ok' | 'tight' | 'impossible' | 'soft'
  available_minutes?: number
  travel_minutes?: number
  shortfall_minutes?: number
  message: string
  is_warning: boolean
  code?: string
  direction?: 'from_previous' | 'to_next'
  other_lesson_id?: number
  to_lesson_id?: number
  from_lesson_id?: number
  from_learner_name?: string | null
  to_learner_name?: string | null
}

export type GapMatch = {
  learner_id: number
  learner_name: string
  suggested_starts_at_local: string
  suggested_duration_minutes: number
  pickup_address: string | null
  reasons: string[]
  is_due: boolean
  availability_known: boolean
  travel_from_previous_minutes: number | null
}

export type EmptySeatRecovery = {
  applicable: boolean
  reason?: string
  cancelled_lesson_id?: number
  cancelled_learner_id?: number
  cancelled_learner_name?: string
  headline?: string
  slot_label?: string
  starts_at_local?: string
  ends_at_local?: string
  duration_minutes?: number
  match_count: number
  match_summary?: string
  matches: GapMatch[]
  best_match: GapMatch | null
}

export type Lesson = {
  id: number
  learner_id: number
  instructor_id: number
  series_id?: number | null
  learner_name: string | null
  starts_at: string
  starts_at_local: string
  starts_at_display: string
  timezone: string
  duration_minutes: number
  pickup_address: string | null
  status: 'scheduled' | 'completed' | 'cancelled' | 'no_show'
  status_label?: string
  financial_line?: string | null
  can_mark_no_show?: boolean
  can_complete?: boolean
  instructor_notes?: string | null
  learner_summary?: string | null
  next_focus?: string | null
  client_mutation_id?: string | null
  learner_next_focus?: string | null
  learner_last_lesson_summary?: string | null
  test_journey?: import('./usePupils').TestJourney | import('./usePupils').TestJourneyCompact | null
  cancelled_at: string | null
  completed_at: string | null
  no_show_at?: string | null
  price_pence?: number | null
  settlement?: string | null
  finance?: import('./useFinance').LessonFinance | null
  created_at?: string
  updated_at?: string
  travel_warnings?: TravelWarning[]
  travel_to_next?: TravelWarning | null
  empty_seat?: EmptySeatRecovery | null
}

export type LessonWritePayload = {
  learner_id: number
  starts_at_local: string
  duration_minutes?: number
  pickup_address?: string | null
  scope?: 'this' | 'this_and_future'
}

export type RecurringLessonPayload = {
  learner_id: number
  starts_at_local: string
  duration_minutes?: number
  pickup_address?: string | null
  until_date?: string
  occurrence_count?: number
  allow_conflicts?: boolean
}

export type RecurringPreviewOccurrence = {
  starts_at_local: string
  starts_at_display: string
  ends_at_local: string
  has_conflict: boolean
  conflicts: Array<{ id: number; learner_name: string | null; starts_at_display: string }>
}

export type RecurringPreview = {
  count: number
  conflict_count: number
  occurrences: RecurringPreviewOccurrence[]
}

export type RecurringCreateResult = {
  series_id: number
  count: number
  items: Lesson[]
}

export type SeriesScopeResult = {
  scope: 'this_and_future'
  count: number
  items: Lesson[]
}

export type LessonCompletePayload = {
  instructor_notes?: string
  learner_summary?: string
  next_focus?: string
  client_mutation_id?: string
  skill_ids?: number[]
  skill_ratings?: Record<string, string>
}

export type LessonNoShowPayload = {
  charge: 'waived' | 'outstanding' | 'package'
  instructor_notes?: string
  client_mutation_id?: string
}

export type LessonCancelPayload = {
  scope?: 'this' | 'this_and_future'
  charge?: 'waived' | 'outstanding' | 'package'
}

export type BookingSuggestion = {
  learner_id: number
  learner_name: string | null
  history_count: number
  duration_minutes: number
  duration_source: 'history' | 'default'
  pickup_address: string | null
  pickup_source: 'history' | 'learner_default' | 'none'
  suggested_starts_at_local: string | null
  suggested_starts_at_display: string | null
  schedule_source: 'pattern' | 'none'
  schedule_pattern: { weekday: string; time: string } | null
}

export type DiaryLesson = Lesson & {
  starts_at_time?: string
  ends_at_time?: string
  ends_at?: string
  is_current?: boolean
  overlaps?: boolean
  overlap_with?: number[]
  travel_to_next?: TravelWarning | null
}

export type DiaryGap = {
  starts_at_local: string
  ends_at_local: string
  starts_at_display: string
  ends_at_display: string
  date: string
  weekday: string
  duration_minutes: number
  label: string
  previous_lesson_id: number
  next_lesson_id: number
  matches: GapMatch[]
}

export type DiaryMonthMarker = {
  id: number
  starts_at_time: string | null
  duration_minutes: number
  learner_name: string | null
  status: string
  overlaps: boolean
}

export type DiaryDay = {
  date: string
  date_display: string
  weekday: string
  is_today: boolean
  in_month?: boolean
  lesson_count: number
  teaching_minutes?: number
  has_test?: boolean
  has_cancellation?: boolean
  has_overlap?: boolean
  markers?: DiaryMonthMarker[]
  lessons: DiaryLesson[]
  gaps?: DiaryGap[]
}

export type DiaryResponse = {
  view: 'day' | 'week' | 'month'
  date: string
  range_start: string
  range_end: string
  label: string
  timezone: string
  is_today: boolean
  now_local: string
  now_time: string
  work_start_time?: string
  work_end_time?: string
  work_days?: number[]
  days: DiaryDay[]
  lessons: DiaryLesson[]
  overlap_count: number
  travel_warning_count: number
  gap_count?: number
}

export type TravelCheckResult = {
  warnings: TravelWarning[]
  warning_count: number
  has_warnings: boolean
}

export function useLessons() {
  async function listUpcoming(): Promise<Lesson[]> {
    const data = await apiFetch<{ items: Lesson[] }>('/lessons')
    return data.items
  }

  async function listForPupil(learnerId: number): Promise<Lesson[]> {
    const data = await apiFetch<{ items: Lesson[] }>(`/lessons?learner_id=${learnerId}`)
    return data.items
  }

  async function fetchDiary(view: 'day' | 'week' | 'month', date: string): Promise<DiaryResponse> {
    const q = new URLSearchParams({ view, date })
    return await apiFetch<DiaryResponse>(`/lessons/diary?${q.toString()}`)
  }

  async function checkTravel(payload: {
    learner_id?: number
    starts_at_local: string
    duration_minutes?: number
    pickup_address?: string | null
    exclude_lesson_id?: number
  }): Promise<TravelCheckResult> {
    return await apiFetch<TravelCheckResult>('/lessons/travel-check', {
      method: 'POST',
      body: payload,
    })
  }

  async function getLesson(id: number): Promise<Lesson> {
    return await apiFetch<Lesson>(`/lessons/${id}`)
  }

  async function fetchBookingSuggestion(learnerId: number): Promise<BookingSuggestion> {
    return await apiFetch<BookingSuggestion>(`/learners/${learnerId}/booking-suggestion`)
  }

  async function createLesson(payload: LessonWritePayload): Promise<Lesson> {
    return await apiFetch<Lesson>('/lessons', {
      method: 'POST',
      body: payload,
    })
  }

  async function updateLesson(
    id: number,
    payload: Partial<LessonWritePayload>,
  ): Promise<Lesson | SeriesScopeResult> {
    return await apiFetch<Lesson | SeriesScopeResult>(`/lessons/${id}`, {
      method: 'PUT',
      body: payload,
    })
  }

  async function cancelLesson(
    id: number,
    scope: 'this' | 'this_and_future' = 'this',
    options: Omit<LessonCancelPayload, 'scope'> = {},
  ): Promise<(Lesson & { empty_seat?: EmptySeatRecovery | null }) | (SeriesScopeResult & { empty_seat?: EmptySeatRecovery | null })> {
    const body: LessonCancelPayload = scope === 'this' ? { ...options } : { scope, ...options }
    return await apiFetch(`/lessons/${id}/cancel`, {
      method: 'POST',
      body,
    })
  }

  async function previewRecurring(payload: RecurringLessonPayload): Promise<RecurringPreview> {
    return await apiFetch<RecurringPreview>('/lessons/recurring/preview', {
      method: 'POST',
      body: payload,
    })
  }

  async function createRecurring(payload: RecurringLessonPayload): Promise<RecurringCreateResult> {
    return await apiFetch<RecurringCreateResult>('/lessons/recurring', {
      method: 'POST',
      body: payload,
    })
  }

  async function completeLesson(
    id: number,
    payload: LessonCompletePayload = {},
  ): Promise<Lesson> {
    return await apiFetch<Lesson>(`/lessons/${id}/complete`, {
      method: 'POST',
      body: payload,
    })
  }

  async function markNoShowLesson(
    id: number,
    payload: LessonNoShowPayload,
  ): Promise<Lesson> {
    return await apiFetch<Lesson>(`/lessons/${id}/no-show`, {
      method: 'POST',
      body: payload,
    })
  }

  return {
    listUpcoming,
    listForPupil,
    fetchDiary,
    checkTravel,
    getLesson,
    fetchBookingSuggestion,
    createLesson,
    updateLesson,
    cancelLesson,
    previewRecurring,
    createRecurring,
    completeLesson,
    markNoShowLesson,
    durationLabel,
  }
}

export function durationLabel(minutes: number): string {
  if (minutes % 60 === 0) {
    const hours = minutes / 60
    return hours === 1 ? '1 hour' : `${hours} hours`
  }
  return `${minutes} min`
}
