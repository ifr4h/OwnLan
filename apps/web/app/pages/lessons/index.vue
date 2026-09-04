<template>
  <section class="diary ol-page ol-page--wide">
    <header class="diary__header">
      <div>
        <p class="ol-eyebrow">Diary</p>
        <h1 class="ol-page-title">Diary</h1>
      </div>
      <NuxtLink :to="bookHref" class="ol-btn ol-btn--sm diary__book">
        Book lesson
        <span aria-hidden="true">→</span>
      </NuxtLink>
    </header>

    <div class="toolbar" role="toolbar" aria-label="Diary navigation">
      <div class="ol-seg toolbar__nav">
        <button class="ol-chip" type="button" :disabled="loading" aria-label="Previous" @click="shift(-1)">
          ←
        </button>
        <button
          class="ol-chip"
          type="button"
          :class="{ 'ol-chip--on': isViewingToday }"
          :disabled="loading || isViewingToday"
          @click="goToday"
        >
          Today
        </button>
        <button class="ol-chip" type="button" :disabled="loading" aria-label="Next" @click="shift(1)">
          →
        </button>
      </div>

      <p class="toolbar__label">{{ diary?.label }}</p>

      <div class="ol-seg" role="group" aria-label="View">
        <button
          v-for="opt in viewOptions"
          :key="opt"
          class="ol-chip"
          type="button"
          :class="{ 'ol-chip--on': view === opt }"
          @click="setView(opt)"
        >
          {{ opt[0]!.toUpperCase() + opt.slice(1) }}
        </button>
      </div>
    </div>

    <p v-if="weekSummary && view === 'week'" class="week-summary" role="status">
      {{ weekSummary }}
    </p>

    <!-- Mobile date strip (day navigation) -->
    <div v-if="view !== 'month' && weekStrip.length && !isDesktop" class="strip" aria-label="Days this week">
      <button
        v-for="d in weekStrip"
        :key="d.date"
        class="strip__day"
        type="button"
        :data-on="d.date === date ? 'yes' : 'no'"
        :data-today="d.isToday ? 'yes' : 'no'"
        @click="setDate(d.date)"
      >
        <span class="strip__dow">{{ d.dow }}</span>
        <span class="strip__dom">{{ d.dom }}</span>
      </button>
    </div>

    <div
      v-if="diary && (diary.overlap_count > 0 || diary.travel_warning_count > 0)"
      class="alerts"
    >
      <p v-if="diary.overlap_count > 0" class="alert alert--danger">
        <span class="ol-badge ol-badge--danger">Overlap</span>
        {{ diary.overlap_count }} overlapping
        {{ diary.overlap_count === 1 ? 'lesson' : 'lessons' }} — shown side-by-side on the grid.
      </p>
      <p v-if="diary.travel_warning_count > 0" class="alert alert--warn">
        <span class="ol-badge ol-badge--warning">Travel</span>
        {{ diary.travel_warning_count }}
        {{ diary.travel_warning_count === 1 ? 'tight gap' : 'tight gaps' }} marked between lessons.
      </p>
    </div>

    <p v-if="error" class="ol-error" role="alert">{{ error }}</p>
    <div v-else-if="loading && !diary" class="diary__skel" aria-hidden="true">
      <div class="ol-skeleton" style="height: 420px; width: 100%" />
    </div>

    <template v-else-if="diary">
      <!-- MONTH -->
      <div v-if="view === 'month'" class="month">
        <div class="month__dows" aria-hidden="true">
          <span v-for="d in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']" :key="d">{{ d }}</span>
        </div>
        <div class="month__grid">
          <button
            v-for="day in diary.days"
            :key="day.date"
            class="month__cell"
            type="button"
            :data-today="day.is_today ? 'yes' : 'no'"
            :data-out="day.in_month === false ? 'yes' : 'no'"
            @click="openDay(day.date)"
          >
            <span class="month__num">{{ day.date_display }}</span>
            <span v-if="day.lesson_count" class="month__count">
              {{ day.lesson_count }}
              <template v-if="(day.teaching_minutes ?? 0) > 0">
                · {{ hoursLabel(day.teaching_minutes!) }}
              </template>
            </span>
            <span class="month__dots" aria-hidden="true">
              <i
                v-for="m in (day.markers ?? []).slice(0, 4)"
                :key="m.id"
                class="month__dot"
                :data-status="m.status"
                :data-overlap="m.overlaps ? 'yes' : 'no'"
              />
            </span>
            <span v-if="day.has_test" class="month__tag">Test</span>
            <span v-else-if="day.has_overlap" class="month__tag month__tag--warn">Overlap</span>
            <span v-else-if="day.has_cancellation" class="month__tag month__tag--muted">Cancel</span>
          </button>
        </div>
      </div>

      <!-- DAY / WEEK TIME GRID -->
      <div v-else class="grid-wrap" :class="{ 'grid-wrap--day': view === 'day' }">
        <div v-if="!hasAnyPupils" class="ol-empty">
          <h2 class="ol-empty__title">No pupils yet</h2>
          <p class="ol-empty__copy">Add or import pupils before booking lessons.</p>
          <div class="ol-empty__actions">
            <NuxtLink to="/pupils/new" class="ol-btn ol-btn--sm">Add pupil →</NuxtLink>
          </div>
        </div>

        <template v-else>
          <div class="grid-wrap__calendar">
            <CalendarDayGrid
              :days="gridDays"
              :bounds="bounds"
              :now-minutes="nowMinutes"
              :show-now="true"
              :compact="view === 'week'"
              :interactive="true"
              :work-start-time="workStart"
              :work-end-time="workEnd"
              :work-days="workDays"
              @book="onBookSlot"
              @open-day="openDay"
              @gap-open="onGapOpen"
            />

            <p class="ol-meta diary__hint">
              <template v-if="isDesktop">
                Click an empty time to book · drag to set duration
              </template>
              <template v-else>
                Tap an empty time to book a lesson
              </template>
            </p>
          </div>

          <DiaryDayContext
            v-if="view === 'day' && isDesktop && gridDays[0]"
            :lessons="gridDays[0].lessons"
            :now-minutes="nowMinutes"
          />
        </template>

        <DiaryGapSheet
          :gap="selectedGap"
          :open="gapSheetOpen"
          @close="gapSheetOpen = false"
        />

        <!-- Day detail: gap matches list (desktop supplement) -->
        <section
          v-if="view === 'day' && activeGaps.length && isDesktop"
          class="gap-panel"
          aria-label="Open gaps"
        >
          <h2 class="ol-section-title">Open gaps</h2>
          <ul class="gap-list">
            <li
              v-for="gap in activeGaps"
              :key="`${gap.previous_lesson_id}-${gap.next_lesson_id}`"
              class="ol-panel gap-item"
            >
              <div class="gap-item__header">
                <p class="gap-item__title">{{ gap.label }}</p>
                <p class="ol-meta">
                  {{ gap.starts_at_display }}–{{ gap.ends_at_display }}
                </p>
                <p v-if="gap.matches.length" class="gap-item__summary">
                  {{ gap.matches.length }}
                  {{ gap.matches.length === 1 ? 'pupil could fit here' : 'pupils could fit here' }}
                </p>
                <p v-else class="gap-item__summary gap-item__summary--quiet">
                  No pupils match this gap yet
                </p>
              </div>
              <ul v-if="gap.matches.length" class="gap-matches">
                <li
                  v-for="match in gap.matches"
                  :key="`${gap.previous_lesson_id}-${match.learner_id}`"
                  class="gap-match"
                >
                  <div class="gap-match__main">
                    <p class="gap-match__name">{{ match.learner_name }}</p>
                    <p class="gap-match__reasons">{{ match.reasons.join(' · ') }}</p>
                  </div>
                  <NuxtLink class="ol-btn ol-btn--sm" :to="bookMatchHref(match)">
                    Book
                  </NuxtLink>
                </li>
              </ul>
            </li>
          </ul>
        </section>
      </div>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { DiaryGap, DiaryResponse, GapMatch } from '~/composables/useLessons'
