export type PupilListItem = {
  id: number
  first_name: string
  middle_name?: string | null
  last_name: string
  full_name: string
  mobile: string
  email: string | null
  default_pickup_address: string | null
  test_date: string | null
  lifecycle?: string
  status?: PupilStatus
  status_label?: string
  waiting_list_joined_at?: string | null
  transmission?: string | null
  theory_status?: string | null
  theory_test_date?: string | null
  available_from?: string | null
  available_from_label?: string | null
  available_from_month?: number | null
  available_ready?: boolean
  availability_summary?: string | null
  licence_expiry_date?: string | null
  archived_at: string | null
  attention_hint?: string | null
  needs_booking?: boolean
  outstanding_pence?: number
  test_soon?: boolean
  instructor_name?: string | null
  instructor_id?: number | null
  lesson_label?: string | null
  lesson_booked?: boolean
  progress_percent?: number | null
  lessons_completed?: number | null
  theory_label?: string | null
  practical_label?: string | null
  gap_matches?: WaitingGapMatches | null
}

export type PupilListInstructor = {
  id: number
  display_name: string
}

export type PupilStatus = 'active' | 'waiting' | 'paused' | 'passed' | 'inactive'
/** Pupils list filter — includes "all". */
export type PupilListStatus = PupilStatus | 'all'

export type PupilStatusCounts = {
  active: number
  waiting: number
  paused: number
  passed: number
  inactive: number
}

export type WaitingGapMatch = {
  date: string
  date_label: string
  time_label: string
  gap_label: string
  reasons: string[]
  learner_id: number
  learner_name: string
  suggested_starts_at_local: string
  suggested_duration_minutes: number
}

export type WaitingGapMatches = {
  match_count: number
  summary: string | null
  matches: WaitingGapMatch[]
}

export type PupilListAttention = {
  lines: string[]
  no_future_booking_count: number
  outstanding_pence: number
  outstanding_label: string | null
  tests_soon_count: number
}

export type PupilContinuity = {
  headline: string | null
  detail: string
  line: string
  needs_attention: boolean
  usual_cadence: string | null
}

export type LearnerReported = {
  source?: string
  intake_id?: number
  submitted_at?: string | null
  provenance?: string
  driving?: Record<string, unknown>
  skills_practised?: string[]
  about?: Record<string, unknown>
  availability_note?: string | null
}

export type PupilContact = {
  id?: number
  kind: 'emergency' | 'parent' | 'guardian' | 'other' | string
  kind_label?: string
  name: string
  phone?: string | null
  email?: string | null
  relationship?: string | null
  sort_order?: number
  notes?: string | null
}

export type PupilPlace = {
  id: number
  learner_id: number
  label: string
  icon: string
  address: string
  usage?: string
  usage_label?: string
  is_default: boolean
  sort_order: number
}

export type PupilServiceRate = {
  id: number
  service_id: number | null
  service_name: string
  price_pence: number
  price_label: string
  effective_from: string
  effective_to: string | null
  note: string | null
  is_active: boolean
}

export type TestJourney = {
  test_date: string
  test_date_display: string
  test_centre: string | null
  practical_test_time?: string | null
  booking_ref?: string | null
  days_until: number
  weeks_until: string | null
  countdown_label: string
  lessons_booked_before_test: number
  hours_booked_before_test?: number
  hours_booked_label?: string | null
  cancel_by_date?: string | null
  cancel_by_label?: string | null
  cancel_by_source?: string | null
  progress_summary: string | null
  next_focus: string | null
  show_on_today: boolean
  latest_mock?: { result_label?: string; date_display?: string } | null
  syllabus_percent?: number | null
  syllabus_line?: string | null
  reminder_offsets?: number[]
}

export type TestJourneyCompact = {
  countdown_label: string
  days_until: number
  test_centre: string | null
  lessons_booked_before_test: number
  next_focus: string | null
}

export type PortalStatus = {
  status: 'not_invited' | 'invite_pending' | 'invite_expired' | 'connected'
  status_label?: string
  email: string | null
  portal_email?: string | null
  activated: boolean
  can_invite: boolean
  invite_pending?: boolean
  invite_expired?: boolean
  invite_expires_at?: string | null
  last_invite_at?: string | null
  connected_since?: string | null
  connected_since_display?: string | null
  delivery_available?: boolean
}

