<template>
  <section class="diary ol-page ol-page--full">
    <div class="toolbar" role="toolbar" aria-label="Diary">
      <div class="toolbar__left">
        <div class="toolbar__nav">
          <button class="ol-chip" type="button" :disabled="loading" aria-label="Previous" @click="shift(-1)">
            ←
          </button>
          <p class="toolbar__label" aria-live="polite">{{ diary?.label || '…' }}</p>
          <button class="ol-chip" type="button" :disabled="loading" aria-label="Next" @click="shift(1)">
            →
          </button>
        </div>

        <button
          class="ol-chip toolbar__today"
          type="button"
          :class="{ 'ol-chip--on': isViewingToday }"
          :disabled="loading || isViewingToday"
          @click="goToday"
        >
          Today
        </button>

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

      <div class="toolbar__right">
        <template v-if="view !== 'month'">
          <button
            class="ol-chip"
            type="button"
            :class="{ 'ol-chip--on': showSidePanel && panelMode === 'welcome' }"
            :aria-pressed="showSidePanel && panelMode === 'welcome'"
            @click="toggleOverviewPanel"
          >
            Overview
          </button>

          <div class="type-filter" ref="typeFilterRoot">
            <button
              class="type-filter__trigger"
              type="button"
              :aria-expanded="typeFilterOpen"
              aria-haspopup="listbox"
              aria-controls="diary-type-filter"
              @click="typeFilterOpen = !typeFilterOpen"
            >
              <span class="type-filter__dots" aria-hidden="true">
                <i
                  v-for="dot in filterTriggerDots"
                  :key="dot"
                  class="type-filter__dot"
                  :data-tone="dot"
                />
              </span>
              <span class="type-filter__label">{{ filterTriggerLabel }}</span>
            </button>

            <div
              v-if="typeFilterOpen"
              id="diary-type-filter"
              class="type-filter__menu"
              role="listbox"
              aria-label="Filter diary by type"
            >
              <button
                v-for="opt in filterOptions"
                :key="opt.value"
                class="type-filter__option"
                type="button"
                role="option"
                :aria-selected="typeFilter === opt.value"
                @click="selectTypeFilter(opt.value)"
              >
                <i class="type-filter__swatch" :data-tone="opt.swatch" aria-hidden="true" />
                <span>{{ opt.label }}</span>
              </button>
            </div>
          </div>
        </template>

        <NuxtLink :to="bookHref" class="ol-btn ol-btn--sm toolbar__book">
          Book
        </NuxtLink>
      </div>
    </div>

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
        <span
          class="strip__dom"
          :class="{ 'strip__dom--today': d.isToday }"
        >{{ d.dom }}</span>
      </button>
    </div>

    <p v-if="error" class="ol-error" role="alert">{{ error }}</p>
    <div v-else-if="loading && !diary" class="diary__skel" aria-hidden="true">
      <div class="ol-skeleton" style="height: 420px; width: 100%" />
    </div>

    <template v-else-if="diary">
      <!-- MONTH -->
      <div
        v-if="view === 'month'"
        class="month"
      >
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
      <div v-else class="grid-wrap">
        <div v-if="!hasAnyPupils" class="ol-empty ol-empty--banner">
          <h2 class="ol-empty__title">No pupils yet</h2>
          <p class="ol-empty__copy">Add or import pupils before booking lessons.</p>
          <div class="ol-empty__actions">
            <NuxtLink to="/pupils/new" class="ol-btn ol-btn--sm">Add pupil →</NuxtLink>
          </div>
        </div>

        <div
          class="grid-wrap__main"
          :class="{ 'grid-wrap__main--panel': showSidePanel }"
        >
          <div class="grid-wrap__calendar">
            <CalendarDayGrid
              :days="gridDays"
              :bounds="bounds"
              :now-minutes="nowMinutes"
              :show-now="true"
              :compact="view === 'week'"
              :interactive="hasAnyPupils"
              :work-start-time="workStart"
              :work-end-time="workEnd"
              :work-days="workDays"
              :breaks="visibleBreaks"
              :focus-type="typeFilter"
              :select-lessons="isDesktop"
              @book="onSlotPick"
              @open-day="onOpenDay"
              @gap-open="onGapOpen"
              @break-remove="onBreakRemove"
              @select-lesson="onSelectLesson"
            />
          </div>

          <CalendarDiarySidePanel
            v-if="showSidePanel"
            :mode="panelMode"
            :lesson="selectedLesson"
            :title="pageTitle"
            :subtitle="statsSubtitle"
            :work-hours="workHoursLabel"
            @close="closePanel"
            @open-full="openLessonFull"
          />
        </div>

        <CalendarDiaryGapSheet
          :gap="selectedGap"
          :open="gapSheetOpen"
          @close="gapSheetOpen = false"
        />

        <Teleport to="body">
          <div
            v-if="slotChoice"
            class="slot-choice"
            role="dialog"
            aria-modal="true"
            aria-label="Add to diary"
          >
            <button class="slot-choice__backdrop" type="button" aria-label="Close" @click="slotChoice = null" />
            <div class="slot-choice__panel">
              <p class="slot-choice__title">Add to this slot</p>
              <p class="slot-choice__meta">
                {{ slotChoice.starts_at_local.slice(11, 16) }}
                ·
                {{ slotChoice.duration_minutes || 60 }} minutes
              </p>
              <div class="slot-choice__actions">
                <button class="ol-btn ol-btn--block" type="button" @click="confirmBookLesson">
                  Book lesson
                </button>
              </div>
              <label class="ol-field slot-choice__label">
                <span class="ol-field__label">Or add a break</span>
                <input v-model="breakLabel" class="ol-input" type="text" maxlength="40" placeholder="School run">
              </label>
              <button class="ol-btn ol-btn--ghost ol-btn--block" type="button" @click="confirmAddBreak">
                Add break
              </button>
            </div>
          </div>
        </Teleport>
      </div>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { DiaryGap, DiaryLesson, DiaryResponse } from '~/composables/useLessons'
