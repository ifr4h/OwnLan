export type CalendarSettings = {
  connected: boolean
  subscription_url: string | null
  privacy_mode: 'full' | 'private'
  privacy_options: Array<{ value: string; label: string; description: string }>
  feed_created_at: string | null
  refresh_note: string
}

export type BusinessSettings = {
  display_name: string
  business_name: string
  contact_phone: string | null
  contact_email: string | null
  timezone: string
  default_lesson_duration_minutes: number
  default_hourly_rate_pence: number | null
  default_hourly_rate_label: string | null
  service_area: string | null
  cancellation_policy: string | null
  work_days: number[]
  work_start_time: string
  work_end_time: string
  booking_mode: 'manual' | 'request' | 'instant'
  learner_reschedule_mode: 'manual' | 'request' | 'instant'
  learner_can_cancel: boolean
  booking_minimum_notice_hours: number
  booking_advance_weeks: number
  booking_slot_increment_minutes: number
  booking_allowed_durations: number[]
  calendar: CalendarSettings
  timezone_options: string[]
  duration_options: number[]
}

export type BusinessSettingsUpdate = {
  display_name?: string
  business_name?: string
  contact_phone?: string | null
  contact_email?: string | null
  timezone?: string
  default_lesson_duration_minutes?: number
  default_hourly_rate_pence?: number
  default_hourly_rate?: string
  service_area?: string | null
  cancellation_policy?: string | null
  work_days?: number[]
  work_start_time?: string
  work_end_time?: string
  booking_mode?: 'manual' | 'request' | 'instant'
  learner_reschedule_mode?: 'manual' | 'request' | 'instant'
  learner_can_cancel?: boolean
  booking_minimum_notice_hours?: number
  booking_advance_weeks?: number
}

export function useSettings() {
  async function fetchSettings(): Promise<BusinessSettings> {
    return await apiFetch<BusinessSettings>('/settings')
  }

  async function updateSettings(payload: BusinessSettingsUpdate): Promise<BusinessSettings> {
    return await apiFetch<BusinessSettings>('/settings', {
      method: 'PUT',
      body: payload,
    })
  }

  async function connectCalendar(): Promise<CalendarSettings> {
    return await apiFetch<CalendarSettings>('/settings/calendar/connect', { method: 'POST', body: {} })
  }

  async function regenerateCalendar(): Promise<CalendarSettings> {
    return await apiFetch<CalendarSettings>('/settings/calendar/regenerate', { method: 'POST', body: {} })
  }

  async function revokeCalendar(): Promise<void> {
    await apiFetch('/settings/calendar/revoke', { method: 'POST', body: {} })
  }

  async function updateCalendarPrivacy(mode: 'full' | 'private'): Promise<CalendarSettings> {
    return await apiFetch<CalendarSettings>('/settings/calendar/privacy', {
      method: 'POST',
      body: { privacy_mode: mode },
    })
  }

  return {
    fetchSettings,
    updateSettings,
    connectCalendar,
    regenerateCalendar,
    revokeCalendar,
    updateCalendarPrivacy,
  }
}
