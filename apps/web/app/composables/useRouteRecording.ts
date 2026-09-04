import { apiFetch } from '~/composables/useApi'
import { formatTimer } from '~/utils/portalFormat'

export type RoutePoint = {
  lat: number
  lng: number
  accuracy?: number
  recorded_at?: string
}

export type InstructorRoute = {
  id: number
  lesson_id: number
  status: 'recording' | 'completed' | 'discarded' | string
  started_at: string | null
  ended_at: string | null
  duration_seconds: number | null
  duration_label: string | null
  distance_metres: number | null
  distance_label: string | null
  point_count: number
  learner_visible: boolean
  shared_at: string | null
  has_geometry: boolean
}

export type RouteMomentMark = {
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

const SAMPLE_MS = 7000
const MAX_ACCURACY_M = 80

/**
 * Explicit instructor route recording — start/stop only, thinned GPS samples.
 */
export function useRouteRecording(lessonId: Ref<number> | number) {
  const id = computed(() => (typeof lessonId === 'number' ? lessonId : lessonId.value))

  const route = ref<InstructorRoute | null>(null)
  const points = ref<RoutePoint[]>([])
  const moments = ref<RouteMomentMark[]>([])
  const recording = computed(() => route.value?.status === 'recording')
  const elapsedSeconds = ref(0)
  const error = ref('')
  const busy = ref(false)
  const markingMoment = ref(false)

  let watchId: number | null = null
  let sampleTimer: ReturnType<typeof setInterval> | null = null
  let tickTimer: ReturnType<typeof setInterval> | null = null
  let lastAcceptedAt = 0
  let pending: GeolocationPosition | null = null

  const timerLabel = computed(() => formatTimer(elapsedSeconds.value))

  async function refresh() {
    try {
      const res = await apiFetch<{ route: InstructorRoute | null }>(`/lessons/${id.value}/route`)
      route.value = res.route
      if (route.value?.status === 'recording') {
        if (route.value.started_at) {
          const started = Date.parse(route.value.started_at.replace(' ', 'T') + 'Z')
          if (!Number.isNaN(started)) {
            elapsedSeconds.value = Math.max(0, Math.floor((Date.now() - started) / 1000))
          }
        }
        if (watchId == null) {
          startWatch()
        }
      }
    } catch {
      route.value = null
    }
  }

  function onPosition(pos: GeolocationPosition) {
    pending = pos
  }

  function acceptPending() {
    if (!pending) return
    const { latitude, longitude, accuracy } = pending.coords
    if (typeof accuracy === 'number' && accuracy > MAX_ACCURACY_M) return
    const now = Date.now()
    if (now - lastAcceptedAt < SAMPLE_MS - 500) return
    lastAcceptedAt = now
    points.value.push({
      lat: latitude,
      lng: longitude,
      accuracy: typeof accuracy === 'number' ? accuracy : undefined,
      recorded_at: new Date().toISOString(),
    })
  }

  function startWatch() {
    if (!import.meta.client || !('geolocation' in navigator)) {
      error.value = 'geo_unavailable'
      return
    }
    points.value = []
    lastAcceptedAt = 0
    pending = null
    watchId = navigator.geolocation.watchPosition(
      onPosition,
      (err) => {
        if (err.code === err.PERMISSION_DENIED) error.value = 'geo_denied'
        else error.value = 'geo_unavailable'
      },
      { enableHighAccuracy: true, maximumAge: 4000, timeout: 15000 },
    )
    sampleTimer = setInterval(acceptPending, SAMPLE_MS)
    tickTimer = setInterval(() => {
      elapsedSeconds.value += 1
    }, 1000)
  }

  function stopWatch() {
    if (watchId != null && import.meta.client) {
      navigator.geolocation.clearWatch(watchId)
      watchId = null
    }
    if (sampleTimer) {
      clearInterval(sampleTimer)
      sampleTimer = null
    }
    if (tickTimer) {
      clearInterval(tickTimer)
      tickTimer = null
    }
    pending = null
  }

  async function start() {
    busy.value = true
    error.value = ''
    try {
      route.value = await apiFetch<InstructorRoute>(`/lessons/${id.value}/route/start`, {
        method: 'POST',
      })
      elapsedSeconds.value = 0
      startWatch()
    } catch (e) {
      error.value = extractApiError(e, 'Could not start recording.')
      throw e
    } finally {
      busy.value = false
    }
  }

  async function stop() {
    busy.value = true
    error.value = ''
    acceptPending()
    stopWatch()
    try {
      if (points.value.length < 2) {
        error.value = 'need_points'
        // Resume watch so instructor can continue.
        startWatch()
        return null
      }
      route.value = await apiFetch<InstructorRoute>(`/lessons/${id.value}/route/stop`, {
        method: 'POST',
        body: { points: points.value },
      })
      points.value = []
      return route.value
    } catch (e) {
      error.value = extractApiError(e, 'Could not stop recording.')
      throw e
    } finally {
      busy.value = false
    }
  }

  async function share() {
    busy.value = true
    error.value = ''
    try {
      route.value = await apiFetch<InstructorRoute>(`/lessons/${id.value}/route/share`, {
        method: 'POST',
      })
      return route.value
    } catch (e) {
      error.value = extractApiError(e, 'Could not share route.')
      throw e
    } finally {
      busy.value = false
    }
  }

  async function discard() {
    busy.value = true
    error.value = ''
    stopWatch()
    try {
      await apiFetch(`/lessons/${id.value}/route/discard`, { method: 'POST' })
      route.value = null
      points.value = []
      moments.value = []
      elapsedSeconds.value = 0
    } catch (e) {
      error.value = extractApiError(e, 'Could not discard route.')
      throw e
    } finally {
      busy.value = false
    }
  }

  async function refreshMoments() {
    try {
      const res = await apiFetch<{ items: RouteMomentMark[] }>(`/lessons/${id.value}/moments`)
      moments.value = res.items ?? []
    } catch {
      moments.value = []
    }
  }

  /**
   * One-tap mark using current GPS (pending watch or fresh getCurrentPosition).
   */
  async function markMoment(): Promise<RouteMomentMark | null> {
    markingMoment.value = true
    error.value = ''
    try {
      let lat: number | null = null
      let lng: number | null = null
      if (pending) {
        lat = pending.coords.latitude
        lng = pending.coords.longitude
      } else if (import.meta.client && 'geolocation' in navigator) {
        const pos = await new Promise<GeolocationPosition>((resolve, reject) => {
          navigator.geolocation.getCurrentPosition(resolve, reject, {
            enableHighAccuracy: true,
            maximumAge: 5000,
            timeout: 10000,
          })
        })
        lat = pos.coords.latitude
        lng = pos.coords.longitude
      }
      if (lat == null || lng == null) {
        error.value = 'geo_unavailable'
        return null
      }
      const moment = await apiFetch<RouteMomentMark>(`/lessons/${id.value}/moments`, {
        method: 'POST',
        body: { lat, lng, recorded_at: new Date().toISOString().slice(0, 19).replace('T', ' ') },
      })
      moments.value = [...moments.value, moment]
      return moment
    } catch (e) {
      error.value = extractApiError(e, 'Could not mark moment.')
      throw e
    } finally {
      markingMoment.value = false
    }
  }

  onBeforeUnmount(() => {
    stopWatch()
  })

  return {
    route,
    points,
    moments,
    recording,
    elapsedSeconds,
    timerLabel,
    error,
    busy,
    markingMoment,
    refresh,
    refreshMoments,
    start,
    stop,
    share,
    discard,
    markMoment,
  }
}
