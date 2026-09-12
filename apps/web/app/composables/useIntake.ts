export type IntakeStatus = 'open' | 'submitted' | 'accepted' | 'waiting' | 'discarded'

export type IntakeListItem = {
  id: number
  status: IntakeStatus | string
  display_name: string
  area: string | null
  transmission: string | null
  experience_label: string | null
  goal_label: string | null
  availability_summary: string | null
  submitted_at: string | null
  created_at: string
  invite_expires_at: string | null
  learner_id: number | null
  is_expired: boolean
  is_revoked: boolean
}

export type IntakeInviteResult = {
  id: number
  status: string
  invite_path: string
  invite_expires_at: string
  message: string
}

export type IntakeCreatePayload = {
  first_name?: string
  last_name?: string
  mobile?: string
  email?: string
}

export type TheoryPayload = {
  status: string
  pass_date: string | null
  test_date?: string | null
  test_date_display?: string | null
  days_until_test?: number | null
  expires_on: string | null
  expires_on_display: string | null
  days_until_expiry: number | null
  label: string
  detail?: string | null
  urgency: 'none' | 'ok' | 'approaching' | 'soon' | 'expired' | string
}

export type IntakeBrief = {
  id: number
  status: IntakeStatus | string
  submitted_at: string | null
  learner_id: number | null
  headline: {
    full_name: string
    tag: string
    transmission: string | null
    area: string | null
  }
  contact: {
    mobile: string | null
    email: string | null
    pickup: string | null
    preferred_contact: string | null
  }
  experience: {
    level: string
    lines: string[]
  }
  skills_learner_says: string[]
  theory: TheoryPayload | null
  practical: {
    booked: boolean
    date: string | null
    time: string | null
    centre: string | null
  }
  availability: {
    changes_often: boolean
    labels: string[]
    note: string | null
  }
  goal: string | null
  instructor_should_know: string | null
  confidence: string | null
  private_practice: string | null
  attention: { code: string; message: string }[]
  terms_acknowledged_at: string | null
  actions: {
    can_accept: boolean
    can_waitlist: boolean
  }
}

export type IntakeFinaliseResult = {
  intake_id: number
  learner_id: number
  lifecycle: string
  full_name: string
  message: string
}

export type IntakePeek = {
  status: string
  prefill: {
    first_name: string | null
    last_name: string | null
    mobile: string | null
    email: string | null
  }
  instructor: {
    display_name: string | null
    business_name: string | null
    service_area: string | null
    has_cancellation_policy: boolean
  }
  skill_groups: Record<string, string>
  sections: string[]
}

export type IntakeTerms = {
  business_name: string | null
  cancellation_policy: string | null
}

export type IntakeAnswers = {
  identity: {
    first_name: string
    last_name: string
    mobile: string
    email?: string | null
    default_pickup_address?: string | null
  }
  driving: {
    had_lessons_before: boolean
    transmission?: string | null
    lesson_hours_band?: string | null
    last_drove?: string | null
    skills_practised?: string[]
    remember_working_on?: string | null
    driven_before?: string | null
  }
  tests: {
    theory_status: string
    theory_pass_date?: string | null
    practical_booked: boolean
    practical_date?: string | null
    practical_time?: string | null
    practical_centre?: string | null
  }
  availability: {
    days: { weekday: number; slots: string[] }[]
    changes_often: boolean
    note?: string | null
  }
  about: {
    goal?: string | null
    instructor_should_know?: string | null
    confidence?: string | null
    private_practice?: string | null
    preferred_contact?: string | null
  }
  terms_acknowledged: boolean
}

export type IntakeSubmitResult = {
  status: string
  message: string
  first_name: string | null
}

export const INTAKE_SKILL_GROUPS: Record<string, string> = {
  controls: 'Controls & moving off',
  junctions: 'Junctions',
  roundabouts: 'Roundabouts',
  dual_carriageways: 'Dual carriageways',
  manoeuvres: 'Basic manoeuvres',
  independent: 'Independent driving',
  parking: 'Parking',
}

export function intakeStatusLabel(status: string, item?: Partial<IntakeListItem>): string {
  if (item?.is_revoked) return 'Revoked'
  if (status === 'open' && item?.is_expired) return 'Expired'
  switch (status) {
    case 'open':
      return 'Waiting for details'
    case 'submitted':
      return 'Ready to review'
    case 'accepted':
      return 'Accepted'
    case 'waiting':
      return 'On waitlist'
    case 'discarded':
      return 'Discarded'
    default:
      return status
  }
}

export function useIntake() {
  async function createIntake(payload: IntakeCreatePayload = {}): Promise<IntakeInviteResult> {
    return await apiFetch<IntakeInviteResult>('/intakes', {
      method: 'POST',
      body: payload,
    })
  }

  async function listIntakes(status?: string): Promise<IntakeListItem[]> {
    const query = status ? `?status=${encodeURIComponent(status)}` : ''
    const data = await apiFetch<{ items: IntakeListItem[] }>(`/intakes${query}`)
    return data.items
  }

  async function getIntakeBrief(id: number): Promise<IntakeBrief> {
    return await apiFetch<IntakeBrief>(`/intakes/${id}`)
  }

  async function acceptIntake(id: number): Promise<IntakeFinaliseResult> {
    return await apiFetch<IntakeFinaliseResult>(`/intakes/${id}/accept`, {
      method: 'POST',
    })
  }

  async function waitlistIntake(id: number): Promise<IntakeFinaliseResult> {
    return await apiFetch<IntakeFinaliseResult>(`/intakes/${id}/waitlist`, {
      method: 'POST',
    })
  }

  async function revokeIntake(id: number): Promise<{ id: number; status: string }> {
    return await apiFetch(`/intakes/${id}/revoke`, {
      method: 'POST',
    })
  }

  async function peekIntake(token: string): Promise<IntakePeek> {
    return await apiFetch<IntakePeek>(`/intake/peek?token=${encodeURIComponent(token)}`)
  }

  async function fetchIntakeTerms(token: string): Promise<IntakeTerms> {
    return await apiFetch<IntakeTerms>(`/intake/terms?token=${encodeURIComponent(token)}`)
  }

  async function submitIntake(token: string, answers: IntakeAnswers): Promise<IntakeSubmitResult> {
    return await apiFetch<IntakeSubmitResult>('/intake/submit', {
      method: 'POST',
      body: { token, answers },
    })
  }

  return {
    createIntake,
    listIntakes,
    getIntakeBrief,
    acceptIntake,
    waitlistIntake,
    revokeIntake,
    peekIntake,
    fetchIntakeTerms,
    submitIntake,
  }
}
