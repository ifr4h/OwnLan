export type FinanceSummary = {
  learner_id: number
  learner_name: string
  credit_minutes: number
  credit_label: string
  credit_remaining_line?: string
  amount_owed_pence: number
  amount_owed_label: string
  owes_money: boolean
  money_status_label: string
}

export type FinanceSnapshot = {
  credit_minutes: number
  credit_label: string
  credit_remaining_line: string
  has_prepaid_credit: boolean
  owes_money: boolean
  amount_owed_pence: number
  amount_owed_label: string
  money_status_label: string
  show_on_teaching: boolean
  teaching_line: string | null
}

export type CompletionAftermath = {
  headline: string
  learner_first_name: string
  learner_name: string
  credit_minutes: number
  credit_line: string
  has_prepaid_credit: boolean
  owes_money: boolean
  amount_owed_pence: number
  amount_owed_label: string
  money_status_label: string
  settlement: string | null
  primary_cta: 'book_next' | 'record_payment' | string
  primary_cta_label: string
  primary_cta_path: string
  secondary_cta: 'book_next' | null | string
  secondary_cta_label: string | null
  secondary_cta_path: string | null
}

export type NoShowAftermath = {
  headline: string
  learner_first_name: string
  learner_name: string
  financial_line: string | null
  settlement: string | null
  owes_money: boolean
  amount_owed_pence: number
  amount_owed_label: string
  primary_cta: 'record_payment' | null | string
  primary_cta_label: string | null
  primary_cta_path: string | null
}

export type LessonFinance = {
  snapshot?: FinanceSnapshot
  settlement?: string | null
  summary?: FinanceSummary
  aftermath?: CompletionAftermath | NoShowAftermath | null
  package_usage?: unknown
  charge?: unknown
  payment?: unknown
}

export type LearnerPackage = {
  id: number
  learner_id: number
  label: string | null
  purchased_minutes: number
  remaining_minutes: number
  used_minutes: number
  purchased_label: string
  remaining_label: string
  price_pence: number
  price_label: string
  payment_id: number | null
  purchased_at: string
  notes: string | null
  status: 'active' | 'exhausted' | 'voided' | string
  voided_at: string | null
  void_reason: string | null
}

export type FinanceHistoryItem = {
  id: string
  kind: 'payment_received' | 'package_purchased' | 'credit_consumed' | 'lesson_charge' | string
  at: string
  at_display: string
  title: string
  detail: string
  amount_pence?: number
  amount_label?: string
  minutes?: number
  voided?: boolean
  counts_as_income?: boolean
  payment_id?: number
  package_id?: number
  lesson_id?: number
}

export type FinancePanel = {
  summary: FinanceSummary
  packages: LearnerPackage[]
  history: FinanceHistoryItem[]
}

export type CreatePackagePayload = {
  purchased_hours: number | string
  price_pence?: number
  price?: string
  label?: string
  notes?: string
  record_payment?: boolean
  payment_method?: 'cash' | 'bank_transfer' | 'other'
  payment_notes?: string
}

export type RecordPaymentPayload = {
  amount_pence?: number
  amount?: string
  method: 'cash' | 'bank_transfer' | 'other'
  notes?: string
}

/** Convert pounds string like "40" / "40.50" to integer pence in the client. */
export function poundsInputToPence(raw: string): number | null {
  const cleaned = raw.replace(/£/g, '').replace(/,/g, '').trim()
  if (!/^\d+(\.\d{1,2})?$/.test(cleaned)) return null
  if (cleaned.includes('.')) {
    const [pounds, frac] = cleaned.split('.')
    return Number(pounds) * 100 + Number(frac.padEnd(2, '0'))
  }
  return Number(cleaned) * 100
}

export function useFinance() {
  async function fetchPanel(learnerId: number): Promise<FinancePanel> {
    return await apiFetch<FinancePanel>(`/learners/${learnerId}/finance`)
  }

  async function createPackage(learnerId: number, payload: CreatePackagePayload) {
    return await apiFetch<{
      package: LearnerPackage
      payment: unknown
      summary: FinanceSummary
    }>(`/learners/${learnerId}/packages`, {
      method: 'POST',
      body: payload,
    })
  }

  async function recordPayment(learnerId: number, payload: RecordPaymentPayload) {
    return await apiFetch<{
      payment: unknown
      allocated_pence: number
      summary: FinanceSummary
    }>(`/learners/${learnerId}/payments`, {
      method: 'POST',
      body: payload,
    })
  }

  async function voidPayment(paymentId: number, reason: string) {
    return await apiFetch<{ summary: FinanceSummary }>(`/payments/${paymentId}/void`, {
      method: 'POST',
      body: { reason },
    })
  }

  async function voidPackage(packageId: number, reason: string) {
    return await apiFetch<{ summary: FinanceSummary }>(`/packages/${packageId}/void`, {
      method: 'POST',
      body: { reason },
    })
  }

  return {
    fetchPanel,
    createPackage,
    recordPayment,
    voidPayment,
    voidPackage,
    poundsInputToPence,
  }
}
