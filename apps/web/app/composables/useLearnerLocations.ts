export const LOCATION_ICONS = [
  'home',
  'work',
  'school',
  'gym',
  'pin',
  'star',
  'train',
  'car',
] as const

export type LocationIconName = (typeof LOCATION_ICONS)[number]

export type LearnerLocation = {
  id: number
  learner_id: number
  label: string
  icon: LocationIconName | string
  address: string
  usage?: 'pickup' | 'dropoff' | 'both' | string
  usage_label?: string
  is_default: boolean
  sort_order: number
  shared_with_instructor?: boolean
}

export type LearnerLocationWrite = {
  label: string
  address: string
  icon?: LocationIconName | string
  usage?: 'pickup' | 'dropoff' | 'both' | string
  is_default?: boolean
}

export type PickupSelection = {
  pickup_location_id: number | null
  pickup_address: string | null
}

export function useLearnerLocations() {
  async function listForLearner(learnerId: number): Promise<LearnerLocation[]> {
    const data = await apiFetch<{ items: LearnerLocation[] }>(`/learners/${learnerId}/locations`)
    return data.items
  }

  async function createForLearner(
    learnerId: number,
    payload: LearnerLocationWrite,
  ): Promise<LearnerLocation> {
    return await apiFetch<LearnerLocation>(`/learners/${learnerId}/locations`, {
      method: 'POST',
      body: payload,
    })
  }

  async function updateForLearner(
    learnerId: number,
    id: number,
    payload: Partial<LearnerLocationWrite>,
  ): Promise<LearnerLocation> {
    return await apiFetch<LearnerLocation>(`/learners/${learnerId}/locations/${id}`, {
      method: 'PUT',
      body: payload,
    })
  }

  async function deleteForLearner(learnerId: number, id: number): Promise<void> {
    await apiFetch(`/learners/${learnerId}/locations/${id}`, { method: 'DELETE' })
  }

  async function setDefaultForLearner(learnerId: number, id: number): Promise<LearnerLocation> {
    return await apiFetch<LearnerLocation>(`/learners/${learnerId}/locations/${id}/default`, {
      method: 'POST',
    })
  }

  async function listPortalPlaces(): Promise<LearnerLocation[]> {
    const data = await apiFetch<{ items: LearnerLocation[] }>('/portal/places')
    return data.items
  }

  async function createPortalPlace(payload: LearnerLocationWrite): Promise<LearnerLocation> {
    return await apiFetch<LearnerLocation>('/portal/places', {
      method: 'POST',
      body: payload,
    })
  }

  async function updatePortalPlace(
    id: number,
    payload: Partial<LearnerLocationWrite>,
  ): Promise<LearnerLocation> {
    return await apiFetch<LearnerLocation>(`/portal/places/${id}`, {
      method: 'PUT',
      body: payload,
    })
  }

  async function deletePortalPlace(id: number): Promise<void> {
    await apiFetch(`/portal/places/${id}`, { method: 'DELETE' })
  }

  async function setDefaultPortalPlace(id: number): Promise<LearnerLocation> {
    return await apiFetch<LearnerLocation>(`/portal/places/${id}/default`, {
      method: 'POST',
    })
  }

  return {
    listForLearner,
    createForLearner,
    updateForLearner,
    deleteForLearner,
    setDefaultForLearner,
    listPortalPlaces,
    createPortalPlace,
    updatePortalPlace,
    deletePortalPlace,
    setDefaultPortalPlace,
  }
}