export type Pupil = PupilListItem & {
  test_centre: string | null
  practical_test_time?: string | null
  practical_test_booking_ref?: string | null
  practical_test_cancel_by?: string | null
  practical_test_reminder_offsets?: number[]
  private_notes: string | null
  next_focus?: string | null
  last_lesson_summary?: string | null
  test_journey?: TestJourney | null
  finance?: import('./useFinance').FinanceSummary | null
  portal?: PortalStatus
  continuity?: PupilContinuity | null
  theory_status?: string | null
  theory_pass_date?: string | null
  theory_test_date?: string | null
  theory?: import('./useIntake').TheoryPayload | null
  licence_number?: string | null
  licence_expiry_date?: string | null
  date_of_birth?: string | null
  age_years?: number | null
  gender?: string | null
  gender_label?: string | null
  emergency_contact_name?: string | null
  emergency_contact_phone?: string | null
  eyesight_status?: string | null
  eyesight_status_label?: string | null
  eyesight_checked_on?: string | null
  wears_glasses?: boolean
  medical_notes?: string | null
  referred_by?: string | null
  payment_notes?: string | null
  contacts?: PupilContact[]
  places?: PupilPlace[]
  service_rates?: PupilServiceRate[]
  standard_rate?: { price_pence: number; price_label: string; label: string } | null
  licence?: {
    number?: string | null
    label: string
    urgency: string
    expires_on?: string | null
    expires_on_display?: string | null
  } | null
  profile_changes?: Array<{
    id: number
    summary: string
    changes: Array<{ field: string; from: string | null; to: string | null }>
    created_at: string
  }>
  learner_reported?: LearnerReported | null
  preferred_contact?: string | null
  availability_is_variable?: boolean
  intake_id?: number | null
  created_at: string
  updated_at: string
}

export type PupilCreatePayload = {
  first_name: string
  middle_name?: string | null
  last_name: string
  mobile: string
  email?: string
  default_pickup_address?: string
  date_of_birth?: string | null
  gender?: string | null
}

export type PupilUpdatePayload = {
  first_name?: string
  middle_name?: string | null
  last_name?: string
  mobile?: string
  email?: string | null
  default_pickup_address?: string | null
  test_date?: string | null
  test_centre?: string | null
  practical_test_time?: string | null
  practical_test_booking_ref?: string | null
  practical_test_cancel_by?: string | null
  practical_test_reminder_offsets?: number[] | null
  theory_status?: string | null
  theory_pass_date?: string | null
  theory_test_date?: string | null
  licence_number?: string | null
  licence_expiry_date?: string | null
  date_of_birth?: string | null
  gender?: string | null
  transmission?: string | null
  preferred_contact?: string | null
  emergency_contact_name?: string | null
  emergency_contact_phone?: string | null
  eyesight_status?: string | null
  eyesight_checked_on?: string | null
  wears_glasses?: boolean
  medical_notes?: string | null
  referred_by?: string | null
  payment_notes?: string | null
  contacts?: PupilContact[]
  available_from?: string | null
  private_notes?: string | null
}

export type AvailabilityWindow = {
  id?: number
  weekday: number
  weekday_label?: string
  mode: 'flexible' | 'after' | 'before' | 'between'
  start_time: string | null
  end_time: string | null
  label?: string
}

export type AvailabilityWrite = {
  weekday: number
  mode: 'flexible' | 'after' | 'before' | 'between'
  start_time?: string | null
  end_time?: string | null
}

export type ImportRowData = {
  first_name: string
  last_name: string
  mobile: string
  email: string | null
  default_pickup_address: string | null
  private_notes: string | null
}

export type ImportPreviewRow = {
  row_number: number
  status: 'ready' | 'error' | 'duplicate'
  errors: string[]
  duplicate: {
    match: string
    source: string
    message: string
    existing_learner_id?: number
    existing_name?: string
    other_row_number?: number
  } | null
  data: ImportRowData
  display_name: string
}

export type ImportPreview = {
  columns: string[]
  column_labels: Record<string, string>
  summary: {
    total: number
    ready: number
    errors: number
    duplicates: number
    can_import: boolean
  }
  rows: ImportPreviewRow[]
  help: string
}

export type ImportConfirmResult = {
  imported_count: number
  skipped_count: number
  failed_count: number
  message: string
  imported: { row_number: number; learner_id: number; full_name: string }[]
}

export type LearnerReportPreset = {
  id: string
  label: string
  from: string
  to: string
}

export type LearnerReportBreakdown = {
  key: string
  label: string
  count: number
}

export type LearnerReportSeriesPoint = {
  month: string
  label: string
  new_learners: number
  passed: number
  inactive: number
  net: number
  active_estimate: number
}