import {
  parseHm,
  fullDayGridBounds,
  type GridBounds,
} from '~/utils/calendar/timeGrid'
import CalendarDiarySidePanel from '~/components/calendar/DiarySidePanel.vue'
import CalendarDiaryGapSheet from '~/components/calendar/DiaryGapSheet.vue'

useHead({ title: 'Diary · OwnLane' })

type DiaryView = 'day' | 'week' | 'month'
type DiarySidePanelMode = 'welcome' | 'lesson'
const VIEW_KEY = 'ownlane.diary.view'

const route = useRoute()
const router = useRouter()
const { fetchDiary } = useLessons()
const { me } = useAuth()
const { onboarding, refresh } = useOnboarding()
const { breaksForDates, addBreak, removeBreak } = useDiaryBreaks()

const diary = ref<DiaryResponse | null>(null)
const loading = ref(true)
const error = ref('')
const isDesktop = ref(true)
const gapSheetOpen = ref(false)
const selectedGap = ref<DiaryGap | null>(null)
const slotChoice = ref<{
  date: string
  starts_at_local: string
  duration_minutes: number
} | null>(null)
const breakLabel = ref('Break')

const panelOpen = ref(true)
const panelMode = ref<DiarySidePanelMode>('welcome')
const selectedLesson = ref<DiaryLesson | null>(null)
const panelDismissed = ref(false)

type DiaryFocusType = 'all' | 'paid' | 'unpaid' | 'package' | 'break' | 'offer'
const typeFilter = ref<DiaryFocusType>('all')
const typeFilterOpen = ref(false)
const typeFilterRoot = ref<HTMLElement | null>(null)

const filterOptions: Array<{ value: DiaryFocusType; label: string; swatch: string }> = [
  { value: 'all', label: 'All types', swatch: 'all' },
  { value: 'paid', label: 'Paid', swatch: 'paid' },
  { value: 'unpaid', label: 'Unpaid', swatch: 'unpaid' },
  { value: 'package', label: 'Block', swatch: 'package' },
  { value: 'break', label: 'Break', swatch: 'break' },
  { value: 'offer', label: 'Offer', swatch: 'offer' },
]