import {
  parseHm,
  resolveGridBounds,
  type GridBounds,
} from '~/utils/calendar/timeGrid'

useHead({ title: 'Diary · OwnLane' })

type DiaryView = 'day' | 'week' | 'month'
const VIEW_KEY = 'ownlane.diary.view'

const route = useRoute()
const router = useRouter()
const { fetchDiary } = useLessons()
const { me } = useAuth()
const { onboarding, refresh } = useOnboarding()

const diary = ref<DiaryResponse | null>(null)
const loading = ref(true)
const error = ref('')
const isDesktop = ref(true)
const gapSheetOpen = ref(false)
const selectedGap = ref<DiaryGap | null>(null)

const viewOptions: DiaryView[] = ['day', 'week', 'month']

const hasAnyPupils = computed(() => (onboarding.value?.pupil_count ?? 0) > 0)

const view = computed<DiaryView>(() => {
  const q = route.query.view
  if (q === 'week' || q === 'month' || q === 'day') return q
  return 'day'
})

const date = computed(() => {
  const q = route.query.date
  if (typeof q === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(q)) return q
  return localToday()
})

const isViewingToday = computed(() => date.value === localToday())

const bookHref = computed(() => `/lessons/new?date=${date.value}`)

const workStart = computed(() =>
  diary.value?.work_start_time
  || me.value?.organisation?.work_start_time
  || '08:00',
)
const workEnd = computed(() =>
  diary.value?.work_end_time
  || me.value?.organisation?.work_end_time
  || '18:00',
)

