export type PracticalFaultType = 'driving' | 'serious' | 'dangerous'
export type PracticalTestResult = 'pass' | 'fail'

export type PracticalCatalogueArea = {
  area: string
  items: Array<{
    code: string
    aspect: string
    label: string
    skill_code: string
  }>
}

export type PracticalCatalogue = {
  fault_types: Array<{ code: PracticalFaultType; label: string }>
  areas: PracticalCatalogueArea[]
  result_rules: { pass: string; note: string }
}

export type PracticalTestFault = {
  id?: number
  fault_type: PracticalFaultType
  fault_code: string
  fault_label: string
  area: string
  aspect: string
  skill_code?: string | null
  count: number
}

export type PracticalTest = {
  id: number
  learner_id: number
  learner_first_name?: string | null
  learner_name?: string | null
  test_date: string
  date_display: string
  result: PracticalTestResult
  result_label: string
  test_centre?: string | null
  accompanied: boolean
  examiner_action: boolean
  driving_faults_count: number
  serious_faults_count: number
  dangerous_faults_count: number
  instructor_notes?: string | null
  faults?: PracticalTestFault[]
}

export type PracticalFaultBar = {
  fault_code: string
  fault_label: string
  area: string
  aspect: string
  skill_code?: string | null
  driving: number
  serious: number
  dangerous: number
  total: number
  bar_percent: number
}

export type PracticalAreaBar = {
  area: string
  driving: number
  serious: number
  dangerous: number
  total: number
  bar_percent: number
}

export type PracticalIndicator = {
  key: string
  label: string
  value: number | null
  value_label: string
  trigger: string
  triggered: boolean
}

export type PracticalTestStats = {
  from: string
  to: string
  range_label: string
  empty: boolean
  summary: {
    pupils_tested: number
    tests_taken: number
    tests_passed: number
    tests_failed: number
    pass_rate_percent: number | null
    pass_rate_label: string | null
    accompanied_count: number
    examiner_action_count: number
    examiner_action_percent: number | null
    avg_driving_faults: number | null
    avg_serious_faults: number | null
    avg_dangerous_faults: number | null
    total_driving_faults: number
    total_serious_faults: number
    total_dangerous_faults: number
  }
  indicators: PracticalIndicator[]
  faults_by_category: PracticalFaultBar[]
  faults_by_area: PracticalAreaBar[]
  top_faults: PracticalFaultBar[]
  recent_tests: PracticalTest[]
  mock_comparison: {
    items: Array<{
      fault_code: string
      fault_label: string
      area: string
      driving: number
      serious: number
      dangerous: number
      total: number
    }>
    note: string
  }
}

export type PracticalTestPayload = {
  learner_id: number
  test_date: string
  result: PracticalTestResult
  test_centre?: string | null
  accompanied?: boolean
  examiner_action?: boolean
  instructor_notes?: string | null
  mark_learner_passed?: boolean
  faults: Array<{
    fault_code: string
    fault_type: PracticalFaultType
    count: number
  }>
}

export function usePracticalTests() {
  const { apiFetch } = useApi()

  async function fetchCatalogue() {
    return await apiFetch<PracticalCatalogue>('/practical-tests/catalogue')
  }

  async function fetchStats(from?: string, to?: string) {
    const q = new URLSearchParams()
    if (from) q.set('from', from)
    if (to) q.set('to', to)
    const suffix = q.toString() ? `?${q}` : ''
    return await apiFetch<PracticalTestStats>(`/practical-tests/stats${suffix}`)
  }

  async function list(opts?: { from?: string; to?: string; learner_id?: number }) {
    const q = new URLSearchParams()
    if (opts?.from) q.set('from', opts.from)
    if (opts?.to) q.set('to', opts.to)
    if (opts?.learner_id) q.set('learner_id', String(opts.learner_id))
    const suffix = q.toString() ? `?${q}` : ''
    return await apiFetch<{ items: PracticalTest[]; total: number; from: string; to: string }>(
      `/practical-tests${suffix}`,
    )
  }

  async function listForLearner(learnerId: number) {
    return await apiFetch<{ items: PracticalTest[]; total: number }>(
      `/learners/${learnerId}/practical-tests`,
    )
  }

  async function view(id: number) {
    return await apiFetch<PracticalTest>(`/practical-tests/${id}`)
  }

  async function create(payload: PracticalTestPayload) {
    return await apiFetch<PracticalTest>('/practical-tests', {
      method: 'POST',
      body: payload,
    })
  }

  async function update(id: number, payload: Omit<PracticalTestPayload, 'learner_id'> & { learner_id?: number }) {
    return await apiFetch<PracticalTest>(`/practical-tests/${id}`, {
      method: 'PUT',
      body: payload,
    })
  }

  async function remove(id: number) {
    return await apiFetch<{ ok: true }>(`/practical-tests/${id}/delete`, { method: 'POST' })
  }

  return {
    fetchCatalogue,
    fetchStats,
    list,
    listForLearner,
    view,
    create,
    update,
    remove,
  }
}
