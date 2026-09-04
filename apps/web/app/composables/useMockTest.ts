export type MockFaultType = 'driving' | 'serious' | 'dangerous'

export type MockFault = {
  id: number
  fault_type: MockFaultType
  fault_type_label: string
  fault_code: string
  fault_label: string
  skill_code?: string
  note?: string | null
  recorded_at: string
  time_display: string
  elapsed_seconds?: number
}

export type MockTest = {
  id: number
  learner_id: number
  learner_first_name?: string
  learner_name?: string
  lesson_id: number | null
  status: 'in_progress' | 'completed' | 'abandoned'
  result?: string | null
  result_label?: string | null
  started_at: string
  finished_at?: string | null
  elapsed_seconds?: number | null
  elapsed_display?: string | null
  driving_faults_count: number
  serious_faults_count: number
  dangerous_faults_count: number
  learner_summary?: string | null
  instructor_note?: string | null
  private_note?: string | null
  suggested_next_focus?: string | null
  date_display?: string
  faults?: MockFault[]
  fault_summary?: Array<{
    area: string
    items: Array<{
      label: string
      skill_code?: string
      driving: number
      serious: number
      dangerous: number
      fault_ids: number[]
    }>
  }>
  recent_faults?: MockFault[]
  suggested_next_focus_options?: Array<{ label: string; count: number; reason: string }>
}

export type MockCatalogue = {
  fault_types: Array<{ code: MockFaultType; label: string }>
  areas: Array<{
    area: string
    items: Array<{ code: string; aspect: string; label: string; skill_code: string }>
  }>
}

export function newClientOpId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  return `op-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`
}

export function newClientSessionId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  return `sess-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`
}

export function useMockTest() {
  const catalogue = useState<MockCatalogue | null>('mock-catalogue', () => null)

  async function fetchCatalogue() {
    if (catalogue.value) return catalogue.value
    catalogue.value = await apiFetch<MockCatalogue>('/mock-tests/catalogue')
    return catalogue.value
  }

  async function startForLesson(lessonId: number, clientSessionId?: string) {
    return await apiFetch<MockTest>(`/lessons/${lessonId}/mock-tests`, {
      method: 'POST',
      body: { client_session_id: clientSessionId },
    })
  }

  async function startForLearner(learnerId: number, clientSessionId?: string) {
    return await apiFetch<MockTest>(`/learners/${learnerId}/mock-tests`, {
      method: 'POST',
      body: { client_session_id: clientSessionId },
    })
  }

  async function getMock(id: number) {
    return await apiFetch<MockTest>(`/mock-tests/${id}`)
  }

  async function recordFault(
    mockId: number,
    data: { fault_type: MockFaultType; fault_code: string; client_op_id?: string },
  ) {
    return await apiFetch<MockFault>(`/mock-tests/${mockId}/faults`, {
      method: 'POST',
      body: data,
    })
  }

  async function undoFault(mockId: number, faultId: number) {
    return await apiFetch(`/mock-tests/${mockId}/faults/${faultId}/undo`, { method: 'POST' })
  }

  async function finishMock(mockId: number, data?: {
    instructor_note?: string
    learner_summary?: string
    private_note?: string
    suggested_next_focus?: string
  }) {
    return await apiFetch<MockTest>(`/mock-tests/${mockId}/finish`, {
      method: 'POST',
      body: data ?? {},
    })
  }

  async function abandonMock(mockId: number) {
    return await apiFetch<MockTest>(`/mock-tests/${mockId}/abandon`, { method: 'POST' })
  }

  async function applyNextFocus(mockId: number, nextFocus: string) {
    return await apiFetch(`/mock-tests/${mockId}/apply-next-focus`, {
      method: 'POST',
      body: { next_focus: nextFocus },
    })
  }

  async function listForLearner(learnerId: number) {
    return await apiFetch<{
      items: MockTest[]
      patterns: { last_mocks?: unknown[]; serious_trend?: unknown[] }
      comparison: unknown
    }>(`/learners/${learnerId}/mock-tests`)
  }

  async function fetchLearnerProgress(learnerId: number) {
    return await apiFetch(`/learners/${learnerId}/progress`)
  }

  async function fetchSkillDetail(learnerId: number, code: string) {
    return await apiFetch(`/learners/${learnerId}/skills/${code}`)
  }

  return {
    catalogue,
    fetchCatalogue,
    startForLesson,
    startForLearner,
    getMock,
    recordFault,
    undoFault,
    finishMock,
    abandonMock,
    applyNextFocus,
    listForLearner,
    fetchLearnerProgress,
    fetchSkillDetail,
  }
}