const workDays = computed(() =>
  diary.value?.work_days
  || me.value?.organisation?.work_days
  || [],
)

const gridDays = computed(() => {
  if (!diary.value) return []
  if (view.value === 'week' && isDesktop.value) return diary.value.days
  const match = diary.value.days.find(d => d.date === date.value)
  return match ? [match] : diary.value.days.slice(0, 1)
})

const bounds = computed<GridBounds>(() => {
  const lessons = gridDays.value.flatMap(d => d.lessons)
  const b = resolveGridBounds({
    workStart: workStart.value,
    workEnd: workEnd.value,
    lessonStarts: lessons.map(l => l.starts_at_time),
    lessonEnds: lessons.map(l => l.ends_at_time),
    padMinutes: 30,
  })
  // Day view gets taller hours for readability; week stays dense but proportional.
  b.pxPerMinute = view.value === 'day' ? 2 : 1.15
  return b
})

const weekSummary = computed(() => {
  if (!diary.value || view.value !== 'week') return ''
  const days = diary.value.days
  const lessons = days.reduce((n, d) => n + d.lesson_count, 0)
  const minutes = days.reduce((n, d) => n + (d.teaching_minutes ?? 0), 0)
  const gaps = days.flatMap(d => d.gaps ?? []).filter(g => g.duration_minutes >= 60)
  const gapMinutes = gaps.reduce((n, g) => n + g.duration_minutes, 0)
  const parts: string[] = []
  parts.push(`${lessons} ${lessons === 1 ? 'lesson' : 'lessons'}`)
  if (minutes > 0) parts.push(hoursLabel(minutes) + ' teaching')
  if (gapMinutes >= 60) parts.push(hoursLabel(gapMinutes) + ' sellable capacity')
  return parts.join(' · ')
})

const nowMinutes = computed(() => {
  if (!diary.value?.now_time) return null
  return parseHm(diary.value.now_time)
})

const activeGaps = computed(() =>
  (diary.value?.days[0]?.gaps ?? []).filter(g => g.duration_minutes >= 60),
)

const weekStrip = computed(() => {
  // Build Mon–Sun around current date for mobile strip.
  const anchor = parseYmd(date.value)
  if (!anchor) return []
  const dow = ((anchor.getDay() + 6) % 7) // Mon=0
  const monday = new Date(anchor)
  monday.setDate(anchor.getDate() - dow)
  const today = localToday()
  return Array.from({ length: 7 }, (_, i) => {
    const d = new Date(monday)
    d.setDate(monday.getDate() + i)
    const ymd = formatYmd(d)
    return {
      date: ymd,
      dow: d.toLocaleDateString('en-GB', { weekday: 'short' }),
      dom: String(d.getDate()),
      isToday: ymd === today,
    }
  })
})

