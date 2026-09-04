import { apiFetch } from '~/composables/useApi'
import type { Scene } from '~/utils/teaching/scene'

export type TeachingResourceSummary = {
  id: number
  kind: string
  title: string
  category: string | null
  description: string | null
  template_code: string | null
  skill_codes: string[]
  is_favourite: boolean
  updated_at: string
  created_at: string
}

export type TeachingResource = TeachingResourceSummary & {
  scene: Scene
}

export type TeachingTemplate = {
  code: string
  label: string
  category: string
}

export type LessonTeachingResource = {
  id: number
  lesson_id: number
  source_resource_id: number | null
  kind: string
  title: string
  scene: Scene
  learner_visible_note: string | null
  skill_codes: string[]
  route_moment_id: number | null
  learner_visible: boolean
  created_at: string
}

export type RouteMoment = {
  id: number
  lesson_id: number
  lesson_route_id: number
  recorded_at: string
  offset_seconds: number | null
  offset_label: string | null
  lat: number
  lng: number
  kind: string
  label: string | null
  learner_note: string | null
  learner_visible: boolean
}

export function useTeaching() {
  async function fetchTemplates(): Promise<TeachingTemplate[]> {
    const res = await apiFetch<{ items: TeachingTemplate[] }>('/teaching/templates')
    return res.items ?? []
  }

  async function fetchResources(opts?: {
    category?: string
    favourites?: boolean
  }): Promise<TeachingResourceSummary[]> {
    const q = new URLSearchParams()
    if (opts?.category) q.set('category', opts.category)
    if (opts?.favourites) q.set('favourites', '1')
    const qs = q.toString()
    const res = await apiFetch<{ items?: TeachingResourceSummary[] } | TeachingResourceSummary[]>(
      `/teaching/resources${qs ? `?${qs}` : ''}`,
    )
    if (Array.isArray(res)) return res
    return res.items ?? []
  }

  async function fetchResource(id: number): Promise<TeachingResource> {
    return await apiFetch<TeachingResource>(`/teaching/resources/${id}`)
  }

  async function createResource(body: {
    title: string
    kind?: string
    template_code?: string
    category?: string | null
    scene?: Scene
    skill_codes?: string[]
    is_favourite?: boolean
    description?: string | null
  }): Promise<TeachingResource> {
    return await apiFetch<TeachingResource>('/teaching/resources', {
      method: 'POST',
      body,
    })
  }

  async function updateResource(
    id: number,
    body: Partial<{
      title: string
      kind: string
      template_code: string
      category: string | null
      scene: Scene
      skill_codes: string[]
      is_favourite: boolean
      description: string | null
    }>,
  ): Promise<TeachingResource> {
    return await apiFetch<TeachingResource>(`/teaching/resources/${id}`, {
      method: 'PUT',
      body,
    })
  }

  async function duplicateResource(id: number): Promise<TeachingResource> {
    return await apiFetch<TeachingResource>(`/teaching/resources/${id}/duplicate`, {
      method: 'POST',
    })
  }

  async function archiveResource(id: number): Promise<void> {
    await apiFetch(`/teaching/resources/${id}/archive`, { method: 'POST' })
  }

  async function fetchLessonResources(lessonId: number): Promise<LessonTeachingResource[]> {
    const res = await apiFetch<{ items?: LessonTeachingResource[] } | LessonTeachingResource[]>(
      `/lessons/${lessonId}/resources`,
    )
    if (Array.isArray(res)) return res
    return res.items ?? []
  }

  async function attachToLesson(
    lessonId: number,
    body: {
      source_resource_id?: number
      scene?: Scene
      title?: string
      kind?: string
      learner_visible_note?: string
      route_moment_id?: number
      skill_codes?: string[]
      learner_visible?: boolean
    },
  ): Promise<LessonTeachingResource> {
    return await apiFetch<LessonTeachingResource>(`/lessons/${lessonId}/resources`, {
      method: 'POST',
      body,
    })
  }

  async function fetchMoments(lessonId: number): Promise<RouteMoment[]> {
    const res = await apiFetch<{ items: RouteMoment[] }>(`/lessons/${lessonId}/moments`)
    return res.items ?? []
  }

  async function updateMoment(
    momentId: number,
    body: Partial<{
      kind: string
      label: string | null
      learner_note: string | null
      learner_visible: boolean
    }>,
  ): Promise<RouteMoment> {
    return await apiFetch<RouteMoment>(`/moments/${momentId}`, {
      method: 'PATCH',
      body,
    })
  }

  return {
    fetchTemplates,
    fetchResources,
    fetchResource,
    createResource,
    updateResource,
    duplicateResource,
    archiveResource,
    fetchLessonResources,
    attachToLesson,
    fetchMoments,
    updateMoment,
  }
}
