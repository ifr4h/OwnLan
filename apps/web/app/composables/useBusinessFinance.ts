export type MoneyPreset = {
  id: string
  label: string
  from: string
  to: string
}

export type MoneyCategory = {
  value: string
  label: string
}

export type VehicleOption = {
  value: number
  label: string
}

export type WhoOwesRow = {
  learner_id: number
  learner_name: string
  amount_owed_pence: number
  amount_owed_label: string
  cta_path: string
}

export type IncomeRow = {
  id: number
  learner_id: number
  learner_name: string | null
  amount_pence: number
  amount_label: string
  method: string
  purpose: string
  recorded_at_display: string
  notes: string | null
}

export type ExpenseRow = {
  id: number
  amount_pence: number
  amount_label: string
  category: string
  category_label: string
  supplier: string | null
  payment_method: string | null
  vehicle_id: number | null
  has_receipt: boolean
  receipt_original_name: string | null
  spent_on: string
  spent_on_display: string
  notes: string | null
  voided_at: string | null
  void_reason: string | null
}

export type SpendingByCategory = {
  category: string
  label: string
  amount_pence: number
  amount_label: string
}

export type FinancialGoal = {
  id: number
  period_year: number
  period_month: number
  period_label: string
  target_pence: number
  target_label: string
}

export type DiaryCapacity = {
  usable_minutes: number
  usable_hours_label: string
  pupil_gap_matches: number
  distinct_pupils_matching: number
  gaps: Array<{
    date: string
    starts_at_display: string
    ends_at_display: string
    duration_minutes: number
    potential_value_label: string
  }>
  diary_path: string
}

export type MonthlyTrendRow = {
  month: string
  label: string
  teaching_income_pence: number
  teaching_income_label: string
  is_current: boolean
  is_partial: boolean
}

export type BusinessOverview = {
  from: string
  to: string
  from_label: string
  to_label: string
  range_label: string
  period_is_partial?: boolean
  money_received_pence: number
  money_received_label: string
  teaching_income_pence: number
  teaching_income_label: string
  booked_before_period_end_pence: number
  booked_before_period_end_label: string
  projected_from_bookings_pence: number
  projected_from_bookings_label: string
  still_owed_pence: number
  still_owed_label: string
  spending_pence: number
  spending_label: string
  left_after_spending_pence: number
  left_after_spending_label: string
  income_count: number
  expense_count: number
  payments_received_breakdown?: Array<{
    method: string
    label: string
    amount_pence: number
    amount_label: string
  }>
  who_owes: WhoOwesRow[]
  recent_income: IncomeRow[]
  expenses: ExpenseRow[]
  spending_by_category: SpendingByCategory[]
  categories: MoneyCategory[]
  presets: MoneyPreset[]
  vehicles: VehicleOption[]
  payment_methods: MoneyCategory[]
  goal: FinancialGoal | null
  goal_gap_from_bookings_pence: number | null
  goal_gap_from_bookings_label: string | null
  average_teaching_value: {
    pence_per_hour: number
    label: string
    teaching_minutes: number
  }
  estimated_additional_teaching_hours: number | null
  estimated_additional_teaching_label: string | null
  diary_capacity: DiaryCapacity
  monthly_trend: MonthlyTrendRow[]
}

export type CreateExpensePayload = {
  amount_pence?: number
  amount?: string
  category: string
  spent_on: string
  supplier?: string
  payment_method?: string
  vehicle_id?: number
  notes?: string
}

export type VehicleRow = {
  id: number
  registration: string
  make: string | null
  model: string | null
  display_name: string
  transmission: string
  transmission_label: string
  is_primary: boolean
  is_active: boolean
  notes: string | null
}

export type MileageRow = {
  id: number
  vehicle_id: number
  vehicle_registration: string | null
  vehicle_display_name: string | null
  logged_on: string
  logged_on_display: string
  distance_miles: number
  distance_label: string
  purpose: string
  purpose_label: string
  start_reading: number | null
  end_reading: number | null
  notes: string | null
}

