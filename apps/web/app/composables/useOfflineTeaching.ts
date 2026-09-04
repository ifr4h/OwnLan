import type { Lesson } from '~/composables/useLessons'
import type { TodayResponse } from '~/composables/useToday'
import { apiFetch } from '~/composables/useApi'
import { enqueueCompleteLesson, readCachedLesson, readCachedToday } from '~/utils/offline/cache'
import * as db from '~/utils/offline/db'
import { flushOutbox, prefetchTeachingDay, refreshAfterSync } from '~/utils/offline/sync'
import { isNetworkError, scopeKey } from '~/utils/offline/types'

export type SyncBanner = 'hidden' | 'offline' | 'syncing' | 'back_online'

export function useOfflineTeaching() {
  const { me } = useAuth()
  const online = useState<boolean>('offline-online', () => true)
  const banner = useState<SyncBanner>('offline-banner', () => 'hidden')
  const pendingCount = useState<number>('offline-pending', () => 0)
  const authBlocked = useState<boolean>('offline-auth-blocked', () => false)

  const activeScope = computed(() => {
    if (!me.value?.user?.id || !me.value.organisation?.id) return null
    return scopeKey(me.value.user.id, me.value.organisation.id)
  })

  function refreshPending() {
    const scope = activeScope.value
    if (!scope) {
      pendingCount.value = 0
      return
    }
    void db.countPendingOutbox(scope).then((n) => {
      pendingCount.value = n
    }).catch(() => {
      pendingCount.value = 0
    })
  }

  async function runSync(showBackOnline: boolean) {
    const scope = activeScope.value
    if (!scope || !online.value) return
    banner.value = 'syncing'
    authBlocked.value = false
    const result = await flushOutbox(scope)
    refreshPending()
    if (result.failed === 0) {
      await refreshAfterSync(scope)
    }
    const items = await db.listOutbox(scope)
    if (items.some(i => i.lastError === 'auth')) {
      authBlocked.value = true
    }
    if (showBackOnline && result.failed === 0) {
      banner.value = 'back_online'
      window.setTimeout(() => {
        if (banner.value === 'back_online') banner.value = 'hidden'
      }, 2200)
    } else if (!online.value) {
      banner.value = 'offline'
    } else if (pendingCount.value > 0) {
      banner.value = 'hidden'
    } else {
      banner.value = 'hidden'
    }
  }

  function bindConnectivity() {
    if (!import.meta.client) return
    online.value = navigator.onLine
    banner.value = navigator.onLine ? 'hidden' : 'offline'

    const onOnline = () => {
      online.value = true
      void runSync(true)
    }
    const onOffline = () => {
      online.value = false
      banner.value = 'offline'
    }

    window.addEventListener('online', onOnline)
    window.addEventListener('offline', onOffline)
    refreshPending()
    if (navigator.onLine) {
      void runSync(false)
    }

    return () => {
      window.removeEventListener('online', onOnline)
      window.removeEventListener('offline', onOffline)
    }
  }

  async function loadToday(): Promise<{ data: TodayResponse; fromCache: boolean }> {
    const scope = activeScope.value
    if (!scope) {
      throw new Error('Not signed in.')
    }

    if (online.value) {
      try {
        const data = await prefetchTeachingDay(scope)
        refreshPending()
        return { data, fromCache: false }
      } catch (error) {
        if (!isNetworkError(error)) throw error
        online.value = false
        banner.value = 'offline'
      }
    }

    const cached = await readCachedToday(scope)
    if (!cached) {
      throw new Error('Today isn’t available offline yet. Open OwnLane online first.')
    }
    return { data: cached, fromCache: true }
  }

  async function loadLesson(id: number): Promise<{ data: Lesson; fromCache: boolean; syncLabel: string | null }> {
    const scope = activeScope.value
    if (!scope) throw new Error('Not signed in.')

    if (online.value) {
      try {
        const data = await apiFetch<Lesson>(`/lessons/${id}`)
        await db.putLesson({
          scopeKey: scope,
          id: data.id,
          cachedAt: new Date().toISOString(),
          data,
        })
        return { data, fromCache: false, syncLabel: null }
      } catch (error) {
        if (!isNetworkError(error)) throw error
        online.value = false
        banner.value = 'offline'
      }
    }

    const cached = await readCachedLesson(scope, id)
    if (!cached) {
      throw new Error('This lesson isn’t available offline. Open it once while online.')
    }
    const pending = (await db.listOutbox(scope)).some(
      i => i.type === 'complete_lesson' && i.payload.lessonId === id,
    )
    return {
      data: cached,
      fromCache: true,
      syncLabel: pending ? 'Waiting to sync' : 'Saved on this device',
    }
  }

  async function completeLessonLocalFirst(input: {
    lessonId: number
    instructor_notes?: string
    learner_summary?: string
    next_focus?: string
    base_updated_at?: string | null
    skill_ids?: number[]
    skill_ratings?: Record<string, string>
  }): Promise<Lesson> {
    const scope = activeScope.value
    if (!scope) throw new Error('Not signed in.')

    const item = await enqueueCompleteLesson(scope, {
      lessonId: input.lessonId,
      instructor_notes: input.instructor_notes,
      learner_summary: input.learner_summary,
      next_focus: input.next_focus,
      base_updated_at: input.base_updated_at,
      skill_ids: input.skill_ids,
      skill_ratings: input.skill_ratings,
    })
    refreshPending()

    if (online.value) {
      try {
        const done = await apiFetch<Lesson>(`/lessons/${input.lessonId}/complete`, {
          method: 'POST',
          body: {
            client_mutation_id: item.id,
            instructor_notes: input.instructor_notes,
            learner_summary: input.learner_summary,
            next_focus: input.next_focus,
            skill_ids: input.skill_ids,
            skill_ratings: input.skill_ratings,
          },
        })
        await db.deleteOutboxItem(item.id)
        refreshPending()
        try {
          await db.putLesson({
            scopeKey: scope,
            id: input.lessonId,
            cachedAt: new Date().toISOString(),
            data: done,
          })
        } catch {
          // Cache is best-effort after a successful complete.
        }
        return done
      } catch (error) {
        if (!isNetworkError(error)) {
          throw error
        }
        banner.value = 'offline'
        online.value = false
      }
    } else {
      banner.value = 'offline'
    }

    const local = await readCachedLesson(scope, input.lessonId)
    if (local) return local

    // Fallback shape if lesson wasn't cached (shouldn't happen for Today flow)
    return {
      id: input.lessonId,
      learner_id: 0,
      instructor_id: 0,
      learner_name: null,
      starts_at: '',
      starts_at_local: '',
      starts_at_display: '',
      timezone: me.value?.organisation?.timezone || 'Europe/London',
      duration_minutes: 60,
      pickup_address: null,
      status: 'completed',
      instructor_notes: input.instructor_notes ?? null,
      learner_summary: input.learner_summary ?? null,
      next_focus: input.next_focus ?? null,
      client_mutation_id: item.id,
      cancelled_at: null,
      completed_at: new Date().toISOString(),
    }
  }

  async function clearLocalForLogout(): Promise<{ blocked: boolean; pending: number }> {
    const scope = activeScope.value
    if (!scope) return { blocked: false, pending: 0 }
    if (online.value) {
      await flushOutbox(scope)
    }
    const pending = await db.countPendingOutbox(scope)
    if (pending > 0) {
      return { blocked: true, pending }
    }
    await db.clearScope(scope)
    return { blocked: false, pending: 0 }
  }

  return {
    online,
    banner,
    pendingCount,
    authBlocked,
    activeScope,
    bindConnectivity,
    loadToday,
    loadLesson,
    completeLessonLocalFirst,
    clearLocalForLogout,
    refreshPending,
    runSync,
  }
}
