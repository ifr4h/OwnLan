export type PupilListItem = {
  id: number
  first_name: string
  last_name: string
  full_name: string
  mobile: string
  email: string | null
  default_pickup_address: string | null
  test_date: string | null
  lifecycle?: string
  waiting_list_joined_at?: string | null
  transmission?: string | null
  archived_at: string | null
  attention_hint?: string | null
  gap_matches?: WaitingGapMatches | null
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

export type TestJourney = {
  test_date: string
  test_date_display: string
  test_centre: string | null
  days_until: number
  weeks_until: string | null
  countdown_label: string
  lessons_booked_before_test: number
  progress_summary: string | null
  next_focus: string | null
  show_on_today: boolean
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
  private_notes: string | null
  next_focus?: string | null
  last_lesson_summary?: string | null
  test_journey?: TestJourney | null
  finance?: import('./useFinance').FinanceSummary | null
  portal?: PortalStatus
  continuity?: PupilContinuity | null
  theory_status?: string | null
  theory_pass_date?: string | null
  theory?: import('./useIntake').TheoryPayload | null
  learner_reported?: LearnerReported | null
  preferred_contact?: string | null
  availability_is_variable?: boolean
  intake_id?: number | null
  created_at: string
  updated_at: string
}

export type PupilCreatePayload = {
  first_name: string
  last_name: string
  mobile: string
  email?: string
  default_pickup_address?: string
}

export type PupilUpdatePayload = {
  first_name?: string
  last_name?: string
  mobile?: string
  email?: string | null
  default_pickup_address?: string | null
  test_date?: string | null
  test_centre?: string | null
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

export function usePupils() {
  async function listPupils(q = ''): Promise<{ items: PupilListItem[], attention?: PupilListAttention }> {
    const query = q.trim() ? `?q=${encodeURIComponent(q.trim())}` : ''
    return await apiFetch<{ items: PupilListItem[], attention?: PupilListAttention }>(`/learners${query}`)
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

  return {
    listPupils,
    getPupil,
    createPupil,
    updatePupil,
    archivePupil,
    listAvailability,
    replaceAvailability,
    invitePortal,
    previewPupilImport,
    confirmPupilImport,
    listWaitingPupils,
  }
}