export function useBusinessFinance() {
  async function fetchOverview(from?: string, to?: string): Promise<BusinessOverview> {
    const query = new URLSearchParams()
    if (from) query.set('from', from)
    if (to) query.set('to', to)
    const suffix = query.toString() ? `?${query.toString()}` : ''
    return await apiFetch<BusinessOverview>(`/business/overview${suffix}`)
  }

  async function fetchReport(from?: string, to?: string) {
    const query = new URLSearchParams()
    if (from) query.set('from', from)
    if (to) query.set('to', to)
    const suffix = query.toString() ? `?${query.toString()}` : ''
    return await apiFetch(`/business/report${suffix}`)
  }

  async function createExpense(payload: CreateExpensePayload): Promise<ExpenseRow> {
    return await apiFetch<ExpenseRow>('/business/expenses', {
      method: 'POST',
      body: payload,
    })
  }

  async function voidExpense(id: number, reason: string): Promise<ExpenseRow> {
    return await apiFetch<ExpenseRow>(`/business/expenses/${id}/void`, {
      method: 'POST',
      body: { reason },
    })
  }

  async function uploadReceipt(expenseId: number, file: File): Promise<ExpenseRow> {
    const form = new FormData()
    form.append('receipt', file)
    const res = await fetch(`/api/business/expenses/${expenseId}/receipt`, {
      method: 'POST',
      body: form,
      credentials: 'include',
    })
    if (!res.ok) {
      const data = await res.json().catch(() => ({}))
      throw new Error((data as { message?: string }).message || 'Could not upload receipt.')
    }
    return await res.json()
  }

  function receiptUrl(expenseId: number): string {
    return `/api/business/expenses/${expenseId}/receipt`
  }

  async function upsertGoal(payload: {
    period_year: number
    period_month: number
    target_pence?: number
    target?: string
  }): Promise<FinancialGoal> {
    return await apiFetch<FinancialGoal>('/business/goals', {
      method: 'POST',
      body: payload,
    })
  }

  async function deleteGoal(periodYear: number, periodMonth: number): Promise<void> {
    await apiFetch('/business/goals/delete', {
      method: 'POST',
      body: { period_year: periodYear, period_month: periodMonth },
    })
  }

  async function fetchVehicles(): Promise<VehicleRow[]> {
    const data = await apiFetch<{ vehicles: VehicleRow[] }>('/business/vehicles')
    return data.vehicles
  }

  async function createVehicle(payload: Record<string, unknown>): Promise<VehicleRow> {
    return await apiFetch<VehicleRow>('/business/vehicles', {
      method: 'POST',
      body: payload,
    })
  }

  async function updateVehicle(id: number, payload: Record<string, unknown>): Promise<VehicleRow> {
    return await apiFetch<VehicleRow>(`/business/vehicles/${id}`, {
      method: 'POST',
      body: payload,
    })
  }

  async function fetchVehicleDetail(id: number, year?: number) {
    const query = year ? `?year=${year}` : ''
    return await apiFetch<VehicleRow & {
      year: number
      expenses_by_category: SpendingByCategory[]
      mileage_miles: number
      mileage_label: string
    }>(`/business/vehicles/${id}${query}`)
  }

  async function fetchMileage(from?: string, to?: string): Promise<MileageRow[]> {
    const query = new URLSearchParams()
    if (from) query.set('from', from)
    if (to) query.set('to', to)
    const suffix = query.toString() ? `?${query.toString()}` : ''
    const data = await apiFetch<{ entries: MileageRow[] }>(`/business/mileage${suffix}`)
    return data.entries
  }

  async function createMileage(payload: Record<string, unknown>): Promise<MileageRow> {
    return await apiFetch<MileageRow>('/business/mileage', {
      method: 'POST',
      body: payload,
    })
  }

  async function deleteMileage(id: number): Promise<void> {
    await apiFetch(`/business/mileage/${id}/delete`, { method: 'POST', body: {} })
  }

  function exportUrl(from: string, to: string, type = 'combined'): string {
    const query = new URLSearchParams({ from, to, type })
    return `/api/business/export?${query.toString()}`
  }

  return {
    fetchOverview,
    fetchReport,
    createExpense,
    voidExpense,
    uploadReceipt,
    receiptUrl,
    upsertGoal,
    deleteGoal,
    fetchVehicles,
    createVehicle,
    updateVehicle,
    fetchVehicleDetail,
    fetchMileage,
    createMileage,
    deleteMileage,
    exportUrl,
  }
}

export function useAccountsPeriod() {
  const route = useRoute()
  const router = useRouter()
  const from = ref('')
  const to = ref('')

  function syncQuery() {
    void router.replace({
      query: {
        ...route.query,
        from: from.value || undefined,
        to: to.value || undefined,
      },
    })
  }

  function applyPreset(preset: MoneyPreset) {
    from.value = preset.from
    to.value = preset.to
    syncQuery()
  }

  function initFromRoute() {
    from.value = typeof route.query.from === 'string' ? route.query.from : ''
    to.value = typeof route.query.to === 'string' ? route.query.to : ''
  }

  return { from, to, applyPreset, initFromRoute, syncQuery }
}