export type LearnerReport = {
  from: string
  to: string
  from_label: string
  to_label: string
  range_label: string
  learner_count: number
  breakdown: LearnerReportBreakdown[]
  new_learners: number
  passed: number
  inactive: number
  growth_percent: number | null
  growth_label: string
  series: LearnerReportSeriesPoint[]
  growth_series: number[]
  new_series: number[]
  month_labels: string[]
  status_counts: PupilStatusCounts
  presets: LearnerReportPreset[]
  hint: string
}

export function usePupils() {
  async function listPupils(
    q = '',
    status: PupilListStatus = 'all',
  ): Promise<{
    items: PupilListItem[]
    attention?: PupilListAttention
    status?: PupilListStatus
    counts?: PupilStatusCounts
    multi_instructor?: boolean
    instructors?: PupilListInstructor[]
    offers_both_transmissions?: boolean
  }> {
    const params = new URLSearchParams()
    if (q.trim()) params.set('q', q.trim())
    if (status !== 'all') params.set('status', status)
    const query = params.toString() ? `?${params.toString()}` : ''
    return await apiFetch<{
      items: PupilListItem[]
      attention?: PupilListAttention
      status?: PupilListStatus
      counts?: PupilStatusCounts
      multi_instructor?: boolean
      instructors?: PupilListInstructor[]
      offers_both_transmissions?: boolean
    }>(`/learners${query}`)
  }

  async function getPupil(id: number): Promise<Pupil> {
    return await apiFetch<Pupil>(`/learners/${id}`)
  }

  async function createPupil(payload: PupilCreatePayload): Promise<Pupil> {
    return await apiFetch<Pupil>('/learners', {
      method: 'POST',
      body: payload,
    })
  }

  async function updatePupil(id: number, payload: PupilUpdatePayload): Promise<Pupil> {
    return await apiFetch<Pupil>(`/learners/${id}`, {
      method: 'PUT',
      body: payload,
    })
  }

  async function archivePupil(id: number): Promise<Pupil> {
    return await apiFetch<Pupil>(`/learners/${id}/archive`, {
      method: 'POST',
    })
  }

  async function setPupilStatus(id: number, status: PupilStatus): Promise<Pupil> {
    return await apiFetch<Pupil>(`/learners/${id}/status`, {
      method: 'POST',
      body: { status },
    })
  }

  async function listAvailability(id: number): Promise<AvailabilityWindow[]> {
    const data = await apiFetch<{ items: AvailabilityWindow[] }>(`/learners/${id}/availability`)
    return data.items
  }

  async function replaceAvailability(
    id: number,
    items: AvailabilityWrite[],
  ): Promise<AvailabilityWindow[]> {
    const data = await apiFetch<{ items: AvailabilityWindow[] }>(`/learners/${id}/availability`, {
      method: 'PUT',
      body: { items },
    })
    return data.items
  }

  async function invitePortal(id: number): Promise<{
    learner_id: number
    learner_first_name: string
    email: string
    activated: boolean
    delivery_mode: 'email' | 'link'
    email_sent: boolean
    email_error: string | null
    invite_path: string
    invite_expires_at: string
    invite_expires_label: string
    message: string
    can_copy_link: boolean
  }> {
    return await apiFetch(`/learners/${id}/portal-invite`, {
      method: 'POST',
    })
  }

  async function previewPupilImport(file: File): Promise<ImportPreview> {
    const body = new FormData()
    body.append('file', file)
    return await apiFetch<ImportPreview>('/learners/import/preview', {
      method: 'POST',
      body,
    })
  }

  async function confirmPupilImport(payload: {
    rows: {
      row_number: number
      data: ImportRowData
      import_despite_duplicate?: boolean
    }[]
    include_duplicates?: boolean
  }): Promise<ImportConfirmResult> {
    return await apiFetch<ImportConfirmResult>('/learners/import/confirm', {
      method: 'POST',
      body: payload,
    })
  }

  async function listWaitingPupils(): Promise<PupilListItem[]> {
    const data = await apiFetch<{ items: PupilListItem[] }>('/learners/waiting')
    return data.items
  }

  async function fetchLearnerReport(from?: string, to?: string): Promise<LearnerReport> {
    const params = new URLSearchParams()
    if (from) params.set('from', from)
    if (to) params.set('to', to)
    const query = params.toString() ? `?${params.toString()}` : ''
    return await apiFetch<LearnerReport>(`/learners/report${query}`)
  }

  return {
    listPupils,
    getPupil,
    createPupil,
    updatePupil,
    archivePupil,
    setPupilStatus,
    listAvailability,
    replaceAvailability,
    invitePortal,
    previewPupilImport,
    confirmPupilImport,
    listWaitingPupils,
    fetchLearnerReport,
  }
}
