import type { LessonWeather, TodayLesson } from '~/composables/useToday'
import type { WeatherSnapshot } from '~/utils/weather/openMeteo'

/**
 * Fills lesson weather via the same-origin Nuxt proxy when the API
 * did not attach weather (outbound PHP fetch failed, etc.).
 */
export function useLessonWeather() {
  const byLessonId = ref<Record<number, WeatherSnapshot>>({})
  const loading = ref(false)

  async function fillMissing(opts: {
    date: string
    timezone: string
    lessons: TodayLesson[]
  }) {
    if (!import.meta.client) return
    const missing = opts.lessons.filter(l => !l.weather && l.status !== 'cancelled')
    if (!missing.length) {
      byLessonId.value = {}
      return
    }

    loading.value = true
    try {
      const address = missing.map(l => l.pickup_address).find(a => a?.trim()) || ''
      const starts = missing
        .map(l => (l.starts_at_local || l.starts_at || '').replace(' ', 'T').slice(0, 16))
        .filter(Boolean)

      const url = new URL('/weather/day', window.location.origin)
      url.searchParams.set('date', opts.date)
      url.searchParams.set('timezone', opts.timezone || 'Europe/London')
      if (address) url.searchParams.set('address', address)
      if (starts.length) url.searchParams.set('starts', starts.join(','))

      const res = await fetch(url.toString())
      if (!res.ok) {
        byLessonId.value = {}
        return
      }
      const data = await res.json() as { ok?: boolean; by_start?: Record<string, WeatherSnapshot> }
      const byStart = data.by_start || {}
      const next: Record<number, WeatherSnapshot> = {}
      for (const lesson of missing) {
        const key = (lesson.starts_at_local || lesson.starts_at || '').replace(' ', 'T').slice(0, 16)
        const hourKey = key.slice(0, 13) + ':00'
        const snap = byStart[key] || byStart[hourKey]
        if (snap) next[lesson.id] = snap
      }
      byLessonId.value = next
    } catch {
      byLessonId.value = {}
    } finally {
      loading.value = false
    }
  }

  function forLesson(lesson: TodayLesson): LessonWeather | WeatherSnapshot | null {
    return lesson.weather || byLessonId.value[lesson.id] || null
  }

  return { byLessonId, loading, fillMissing, forLesson }
}