const filterTriggerDots = computed(() => {
  if (typeFilter.value === 'all') return ['paid', 'unpaid', 'package', 'break']
  if (typeFilter.value === 'package') return ['package']
  return [typeFilter.value]
})

const filterTriggerLabel = computed(() => {
  if (typeFilter.value === 'all') return 'All types'
  return filterOptions.find(o => o.value === typeFilter.value)?.label ?? 'Filter'
})

function selectTypeFilter(next: DiaryFocusType) {
  typeFilter.value = next
  typeFilterOpen.value = false
}

function onTypeFilterClickOutside(e: MouseEvent) {
  if (!typeFilterOpen.value) return
  const root = typeFilterRoot.value
  if (root && !root.contains(e.target as Node)) {
    typeFilterOpen.value = false
  }
}

function syncPanelForViewport() {
  if (!isDesktop.value || view.value === 'month') {
    panelOpen.value = false
    selectedLesson.value = null
    panelMode.value = 'welcome'
    return
  }
  // Desktop day/week: open by default until the instructor closes it this session.
  if (!panelDismissed.value) {
    panelOpen.value = true
    if (panelMode.value !== 'lesson') {
      panelMode.value = 'welcome'
      selectedLesson.value = null
    }
  }
}

function toggleOverviewPanel() {
  if (!isDesktop.value) return
  if (panelOpen.value && panelMode.value === 'welcome') {
    closePanel()
    return
  }
  panelDismissed.value = false
  selectedLesson.value = null
  panelMode.value = 'welcome'
  panelOpen.value = true
}

function onSelectLesson(lesson: DiaryLesson) {
  if (!isDesktop.value) {
    void navigateTo(`/lessons/${lesson.id}`)
    return
  }
  panelDismissed.value = false
  selectedLesson.value = lesson
  panelMode.value = 'lesson'
  panelOpen.value = true
}

function closePanel() {
  panelOpen.value = false
  panelDismissed.value = true
  selectedLesson.value = null
  panelMode.value = 'welcome'
}

function openLessonFull(id: number) {
  void navigateTo(`/lessons/${id}`)
}

const viewOptions: DiaryView[] = ['day', 'week', 'month']

const hasAnyPupils = computed(() => {
  if ((onboarding.value?.pupil_count ?? 0) > 0) return true
  // Fall back to diary payload so a stale onboarding count can't blank the grid
  return (diary.value?.lessons?.length ?? 0) > 0
    || (diary.value?.days ?? []).some(d => d.lesson_count > 0)
})

const view = computed<DiaryView>(() => {
  const q = route.query.view
  if (q === 'week' || q === 'month' || q === 'day') return q
  return 'day'
})

const showSidePanel = computed(() =>
  isDesktop.value && panelOpen.value && view.value !== 'month',
)

const date = computed(() => {
  const q = route.query.date
  if (typeof q === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(q)) return q
  return localToday()
})

const isViewingToday = computed(() => {
  const today = localToday()
  if (view.value === 'day') return date.value === today
  // Week / month: “today” means the loaded range includes today
  if (diary.value?.days?.length) {
    return diary.value.days.some(d => d.is_today || d.date === today)
  }
  return date.value === today
})

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
  // Desktop week = seven day columns in one row.
  if (view.value === 'week' && isDesktop.value) return diary.value.days
  const match = diary.value.days.find(d => d.date === date.value)
  return match ? [match] : diary.value.days.slice(0, 1)
})

const bounds = computed<GridBounds>(() => {
  // Full midnight–midnight day. Work hours only shade the outside bands.
  return fullDayGridBounds(view.value === 'day' ? 1.35 : 0.95)
})

const visibleBreaks = computed(() => {
  const dates = gridDays.value.map(d => d.date)
  return breaksForDates(dates)
})

