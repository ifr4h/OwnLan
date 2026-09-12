export type ProfileAnalytics = {
  month_label: string
  visits: number
  enquiries_started: number
  enquiries_submitted: number
  pupils_added: number
  enquiries_by_source: Record<string, number>
}

export type ProfileEditor = {
  profile: {
    status: string
    slug: string
    share_url: string | null
    display_name: string
    business_name: string | null
    intro: string | null
    accent_colour: string | null
    transmission: string | null
    teaching_areas: string[]
    teaching_styles: string[]
    languages: string | null
    adi_status: string | null
    years_teaching: number | null
    vehicle_summary: string | null
    dual_controls: boolean
    teaches_gender: string
    teaches_gender_label?: string
    public_pricing: Array<{ duration_minutes: number; price_pence: number; label?: string | null }>
    services: Array<Record<string, unknown>>
    faqs: Array<{ question: string; answer: string }>
    social_links: Record<string, string>
    contact_phone: string | null
    contact_email: string | null
    whatsapp: string | null
    show_phone: boolean
    show_email: boolean
    allow_indexing: boolean
    acquisition_mode: string
    acquisition_label: string
    allow_waiting_list: boolean
    photo_url: string | null
    cover_url: string | null
    updated_at: string | null
  }
  publish_blockers: string[]
  acquisition_options: Array<{ value: string; label: string }>
  transmission_options: Array<{ value: string; label: string }>
  adi_status_options: Array<{ value: string; label: string }>
  teaches_gender_options: Array<{ value: string; label: string }>
  teaching_style_options: Array<{ value: string; label: string }>
  service_type_options: Array<{ value: string; label: string }>
  source_link_tags: string[]
  analytics: ProfileAnalytics
}

export function useProfileSettings() {
  async function fetchProfile(): Promise<ProfileEditor> {
    return await apiFetch<ProfileEditor>('/settings/profile')
  }

  async function updateProfile(payload: Record<string, unknown>): Promise<ProfileEditor> {
    return await apiFetch<ProfileEditor>('/settings/profile', { method: 'PUT', body: payload })
  }

  async function publish(): Promise<ProfileEditor> {
    return await apiFetch<ProfileEditor>('/settings/profile/publish', { method: 'POST', body: {} })
  }

  async function unpublish(): Promise<ProfileEditor> {
    return await apiFetch<ProfileEditor>('/settings/profile/unpublish', { method: 'POST', body: {} })
  }

  async function preview(): Promise<Record<string, unknown>> {
    return await apiFetch('/settings/profile/preview')
  }

  async function uploadPhoto(file: File): Promise<ProfileEditor> {
    const form = new FormData()
    form.append('photo', file)
    return await apiFetch<ProfileEditor>('/settings/profile/photo', {
      method: 'POST',
      body: form,
    })
  }

  async function uploadCover(file: File): Promise<ProfileEditor> {
    const form = new FormData()
    form.append('cover', file)
    return await apiFetch<ProfileEditor>('/settings/profile/cover', {
      method: 'POST',
      body: form,
    })
  }

  function sourceLink(baseUrl: string | null, tag: string): string | null {
    if (!baseUrl) return null
    const url = new URL(baseUrl)
    url.searchParams.set('src', tag)
    return url.toString()
  }

  return { fetchProfile, updateProfile, publish, unpublish, preview, uploadPhoto, uploadCover, sourceLink }
}
