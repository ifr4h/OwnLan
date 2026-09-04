export type CompanionPermissions = {
  lessons?: boolean
  money?: boolean
  progress?: boolean
  learn?: boolean
  routes?: boolean
  test?: boolean
  practice?: boolean
  booking?: boolean
}

export type CompanionLearnerLink = {
  link_id: number
  learner_id: number
  organisation_id: number
  first_name: string | null
  full_name: string | null
  display_label: string
  relationship_label: string | null
  permissions: CompanionPermissions
}

export type CompanionMe = {
  account: { id: number; email: string; name: string }
  learners: CompanionLearnerLink[]
}

export type CompanionLearnerHome = {
  learner: { id: number; first_name: string; full_name: string }
  permissions: CompanionPermissions
  provenance: string
  next_lesson?: {
    id: number
    starts_at_label?: string
    duration_label?: string
    [key: string]: unknown
  } | null
  next_focus?: string | null
  last_lesson_summary?: string | null
  money?: {
    credit_minutes: number
    credit_label: string
    amount_due_pence: number
    amount_due_label: string
  }
  theory?: unknown
  practical_test?: unknown
  practice_focus?: string | null
  practice_resources?: Array<{
    id: number
    title: string
    note: string | null
    lesson_id: number
    scene?: Record<string, unknown>
  }>
  recent_practice?: Array<{
    id: number
    practised_at: string
    duration_minutes: number
    skill_codes: string[]
    feeling: string | null
    learner_reflection: string | null
    companion_note: string | null
  }>
  /** Present when practice sessions exist. */
  latest_practice_session_id?: number | null
  can_open_learn?: boolean
  can_open_routes?: boolean
}

export const COMPANION_PERMISSION_OPTIONS: Array<{ key: keyof CompanionPermissions; label: string }> = [
  { key: 'lessons', label: 'Lessons & schedule' },
  { key: 'money', label: 'Payments & lesson credit' },
  { key: 'progress', label: 'Progress' },
  { key: 'learn', label: 'Learning resources' },
  { key: 'routes', label: 'Routes' },
  { key: 'test', label: 'Test information' },
  { key: 'practice', label: 'Private practice' },
]

export function useCompanion() {
  const me = useState<CompanionMe | null>('companion-me', () => null)
  const ready = useState<boolean>('companion-ready', () => false)
  const isAuthenticated = computed(() => me.value !== null)

  async function fetchMe(): Promise<CompanionMe | null> {
    try {
      me.value = await apiFetch<CompanionMe>('/companion/me')
      return me.value
    } catch {
      me.value = null
      return null
    } finally {
      ready.value = true
    }
  }

  async function login(payload: { email: string; password: string }) {
    me.value = await apiFetch<CompanionMe>('/companion/login', { method: 'POST', body: payload })
    ready.value = true
    return me.value
  }

  async function activate(payload: { token: string; password: string }) {
    me.value = await apiFetch<CompanionMe>('/companion/activate', { method: 'POST', body: payload })
    ready.value = true
    return me.value
  }

  async function peekInvite(token: string) {
    return await apiFetch<{
      companion_name: string
      email: string
      already_activated: boolean
      learner_first_name: string | null
    }>(`/companion/invite?token=${encodeURIComponent(token)}`)
  }

  async function logout() {
    try {
      await apiFetch('/companion/logout', { method: 'POST' })
    } finally {
      me.value = null
      ready.value = true
    }
  }

  async function learnerHome(learnerId: number) {
    return await apiFetch<CompanionLearnerHome>(`/companion/learners/${learnerId}`)
  }

  async function addPracticeNote(learnerId: number, sessionId: number, companion_note: string) {
    return await apiFetch(`/companion/learners/${learnerId}/practice/${sessionId}/note`, {
      method: 'POST',
      body: { companion_note },
    })
  }

  return {
    me,
    ready,
    isAuthenticated,
    fetchMe,
    login,
    activate,
    peekInvite,
    logout,
    learnerHome,
    addPracticeNote,
  }
}