const statsTitle = computed(() => {
  if (!diary.value) return ''
  const days = view.value === 'week'
    ? diary.value.days
    : view.value === 'month'
      ? diary.value.days.filter(d => d.in_month !== false)
      : gridDays.value
  const lessons = days.reduce((n, d) => n + d.lesson_count, 0)
  const noun = lessons === 1 ? 'lesson' : 'lessons'
  if (view.value === 'week') return `${lessons} ${noun} this week`
  if (view.value === 'month') return `${lessons} ${noun} this month`
  return `${lessons} ${noun} today`
})

const pageTitle = computed(() => statsTitle.value || 'Diary')

const workHoursLabel = computed(() => `${workStart.value}–${workEnd.value}`)

const statsSubtitle = computed(() => {
  if (!diary.value) return ''
  const days = view.value === 'week' ? diary.value.days : gridDays.value
  const sellable = days
    .map((d) => {
      const mins = (d.gaps ?? [])
        .filter(g => g.duration_minutes >= 60)
        .reduce((n, g) => n + g.duration_minutes, 0)
      return { date: d.date, minutes: mins }
    })
    .filter(d => d.minutes >= 60)
    .sort((a, b) => b.minutes - a.minutes)

  if (!sellable.length) return 'No sellable hours free in this view'

  if (view.value === 'day') {
    return `${hoursLabel(sellable[0]!.minutes)} sellable still free`
  }

  const best = sellable[0]!
  const weekday = weekdayName(best.date)
  return `${hoursLabel(best.minutes)} sellable still free on ${weekday}`
})

const nowMinutes = computed(() => {
  if (!diary.value?.now_time) return null
  return parseHm(diary.value.now_time)
})

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

function weekdayName(ymd: string): string {
  const d = parseYmd(ymd)
  if (!d) return ymd
  return d.toLocaleDateString('en-GB', { weekday: 'long' })
}

function onGapOpen(gap: DiaryGap) {
  selectedGap.value = gap
  gapSheetOpen.value = true
}

function onSlotPick(payload: { date: string; starts_at_local: string; duration_minutes?: number }) {
  slotChoice.value = {
    date: payload.date,
    starts_at_local: payload.starts_at_local,
    duration_minutes: payload.duration_minutes || 60,
  }
  breakLabel.value = 'Break'
}

function confirmBookLesson() {
  if (!slotChoice.value) return
  const q = new URLSearchParams({
    date: slotChoice.value.date,
    starts_at_local: slotChoice.value.starts_at_local,
    duration_minutes: String(slotChoice.value.duration_minutes),
  })
  slotChoice.value = null
  void navigateTo(`/lessons/new?${q.toString()}`)
}

function confirmAddBreak() {
  if (!slotChoice.value) return
  addBreak({
    date: slotChoice.value.date,
    starts_at_local: slotChoice.value.starts_at_local,
    duration_minutes: slotChoice.value.duration_minutes,
    label: breakLabel.value,
  })
  slotChoice.value = null
}

function onBreakRemove(id: string) {
  removeBreak(id)
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

/** Month cells open day view. Week day headers only move the focused date. */
async function onOpenDay(next: string) {
  if (view.value === 'week') {
    await setDate(next)
    return
  }
  await router.replace({ query: { view: 'day', date: next } })
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
  syncPanelForViewport()
}

onMounted(() => {
  syncDesktop()
  window.addEventListener('resize', syncDesktop)
  document.addEventListener('pointerdown', onTypeFilterClickOutside)

  // Restore preferred view if URL has no view (and not forced by mobile).
  if (!route.query.view && import.meta.client) {
    const saved = localStorage.getItem(VIEW_KEY) as DiaryView | null
    const preferred = saved === 'week' || saved === 'month' || saved === 'day' ? saved : null
    if (preferred) {
      // Phones default to day even if week/month was saved.
      const next = window.matchMedia('(max-width: 899px)').matches ? 'day' : preferred
      void router.replace({ query: { ...route.query, view: next, date: date.value } })
      syncPanelForViewport()
      return
    }
  }
  void load()
  syncPanelForViewport()
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', syncDesktop)
  document.removeEventListener('pointerdown', onTypeFilterClickOutside)
})

watch([view, date], () => {
  void load()
})

watch(view, () => {
  syncPanelForViewport()
})
</script>

<style scoped>
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-width: 0;
}

