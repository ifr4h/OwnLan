import { apiFetch } from '~/composables/useApi'
import type { Lesson } from '~/composables/useLessons'
import type { Pupil } from '~/composables/usePupils'
import type { TodayResponse } from '~/composables/useToday'
import * as db from './db'
import { cacheTodaySnapshot } from './cache'
import { isNetworkError, type OutboxItem } from './types'

export type SyncPhase = 'idle' | 'offline' | 'syncing' | 'back_online'

let flushing = false

export async function prefetchTeachingDay(scope: string): Promise<TodayResponse> {
  const today = await apiFetch<TodayResponse>('/today')
  const learnerIds = [...new Set(today.lessons.map(l => l.learner_id))]
  const pupils: Pupil[] = []
  for (const id of learnerIds) {
    try {
      pupils.push(await apiFetch<Pupil>(`/learners/${id}`))
    } catch {
      // Skip individual pupil failures; today still caches.
    }
  }
  await cacheTodaySnapshot(scope, today, pupils)
  return today
}

export async function flushOutbox(scope: string): Promise<{ synced: number; failed: number }> {
  if (flushing) {
    return { synced: 0, failed: 0 }
  }
  flushing = true
  let synced = 0
  let failed = 0
  try {
    const items = await db.listOutbox(scope)
    for (const item of items) {
      if (item.status === 'syncing') continue
      const ok = await syncOne(item)
      if (ok) synced += 1
      else failed += 1
    }
  } finally {
    flushing = false
  }
  return { synced, failed }
}

async function syncOne(item: OutboxItem): Promise<boolean> {
  const next: OutboxItem = {
    ...item,
    status: 'syncing',
    updatedAt: new Date().toISOString(),
    attempts: item.attempts + 1,
  }
  await db.putOutboxItem(next)

  try {
    if (item.type === 'complete_lesson') {
      const done = await apiFetch<Lesson>(`/lessons/${item.payload.lessonId}/complete`, {
        method: 'POST',
        body: {
          client_mutation_id: item.id,
          instructor_notes: item.payload.instructor_notes,
          learner_summary: item.payload.learner_summary,
          next_focus: item.payload.next_focus,
          skill_ids: item.payload.skill_ids,
          skill_ratings: item.payload.skill_ratings,
        },
      })
      await db.putLesson({
        scopeKey: item.scopeKey,
        id: item.payload.lessonId,
        cachedAt: new Date().toISOString(),
        data: done,
      })
    }
    await db.deleteOutboxItem(item.id)
    return true
  } catch (error) {
    if (isAuthError(error)) {
      await db.putOutboxItem({
        ...next,
        status: 'failed',
        lastError: 'auth',
        updatedAt: new Date().toISOString(),
      })
      return false
    }
    const transient = isNetworkError(error) || isServerError(error)
    await db.putOutboxItem({
      ...next,
      status: transient ? 'pending' : 'failed',
      lastError: transient ? 'network' : extractMessage(error),
      updatedAt: new Date().toISOString(),
    })
    return false
  }
}

function isAuthError(error: unknown): boolean {
  const status = (error as { statusCode?: number; status?: number })?.statusCode
    ?? (error as { status?: number })?.status
  return status === 401 || status === 403
}

function isServerError(error: unknown): boolean {
  const status = (error as { statusCode?: number; status?: number })?.statusCode
    ?? (error as { status?: number })?.status
  return typeof status === 'number' && status >= 500
}

function extractMessage(error: unknown): string {
  if (error && typeof error === 'object' && 'data' in error) {
    const data = (error as { data?: { message?: string } }).data
    if (data?.message) return data.message
  }
  return 'sync_failed'
}

export async function refreshAfterSync(scope: string): Promise<void> {
  try {
    await prefetchTeachingDay(scope)
  } catch {
    // Keep local cache if refresh fails.
  }
}