function hoursLabel(minutes: number): string {
  const h = minutes / 60
  return Number.isInteger(h) ? `${h}h` : `${h.toFixed(1)}h`
}

function onGapOpen(gap: DiaryGap) {
  selectedGap.value = gap
  gapSheetOpen.value = true
}

function bookMatchHref(match: GapMatch): string {
  const q = new URLSearchParams({
    learner_id: String(match.learner_id),
    starts_at_local: match.suggested_starts_at_local,
    duration_minutes: String(match.suggested_duration_minutes),
  })
  return `/lessons/new?${q.toString()}`
}

function onBookSlot(payload: { date: string; starts_at_local: string; duration_minutes?: number }) {
  const q = new URLSearchParams({
    date: payload.date,
    starts_at_local: payload.starts_at_local,
  })
  if (payload.duration_minutes) q.set('duration_minutes', String(payload.duration_minutes))
  void navigateTo(`/lessons/new?${q.toString()}`)
}

function localToday(): string {
  const n = new Date()
  return formatYmd(n)
}

function formatYmd(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function parseYmd(ymd: string): Date | null {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(ymd)
  if (!m) return null
  return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]))
}

function addDays(ymd: string, days: number): string {
  const d = parseYmd(ymd)
  if (!d) return ymd
  d.setDate(d.getDate() + days)
  return formatYmd(d)
}

function addMonths(ymd: string, months: number): string {
  const d = parseYmd(ymd)
  if (!d) return ymd
  d.setMonth(d.getMonth() + months)
  return formatYmd(d)
}

async function setView(next: DiaryView) {
  if (import.meta.client) localStorage.setItem(VIEW_KEY, next)
  // Mobile: week view becomes day-first navigation, not a 7-column grid.
  const effective = !isDesktop.value && next === 'week' ? 'day' : next
  await router.replace({ query: { ...route.query, view: effective, date: date.value } })
}

async function setDate(next: string) {
  await router.replace({ query: { ...route.query, date: next, view: view.value } })
}

async function openDay(next: string) {
  await router.replace({ query: { view: 'day', date: next } })
}

async function goToday() {
  await router.replace({ query: { ...route.query, date: localToday(), view: view.value } })
}

async function shift(dir: number) {
  let next = date.value
  if (view.value === 'day') next = addDays(date.value, dir)
  else if (view.value === 'week') next = addDays(date.value, dir * 7)
  else next = addMonths(date.value, dir)
  await setDate(next)
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [result] = await Promise.all([
      fetchDiary(view.value, date.value),
      refresh(),
    ])
    diary.value = result
  } catch (e) {
    error.value = extractApiError(e, 'Could not load diary.')
  } finally {
    loading.value = false
  }
}

function syncDesktop() {
  const desktop = window.matchMedia('(min-width: 900px)').matches
  isDesktop.value = desktop
  if (!desktop && view.value === 'week') {
    void router.replace({ query: { ...route.query, view: 'day', date: date.value } })
  }
}

onMounted(() => {
  syncDesktop()
  window.addEventListener('resize', syncDesktop)

  // Restore preferred view if URL has no view (and not forced by mobile).
  if (!route.query.view && import.meta.client) {
    const saved = localStorage.getItem(VIEW_KEY) as DiaryView | null
    const preferred = saved === 'week' || saved === 'month' || saved === 'day' ? saved : null
    if (preferred) {
      // Phones default to day even if week/month was saved.
      const next = window.matchMedia('(max-width: 899px)').matches ? 'day' : preferred
      void router.replace({ query: { ...route.query, view: next, date: date.value } })
      return
    }
  }
  void load()
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', syncDesktop)
})

watch([view, date], () => {
  void load()
})
</script>

<style scoped>
.diary__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.toolbar {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 10px;
}