.toolbar__left,
.toolbar__right {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.toolbar__left {
  flex: 1 1 auto;
  flex-wrap: wrap;
}

.toolbar__right {
  flex: 0 1 auto;
  justify-content: flex-end;
  flex-wrap: wrap;
}

.toolbar__nav {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.toolbar__label {
  margin: 0;
  min-width: 0;
  max-width: 14rem;
  text-align: center;
  font-size: var(--text-body-sm);
  font-weight: 600;
  color: var(--color-ink-black);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.toolbar__today:disabled {
  opacity: 0.45;
}

.toolbar__book {
  flex-shrink: 0;
}

.week-summary {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.type-filter {
  position: relative;
  flex-shrink: 0;
}

.type-filter__trigger {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 36px;
  padding: 8px 12px 8px 8px;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  background: var(--color-paper-white);
  color: var(--color-ink-black);
  font: inherit;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}

.type-filter__trigger:hover {
  border-color: var(--color-border-strong);
}

.type-filter__dots {
  display: inline-flex;
  align-items: center;
  padding-left: 2px;
}

.type-filter__dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  border: 1.5px solid var(--color-paper-white);
  margin-left: -4px;
  box-shadow: 0 0 0 1px color-mix(in srgb, var(--color-ink-black) 8%, transparent);
}

.type-filter__dot:first-child {
  margin-left: 0;
}

.type-filter__dot[data-tone='paid'],
.type-filter__swatch[data-tone='paid'] {
  background: var(--color-diary-paid);
}

.type-filter__dot[data-tone='unpaid'],
.type-filter__swatch[data-tone='unpaid'] {
  background: var(--color-diary-unpaid);
}

.type-filter__dot[data-tone='package'],
.type-filter__swatch[data-tone='package'] {
  background: var(--color-diary-block);
}

.type-filter__dot[data-tone='break'],
.type-filter__swatch[data-tone='break'] {
  background: var(--color-diary-break);
}

.type-filter__dot[data-tone='offer'],
.type-filter__swatch[data-tone='offer'] {
  background: var(--color-diary-offer);
  box-shadow: inset 0 0 0 1px dashed var(--color-diary-offer-ink);
}

.type-filter__label {
  white-space: nowrap;
}

.type-filter__menu {
  position: absolute;
  top: calc(100% + 6px);
  left: auto;
  right: 0;
  z-index: 20;
  min-width: 168px;
  padding: 6px;
  border: 1px solid var(--color-border);
  border-radius: 12px;
  background: var(--color-paper-white);
  box-shadow: 0 12px 28px rgba(32, 21, 21, 0.12);
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.type-filter__option {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 8px 10px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: var(--color-ink-black);
  font: inherit;
  font-size: var(--text-body-sm);
  text-align: left;
  cursor: pointer;
}

.type-filter__option:hover,
.type-filter__option[aria-selected='true'] {
  background: var(--color-parchment);
}

.type-filter__swatch {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  flex-shrink: 0;
}

.type-filter__swatch[data-tone='all'] {
  background:
    conic-gradient(
      var(--color-diary-paid) 0 90deg,
      var(--color-diary-unpaid) 90deg 180deg,
      var(--color-diary-block) 180deg 270deg,
      var(--color-diary-break) 270deg 360deg
    );
}

.type-filter__swatch[data-tone='break'] {
  border-radius: 999px;
}

.slot-choice {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: grid;
  place-items: end center;
  padding: 16px;
}

.slot-choice__backdrop {
  position: absolute;
  inset: 0;
  border: none;
  background: rgba(32, 21, 21, 0.28);
  cursor: pointer;
}

.slot-choice__panel {
  position: relative;
  width: min(100%, 360px);
  margin-bottom: max(8px, env(safe-area-inset-bottom));
  padding: 18px 16px 16px;
  border-radius: 16px;
  background: var(--color-paper-white, #fffefb);
  border: 1px solid var(--color-border, #ececec);
  box-shadow: 0 12px 40px rgba(32, 21, 21, 0.14);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.slot-choice__title {
  margin: 0;
  font-size: var(--text-body);
  font-weight: 650;
}

.slot-choice__meta {
  margin: -6px 0 0;
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.slot-choice__actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.slot-choice__label {
  margin: 0;
}

@media (min-width: 720px) {
  .slot-choice {
    place-items: center;
  }

  .slot-choice__panel {
    margin-bottom: 0;
  }
}

.strip {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 0;
  padding: 4px 0 8px;
}

.strip__day {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 6px 2px;
  border: none;
  border-radius: 0;
  background: transparent;
  min-height: 48px;
}

.strip__day[data-on='yes'] .strip__dom:not(.strip__dom--today) {
  background: #f2f2f7;
}

.strip__dow {
  font-size: 11px;
  color: #8e8e93;
  text-transform: none;
  font-weight: 500;
}

.strip__dom {
  font-size: 17px;
  font-variant-numeric: tabular-nums;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--color-ink-black);
}

.strip__day[data-today='yes'] .strip__dom {
  background: var(--color-ownlane-green);
  color: white;
  font-weight: 500;
  font-size: 15px;
}

.grid-wrap {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 0;
  width: 100%;
}

.grid-wrap__main {
  display: flex;
  flex-direction: column;
  gap: 0;
  min-width: 0;
  width: 100%;
}

@media (min-width: 900px) {
  .grid-wrap__main--panel {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(280px, 34%);
    gap: 0;
    align-items: stretch;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-cards);
    overflow: hidden;
    background: var(--color-paper-white);
    height: max(420px, calc(100dvh - 11.5rem));
    min-height: 420px;
  }

  .grid-wrap__main--panel .grid-wrap__calendar {
    height: 100%;
    min-height: 0;
    border: none;
    border-radius: 0;
    border-right: 1px solid var(--color-border);
  }
}

.grid-wrap__calendar {
  min-width: 0;
  width: 100%;
  flex: 1 1 auto;
  height: max(420px, calc(100dvh - 11.5rem));
  min-height: 420px;
  background: var(--color-paper-white);
  border-radius: var(--radius-cards);
  overflow: hidden;
  border: 1px solid var(--color-border);
  display: flex;
  flex-direction: column;
}

.grid-wrap__calendar :deep(.tg) {
  flex: 1;
  min-height: 0;
}

.diary__hint {
  display: none;
}

.month {
  display: flex;
  flex-direction: column;
  gap: 8px;
  background: #ffffff;
  border: 1px solid #ececec;
  border-radius: 16px;
  padding: 12px;
}

.month__dows {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
  font-size: 11px;
  color: #8e8e93;
  text-align: center;
  text-transform: none;
  letter-spacing: 0;
  font-weight: 500;
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
  border: none;
  border-radius: 12px;
  background: #fafafa;
  text-align: left;
  transition: background-color var(--duration-fast) ease;
}

.month__cell:hover {
  background: #f2f2f7;
}

.month__cell[data-out='yes'] {
  opacity: 0.4;
}

.month__cell[data-today='yes'] {
  background: #e7f6ee;
  border: 2px solid var(--color-ownlane-green);
  padding: 7px;
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
  .toolbar {
    flex-wrap: wrap;
  }

  .toolbar__left,
  .toolbar__right {
    flex-wrap: wrap;
  }

  .toolbar__right {
    width: 100%;
    justify-content: flex-start;
  }

  .toolbar__label {
    max-width: 10rem;
  }

  .toolbar__book {
    margin-left: auto;
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
