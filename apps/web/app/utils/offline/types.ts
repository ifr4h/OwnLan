export type OutboxStatus = 'pending' | 'syncing' | 'failed' | 'synced'

export type CompleteLessonPayload = {
  lessonId: number
  instructor_notes?: string | null
  learner_summary?: string | null
  next_focus?: string | null
  base_updated_at?: string | null
  skill_ids?: number[]
  skill_ratings?: Record<string, string>
}

export type OutboxItem = {
  id: string
  scopeKey: string
  type: 'complete_lesson'
  status: Exclude<OutboxStatus, 'synced'>
  createdAt: string
  updatedAt: string
  attempts: number
  lastError: string | null
  payload: CompleteLessonPayload
}

export type CachedToday = {
  scopeKey: string
  cachedAt: string
  data: unknown
}

export type CachedLesson = {
  scopeKey: string
  id: number
  cachedAt: string
  data: unknown
  localOnly?: boolean
}

export type CachedPupil = {
  scopeKey: string
  id: number
  cachedAt: string
  data: unknown
}

export function scopeKey(userId: number, organisationId: number): string {
  return `u${userId}-o${organisationId}`
}

export function newMutationId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  return `m-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`
}

export function isNetworkError(error: unknown): boolean {
  if (!error || typeof error !== 'object') return false
  const e = error as { name?: string; message?: string; statusCode?: number; status?: number }
  if (e.name === 'FetchError' && (e.statusCode === undefined || e.statusCode === 0)) return true
  if (typeof e.message === 'string') {
    const msg = e.message.toLowerCase()
    if (msg.includes('network') || msg.includes('failed to fetch') || msg.includes('offline')) {
      return true
    }
  }
  if (typeof navigator !== 'undefined' && navigator.onLine === false) return true
  return false
}
