import { apiFetch } from '~/composables/useApi'

export type ServiceKind = 'lesson' | 'mock_test' | 'refresher' | 'motorway' | 'test_day'
export type ServiceStatus = 'draft' | 'active' | 'inactive'
export type BookingAccess = 'instructor_only' | 'request' | 'instant'

export type CatalogueService = {
  id: number
  name: string
  kind: ServiceKind
  kind_label: string
  duration_minutes: number
  duration_label: string
  price_pence: number
  price_label: string
  status: ServiceStatus
  status_label: string
  visibility_public: boolean
  booking_access: BookingAccess
  booking_access_label: string
  is_default: boolean
  sort_order: number
  description?: string | null
}

export type CataloguePackage = {
  id: number
  label: string
  purchased_minutes: number
  hours_label: string
  price_pence: number
  price_label: string
  active: boolean
  portal_visible: boolean
  availability_label: string
}

export type PricingRule = {
  id: number
  label: string
  active: boolean
  days_of_week: number[]
  days_label: string
  time_after: string | null
  time_before: string | null
  adjustment_kind: 'add_pence' | 'percent' | 'set_pence'
  adjustment_value: number
  adjustment_label: string
  service_id: number | null
  summary: string
  sort_order: number
}

export type PupilRate = {
  id: number
  learner_id: number
  learner_name: string
  service_id: number | null
  service_name: string
  price_pence: number
  price_label: string
  effective_from: string
  effective_to: string | null
  note: string | null
}

export type ServicesHome = {
  services: CatalogueService[]
  packages: CataloguePackage[]
  pricing_rules: PricingRule[]
  pupil_rates: PupilRate[]
  kind_options: Array<{ value: string; label: string }>
  booking_access_options: Array<{ value: string; label: string }>
  status_options: Array<{ value: string; label: string }>
  duration_presets: number[]
  weekday_options: Array<{ value: number; label: string; short: string }>
}

export type ServiceDetail = {
  service: CatalogueService
  price_changes: Array<{
    id: number
    price_pence: number
    price_label: string
    effective_on: string
  }>
  kind_options: Array<{ value: string; label: string }>
  booking_access_options: Array<{ value: string; label: string }>
  status_options: Array<{ value: string; label: string }>
  duration_presets: number[]
}

export function useServices() {
  async function fetchHome(): Promise<ServicesHome> {
    return await apiFetch<ServicesHome>('/services')
  }

  async function fetchService(id: number): Promise<ServiceDetail> {
    return await apiFetch<ServiceDetail>(`/services/${id}`)
  }

  async function createService(payload: Record<string, unknown>) {
    return await apiFetch<{ service: CatalogueService }>('/services', {
      method: 'POST',
      body: payload,
    })
  }

  async function updateService(id: number, payload: Record<string, unknown>) {
    return await apiFetch<{ service: CatalogueService }>(`/services/${id}`, {
      method: 'PATCH',
      body: payload,
    })
  }

  async function schedulePriceChange(id: number, payload: Record<string, unknown>) {
    return await apiFetch(`/services/${id}/price-changes`, {
      method: 'POST',
      body: payload,
    })
  }

  async function createPackage(payload: Record<string, unknown>) {
    return await apiFetch('/services/packages', { method: 'POST', body: payload })
  }

  async function updatePackage(id: number, payload: Record<string, unknown>) {
    return await apiFetch(`/services/packages/${id}`, { method: 'PATCH', body: payload })
  }

  async function createPricingRule(payload: Record<string, unknown>) {
    return await apiFetch('/services/pricing-rules', { method: 'POST', body: payload })
  }

  async function updatePricingRule(id: number, payload: Record<string, unknown>) {
    return await apiFetch(`/services/pricing-rules/${id}`, { method: 'PATCH', body: payload })
  }

  async function createPupilRate(payload: Record<string, unknown>) {
    return await apiFetch('/services/pupil-rates', { method: 'POST', body: payload })
  }

  async function updatePupilRate(id: number, payload: Record<string, unknown>) {
    return await apiFetch(`/services/pupil-rates/${id}`, { method: 'PATCH', body: payload })
  }

  return {
    fetchHome,
    fetchService,
    createService,
    updateService,
    schedulePriceChange,
    createPackage,
    updatePackage,
    createPricingRule,
    updatePricingRule,
    createPupilRate,
    updatePupilRate,
  }
}
