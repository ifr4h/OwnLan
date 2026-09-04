import type { DiaryResponse } from '~/composables/useLessons'
import type { NeedsAttentionItem } from '~/composables/useToday'
import {
  addDaysYmd,
  buildDiaryOverview,
  localTodayYmd,
  priorPeriodAnchor,
  type DiaryOverviewModel,
} from '~/utils/diary/overviewModel'

function dayTeachingMinutes(diary: DiaryResponse): number {
  const lessons = (diary.lessons ?? []).filter(
    l => l.status === 'scheduled' || l.status === 'completed',
  )
  if (lessons.length) {
    return lessons.reduce((n, l) => n + (l.duration_minutes || 0), 0)
  }
  return (diary.days ?? []).reduce((n, d) => {
    if (typeof d.teaching_minutes === 'number') return n + d.teaching_minutes
    const dayLessons = (d.lessons ?? []).filter(
      l => l.status === 'scheduled' || l.status === 'completed',
    )
    return n + dayLessons.reduce((m, l) => m + (l.duration_minutes || 0), 0)
  }, 0)
}

/**
 * Adaptive diary overview: current diary + prior period + (day) usual weekday samples
 * + (week/month) continuity from /today.
 */
export function useDiaryOverview(
  diary: Ref<DiaryResponse | null>,
) {
  const { fetchDiary } = useLessons()
  const { fetchToday } = useToday()

  const prior = ref<DiaryResponse | null>(null)
  const usualWeekdayMinutes = ref<number[]>([])
  const continuityPupils = ref<Array<{
    learner_id: number
    learner_name: string
    usual_cadence: string | null
  }>>([])
  const extrasLoading = ref(false)
  const loadKey = ref('')

  const overview = computed<DiaryOverviewModel | null>(() => {
    if (!diary.value) return null
    try {
      return buildDiaryOverview(diary.value, prior.value, localTodayYmd(), {
        usualWeekdayMinutes: usualWeekdayMinutes.value,
        continuityPupils: continuityPupils.value,
      })
    } catch {
      return null
    }
  })

  async function loadExtras(current: DiaryResponse) {
    const key = `${current.view}:${current.range_start}:${current.range_end}:${current.date}`
    if (loadKey.value === key) return
    loadKey.value = key
    const runKey = key
    extrasLoading.value = true

    prior.value = null
    usualWeekdayMinutes.value = []
    if (current.view === 'day') continuityPupils.value = []

    try {
      const jobs: Promise<void>[] = []

      jobs.push(
        fetchDiary(current.view, priorPeriodAnchor(current.view, current.date))
          .then((d) => {
            if (loadKey.value === runKey) prior.value = d
          })
          .catch(() => {
            if (loadKey.value === runKey) prior.value = null
          }),
      )

      if (current.view === 'day') {
        // Four recent same weekdays for "usual Tuesday" (falls back to last week if fewer).
        const anchors = [1, 2, 3, 4, 5, 6].map(w => addDaysYmd(current.date, -7 * w))
        jobs.push(
          Promise.all(anchors.map(a => fetchDiary('day', a).catch(() => null)))
            .then((results) => {
              if (loadKey.value !== runKey) return
              usualWeekdayMinutes.value = results
                .filter((d): d is DiaryResponse => !!d)
                .map(dayTeachingMinutes)
            }),
        )
      }

      if (current.view === 'week' || current.view === 'month') {
        jobs.push(
          fetchToday()
            .then((today) => {
              if (loadKey.value !== runKey) return
              const items = (today.needs_attention ?? []) as NeedsAttentionItem[]
              continuityPupils.value = items
                .filter(i =>
                  i.usual_cadence
                  || i.kind === 'pattern_overdue'
                  || i.kind === 'no_future_booking',
                )
                .slice(0, 6)
                .map(i => ({
                  learner_id: i.learner_id,
                  learner_name: i.learner_name,
                  usual_cadence: i.usual_cadence,
                }))
            })
            .catch(() => {
              if (loadKey.value === runKey) continuityPupils.value = []
            }),
        )
      }

      await Promise.all(jobs)
    } finally {
      if (loadKey.value === runKey) extrasLoading.value = false
    }
  }

  watch(
    () => diary.value && `${diary.value.view}:${diary.value.range_start}:${diary.value.range_end}:${diary.value.date}`,
    () => {
      if (!diary.value) {
        prior.value = null
        usualWeekdayMinutes.value = []
        continuityPupils.value = []
        loadKey.value = ''
        return
      }
      void loadExtras(diary.value)
    },
    { immediate: true },
  )

  return {
    overview,
    priorLoading: extrasLoading,
  }
}
