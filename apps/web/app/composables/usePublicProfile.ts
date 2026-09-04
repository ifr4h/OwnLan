export type PublicService = {
  id: string
  name: string
  description: string | null
  duration_minutes: number | null
  duration_label: string | null
  price_pence: number
  price_label: string
  type: string
}

export type PublicProfile = {
  slug: string
  preview?: boolean
  display_name: string
  business_name: string | null
  headline: string
  subheadline: string | null
  intro: string | null
  photo_url: string | null
  cover_url: string | null
  branding: {
    accent_colour: string
    accent_foreground: string
  }
  transmission: string | null
  transmission_code: string | null
  acquisition: {
    code: string
    label: string
    allows_enquiry: boolean
    allows_waiting_list: boolean
  }
  acquisition_label: string
  allows_enquiry: boolean
  allows_waiting_list: boolean
  cta_label: string | null
  teaching_areas: string[]
  teaching_styles: string[]
  languages: string | null
  adi_status: string | null
  years_teaching: number | null
  vehicle_summary: string | null
  dual_controls: boolean
  services: PublicService[]
  pricing: Array<{
    duration_minutes: number
    price_pence: number
    label: string | null
    price_label: string
  }>
  pricing_from_label: string | null
  faqs: Array<{ question: string; answer: string }>
  contact: {
    phone?: string
    email?: string
    whatsapp_url?: string
  }
  social_links: Record<string, string>
  share_url: string
  seo: {
    title: string
    description: string
    canonical: string
    robots: string
    og_image: string | null
  }
  structured_data: Record<string, unknown>
  powered_by_ownlane: boolean
  updated_at?: string
}

export type EnquirySubmitPayload = {
  first_name: string
  last_name: string
  mobile: string
  email?: string
  postcode: string
  transmission?: string
  experience_band?: string
  availability?: { days: Array<{ weekday: number; slots: string[] }> }
  desired_start?: string
  theory_status?: string
  practical_test_date?: string
  message?: string
  source_tag?: string
  service_interest?: string
  website?: string
}

export type PublicAnalyticsEvent = 'view' | 'enquiry_started' | 'enquiry_submitted' | 'service_click'

export function usePublicProfile() {
  async function fetchProfile(slug: string): Promise<PublicProfile> {
    return await apiFetch<PublicProfile>(`/public/instructors/${encodeURIComponent(slug)}`)
  }

  async function checkArea(slug: string, postcode: string): Promise<{ status: string; label: string }> {
    const q = encodeURIComponent(postcode)
    return await apiFetch(`/public/instructors/${encodeURIComponent(slug)}/area-check?postcode=${q}`)
  }

  async function submitEnquiry(slug: string, payload: EnquirySubmitPayload): Promise<{
    status: string
    first_name: string
    instructor_name: string
    message: string
  }> {
    return await apiFetch(`/public/instructors/${encodeURIComponent(slug)}/enquire`, {
      method: 'POST',
      body: payload,
    })
  }

  async function trackEvent(slug: string, event: PublicAnalyticsEvent, sourceTag?: string): Promise<void> {
    try {
      await apiFetch(`/public/instructors/${encodeURIComponent(slug)}/track`, {
        method: 'POST',
        body: { event, source_tag: sourceTag },
      })
    } catch {
      // Analytics should not block the page.
    }
  }

  return { fetchProfile, checkArea, submitEnquiry, trackEvent }
}
