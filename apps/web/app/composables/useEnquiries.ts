export type EnquiryListItem = {
  id: number
  full_name: string
  postcode: string
  transmission: string | null
  status: string
  status_label: string
  created_at: string
  summary: string
  fit_hint: string | null
  path: string
}

export type EnquiryDetail = {
  id: number
  status: string
  status_label: string
  full_name: string
  first_name: string
  last_name: string
  email: string | null
  mobile: string
  postcode: string
  transmission: string | null
  experience_band: string | null
  experience_label: string | null
  availability: Record<string, unknown>
  desired_start: string | null
  desired_start_label: string | null
  theory_status: string | null
  practical_test_date: string | null
  message: string | null
  source: string
  source_tag: string | null
  service_interest: string | null
  created_at: string
  contacted_at: string | null
  converted_learner_id: number | null
  learner_path: string | null
  fit: {
    flags: Array<{ type: string; label: string }>
    area: { status: string; label: string }
    availability_summary: string
    suggested_times: Array<{
      date_label: string
      time_label: string
      starts_at_local: string
      duration_minutes: number
    }>
    suggested_times_count: number
    travel_note: string | null
  }
  duplicates: Array<{
    learner_id: number
    full_name: string
    reasons: string[]
    path: string
  }>
  actions: string[]
}

export type EnquiryStats = {
  month_label: string
  total: number
  new: number
  converted: number
  waiting: number
  declined: number
  by_source: Record<string, number>
}

export function useEnquiries() {
  async function list(status?: string, transmission?: string): Promise<EnquiryListItem[]> {
    const params = new URLSearchParams()
    if (status) params.set('status', status)
    if (transmission) params.set('transmission', transmission)
    const suffix = params.toString() ? `?${params.toString()}` : ''
    const data = await apiFetch<{ items: EnquiryListItem[] }>(`/enquiries${suffix}`)
    return data.items
  }

  async function fetchStats(): Promise<EnquiryStats> {
    return await apiFetch<EnquiryStats>('/enquiries/stats')
  }

  async function fetchEnquiry(id: number): Promise<EnquiryDetail> {
    return await apiFetch<EnquiryDetail>(`/enquiries/${id}`)
  }

  async function markContacted(id: number): Promise<EnquiryDetail> {
    return await apiFetch<EnquiryDetail>(`/enquiries/${id}/contact`, { method: 'POST', body: {} })
  }

  async function accept(id: number): Promise<EnquiryDetail> {
    return await apiFetch<EnquiryDetail>(`/enquiries/${id}/accept`, { method: 'POST', body: {} })
  }

  async function decline(id: number, reason?: string): Promise<EnquiryDetail> {
    return await apiFetch<EnquiryDetail>(`/enquiries/${id}/decline`, {
      method: 'POST',
      body: reason ? { reason } : {},
    })
  }

  async function convert(id: number): Promise<{
    learner_id: number
    path: string
    message: string
    suggested_times: EnquiryDetail['fit']['suggested_times']
  }> {
    return await apiFetch(`/enquiries/${id}/convert`, { method: 'POST', body: {} })
  }

  async function addToWaitingList(id: number): Promise<{
    learner_id: number
    path: string
    message: string
  }> {
    return await apiFetch(`/enquiries/${id}/waiting`, { method: 'POST', body: {} })
  }

  async function bookFirstLesson(id: number, startsAtLocal: string, durationMinutes?: number): Promise<{
    lesson_id: number
    path: string
    message: string
  }> {
    return await apiFetch(`/enquiries/${id}/book`, {
      method: 'POST',
      body: { starts_at_local: startsAtLocal, duration_minutes: durationMinutes },
    })
  }

  return {
    list,
    fetchStats,
    fetchEnquiry,
    markContacted,
    accept,
    decline,
    convert,
    addToWaitingList,
    bookFirstLesson,
  }
}