.toolbar__label {
  margin: 0;
  text-align: center;
  font-size: var(--text-body-sm);
  font-weight: 500;
  color: var(--color-ink-black);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.week-summary {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.strip {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}

.strip__day {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: 8px 4px;
  border: 1px solid transparent;
  border-radius: 12px;
  background: var(--surface-card);
  min-height: 52px;
}

.strip__day[data-on='yes'] {
  background: var(--color-success-wash);
  border-color: rgba(22, 139, 85, 0.35);
}

.strip__dow {
  font-size: 10px;
  color: var(--color-muted);
  text-transform: uppercase;
}

.strip__dom {
  font-size: 15px;
  font-variant-numeric: tabular-nums;
}

.strip__day[data-today='yes'] .strip__dom {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--color-ownlane-green);
  color: white;
  font-size: 13px;
}

.alerts {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.alert {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin: 0;
  padding: 8px 12px;
  border-radius: 10px;
  font-size: var(--text-meta);
}

.alert--danger {
  background: var(--color-danger-wash);
}

.alert--warn {
  background: var(--color-warning-wash);
}

.grid-wrap {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 0;
}

.grid-wrap--day {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 16px;
}

.grid-wrap__calendar {
  min-width: 0;
}

@media (min-width: 1100px) {
  .grid-wrap--day {
    grid-template-columns: minmax(0, 1fr) 280px;
    align-items: start;
  }
}

.diary__hint {
  text-align: center;
}

.gap-panel {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.gap-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.gap-item {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 12px;
}

.gap-item__header {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.gap-item__title {
  font-size: var(--text-body-sm);
  font-weight: 600;
}

.gap-item__summary {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-ownlane-green);
  font-weight: 500;
}

.gap-item__summary--quiet {
  color: var(--color-muted);
  font-weight: 400;
}

.gap-matches {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
  border-top: 1px solid var(--color-border);
  padding-top: 10px;
}

.gap-match {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.gap-match__main {
  min-width: 0;
}

.gap-match__name {
  margin: 0;
  font-size: var(--text-body-sm);
  font-weight: 500;
}

.gap-match__reasons {
  margin: 2px 0 0;
  font-size: var(--text-caption);
  color: var(--color-muted);
}

.month {
  display: flex;
  flex-direction: column;
  gap: 8px;
  background: var(--surface-card);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-panel);
  padding: 12px;
}

.month__dows {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
  font-size: 11px;
  color: var(--color-muted);
  text-align: center;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.month__grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}

.month__cell {
  min-height: 84px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  padding: 8px;
  border: 1px solid var(--color-border);
  border-radius: 10px;
  background: #fbfdfc;
  text-align: left;
  transition: background-color var(--duration-fast) ease, border-color var(--duration-fast) ease;
}

.month__cell:hover {
  border-color: #c5dccf;
  background: white;
}

.month__cell[data-out='yes'] {
  opacity: 0.45;
}

.month__cell[data-today='yes'] {
  border-color: var(--color-ownlane-green);
  background: var(--color-success-wash);
}

.month__num {
  font-size: 13px;
  font-variant-numeric: tabular-nums;
}

.month__count {
  font-size: 11px;
  color: var(--color-muted);
}

.month__dots {
  display: flex;
  flex-wrap: wrap;
  gap: 3px;
  margin-top: auto;
}

.month__dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--color-ownlane-green);
}

.month__dot[data-status='cancelled'] {
  background: #b0b0b5;
}

.month__dot[data-status='completed'] {
  background: #7a9a86;
}

.month__dot[data-overlap='yes'] {
  background: var(--color-danger);
}

.month__tag {
  font-size: 10px;
  color: var(--color-ownlane-green);
}

.month__tag--warn {
  color: var(--color-danger);
}

.month__tag--muted {
  color: var(--color-muted);
}

@media (max-width: 899px) {
  .diary__book {
    display: none;
  }

  .toolbar {
    grid-template-columns: 1fr;
  }

  .toolbar__label {
    order: -1;
  }

  .month__cell {
    min-height: 68px;
    padding: 6px;
  }
}

@media (min-width: 900px) {
  .strip {
    display: none;
  }
}
</style>
