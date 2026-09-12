import { apiFetch } from '~/composables/useApi'

export type BookingWeek = {
  week_key: string
  week_label: string
  days: Array<{
    date: string
    date_label: string
    times: Array<{
      starts_at_local: string
      starts_at_time: string
      ends_at_time: string
      recommendation?: string | null
    }>
  }>
}

export type BookingAvailability = {
  booking_mode: 'manual' | 'request' | 'instant'
  reschedule_mode: 'manual' | 'request' | 'instant'
  can_cancel: boolean
  duration_minutes: number
  duration_options: number[]
  pickup_address: string | null
  pickup_options: Array<{ label: string; address: string | null }>
  suggested: {
    date_label: string
    starts_at_local: string
    starts_at_time: string
    ends_at_time: string
    recommendation?: string | null
  } | null
  days: BookingWeek[]
  total_slots: number
  price: {
    amount_pence: number
    amount_label: string
    included_in_package: boolean
    package_balance_after_label: string
  }
}

export type BookingRequest = {
  id: number
  type: 'book' | 'reschedule'
  status: string
  original_lesson_id?: number | null
  lesson_id?: number | null
  starts_at_local: string
  starts_at_display: string
  starts_at_day: string
  starts_at_time: string
  ends_at_time: string
  duration_minutes: number
  duration_label: string
  pickup_address: string | null
  decline_reason?: string | null
  suggested?: {
    starts_at_local: string
    starts_at_day: string
    starts_at_time: string
    ends_at_time: string
  }
}

export type BookingSettings = {
  booking_mode: string
  reschedule_mode: string
  can_book: boolean
  can_request: boolean
  can_instant_book: boolean
  can_cancel: boolean
  cancellation_policy: string | null
  cancellation_notice_hours?: number
  cancellation_late_policy?: 'charge' | 'decide'
  booking_payment_policy?: string
  requires_payment_to_confirm?: boolean
}

export function usePortalBooking() {
  async function fetchSettings(): Promise<BookingSettings> {
    return await apiFetch<BookingSettings>('/portal/booking/settings')
  }

  async function fetchAvailability(params: Record<string, string | number | undefined> = {}): Promise<BookingAvailability> {
    const query = new URLSearchParams()
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== '') {
        query.set(key, String(value))
      }
    }
    const qs = query.toString()

    return await apiFetch<BookingAvailability>(`/portal/booking/availability${qs ? `?${qs}` : ''}`)
  }

  async function submitBooking(payload: Record<string, unknown>) {
    return await apiFetch<{ outcome: string; request: BookingRequest; lesson?: Record<string, unknown> }>(
      '/portal/booking/requests',
      { method: 'POST', body: payload },
    )
  }

  async function withdrawRequest(id: number) {
    return await apiFetch(`/portal/booking/requests/${id}/withdraw`, { method: 'POST' })
  }

  async function acceptCounter(id: number) {
    return await apiFetch(`/portal/booking/requests/${id}/accept-counter`, { method: 'POST' })
  }

  async function cancelLesson(id: number, reason?: string) {
    return await apiFetch(`/portal/lessons/${id}/cancel`, {
      method: 'POST',
      body: reason ? { reason } : {},
    })
  }

  async function createBookingHold(payload: {
    starts_at_local: string
    duration_minutes?: number
    pickup_address?: string | null
  }) {
    return await apiFetch<{
      hold_id: number
      starts_at_local: string
      duration_minutes: number
      price_pence: number
      price_label: string
      expires_at: string
      expires_in_seconds: number
    }>('/portal/booking/hold', { method: 'POST', body: payload })
  }

  return {
    fetchSettings,
    fetchAvailability,
    submitBooking,
    withdrawRequest,
    acceptCounter,
    cancelLesson,
    createBookingHold,
  }
}

export function useInstructorBooking() {
  async function listPending() {
    return await apiFetch<{ items: Array<BookingRequest & { learner_name: string; learner_first_name: string }> }>(
      '/booking-requests',
    )
  }

  async function accept(id: number) {
    return await apiFetch(`/booking-requests/${id}/accept`, { method: 'POST', body: {} })
  }

  async function decline(id: number, reason?: string) {
    return await apiFetch(`/booking-requests/${id}/decline`, {
      method: 'POST',
      body: reason ? { reason } : {},
    })
  }

  async function suggest(id: number, startsAtLocal: string) {
    return await apiFetch(`/booking-requests/${id}/suggest`, {
      method: 'POST',
      body: { starts_at_local: startsAtLocal },
    })
  }

  async function availabilityForLearner(learnerId: number, params: Record<string, string | number | undefined> = {}) {
    const query = new URLSearchParams()
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== '') {
        query.set(key, String(value))
      }
    }
    const qs = query.toString()

    return await apiFetch<BookingAvailability>(`/learners/${learnerId}/booking-availability${qs ? `?${qs}` : ''}`)
  }

  return { listPending, accept, decline, suggest, availabilityForLearner }
}
