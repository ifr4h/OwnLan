<script setup lang="ts">
import type { DiaryDay, DiaryGap, DiaryLesson } from '~/composables/useLessons'
import type { DiaryBreak } from '~/composables/useDiaryBreaks'
import {
  type GridBounds,
  blockHeight,
  blockTop,
  formatDuration,
  formatHourLabel,
  gridHeight,
  halfHourMarks,
  hourMarks,
  layoutOverlaps,
  localDateTime,
  parseHm,
  yToMinutes,
} from '~/utils/calendar/timeGrid'

const props = defineProps<{
  days: DiaryDay[]
  bounds: GridBounds
  nowMinutes: number | null
  showNow: boolean
  compact?: boolean
  interactive?: boolean
  workStartTime?: string
  workEndTime?: string
  workDays?: number[]
  breaks?: DiaryBreak[]
  focusType?: 'all' | 'paid' | 'unpaid' | 'package' | 'break' | 'offer'
  selectLessons?: boolean
}>()

const emit = defineEmits<{
  book: [payload: { date: string; starts_at_local: string; duration_minutes?: number }]
  openDay: [date: string]
  gapOpen: [gap: DiaryGap]
  breakRemove: [id: string]
  selectLesson: [lesson: DiaryLesson]
}>()

type LessonTimed = DiaryLesson & { startMinutes: number; endMinutes: number; id: number }

const marks = computed(() => hourMarks(props.bounds))
const halfMarks = computed(() => halfHourMarks(props.bounds))
const heightPx = computed(() => gridHeight(props.bounds))
const rootEl = ref<HTMLElement | null>(null)
const scrollerEl = ref<HTMLElement | null>(null)
const didCenter = ref(false)
const clockTick = ref(0)

let clockTimer: ReturnType<typeof setInterval> | null = null

/** Prefer live clock when today is visible so the red line stays accurate. */
const effectiveNowMinutes = computed(() => {
  void clockTick.value
  const viewingToday = props.days.some(d => d.is_today)
  if (props.showNow && viewingToday && import.meta.client) {
    const n = new Date()
    return n.getHours() * 60 + n.getMinutes()
  }
  return props.nowMinutes
})

function lessonsForDay(day: DiaryDay): ReturnType<typeof layoutOverlaps<LessonTimed>> {
  const timed: LessonTimed[] = day.lessons.map((lesson) => {
    const startMinutes = parseHm(lesson.starts_at_time || lesson.starts_at_local?.slice(11, 16))
    const endMinutes = lesson.ends_at_time
      ? parseHm(lesson.ends_at_time)
      : startMinutes + (lesson.duration_minutes || 60)
    return {
      ...lesson,
      id: lesson.id,
      startMinutes,
      endMinutes: Math.max(endMinutes, startMinutes + 1),
    }
  })
  return layoutOverlaps(timed, props.bounds)
}

function travelMarkers(day: DiaryDay) {
  const out: Array<{
    key: string
    top: number
    height: number
    warning: NonNullable<DiaryLesson['travel_to_next']>
  }> = []
  for (const lesson of day.lessons) {
    const leg = lesson.travel_to_next
    if (!leg || !lesson.ends_at_time || leg.travel_minutes == null) continue
    const start = parseHm(lesson.ends_at_time)
    const travelMins = Math.min(leg.travel_minutes, Math.max(leg.available_minutes ?? leg.travel_minutes, 8))
    const end = start + travelMins
    out.push({
      key: `travel-${lesson.id}`,
      top: blockTop(start, props.bounds),
      height: blockHeight(start, end, props.bounds),
      warning: leg,
    })
  }
  return out
}

function gapMarkers(day: DiaryDay) {
  return (day.gaps ?? [])
    .filter(g => g.duration_minutes >= 45)
    .map((gap: DiaryGap) => {
      const start = parseHm(gap.starts_at_display)
      const end = parseHm(gap.ends_at_display)
      return {
        gap,
        top: blockTop(start, props.bounds),
        height: blockHeight(start, end, props.bounds),
      }
    })
}

function breaksForDay(date: string) {
  return (props.breaks ?? [])
    .filter(b => b.date === date)
    .map(b => ({
      break: b,
      top: blockTop(b.start_minutes, props.bounds),
      height: blockHeight(b.start_minutes, b.end_minutes, props.bounds),
    }))
}

function isWorkingDay(dateYmd: string): boolean {
  const days = props.workDays
  if (!days?.length) return true
  const d = new Date(`${dateYmd}T12:00:00`)
  const iso = ((d.getDay() + 6) % 7) + 1
  return days.includes(iso)
}

const outsideBands = computed(() => {
  const start = parseHm(props.workStartTime || '08:00')
  const end = parseHm(props.workEndTime || '18:00')
  const bands: Array<{ top: number; height: number }> = []
  if (start > props.bounds.startMinutes) {
    bands.push({
      top: blockTop(props.bounds.startMinutes, props.bounds),
      height: blockHeight(props.bounds.startMinutes, start, props.bounds),
    })
  }
  if (end < props.bounds.endMinutes) {
    bands.push({
      top: blockTop(end, props.bounds),
      height: blockHeight(end, props.bounds.endMinutes, props.bounds),
    })
  }
  return bands
})

const drag = ref<{
  date: string
  startMin: number
  endMin: number
  gridEl: HTMLElement
} | null>(null)

function onPointerDown(e: PointerEvent, date: string, el: HTMLElement) {
  if (!props.interactive) return
  if ((e.target as HTMLElement).closest('a,button')) return
  const rect = el.getBoundingClientRect()
  const startMin = yToMinutes(e.clientY, rect.top, props.bounds, 15)
  drag.value = { date, startMin, endMin: startMin + 60, gridEl: el }
  el.setPointerCapture(e.pointerId)
}

function onPointerMove(e: PointerEvent) {
  if (!drag.value) return
  const rect = drag.value.gridEl.getBoundingClientRect()
  const mins = yToMinutes(e.clientY, rect.top, props.bounds, 15)
  drag.value = {
    ...drag.value,
    endMin: Math.max(drag.value.startMin + 15, mins),
  }
}

function onPointerUp(e: PointerEvent) {
  if (!drag.value) return
  const { date, startMin, endMin, gridEl } = drag.value
  gridEl.releasePointerCapture(e.pointerId)
  const duration = Math.max(15, endMin - startMin)
  const rect = gridEl.getBoundingClientRect()
  const release = yToMinutes(e.clientY, rect.top, props.bounds, 15)
  const isTap = Math.abs(release - startMin) < 10
  emit('book', {
    date,
    starts_at_local: localDateTime(date, startMin),
    duration_minutes: isTap ? 60 : duration,
  })
  drag.value = null
}

function onPointerCancel() {
  drag.value = null
}

function dragStyle() {
  if (!drag.value) return null
  const start = Math.min(drag.value.startMin, drag.value.endMin)
  const end = Math.max(drag.value.startMin, drag.value.endMin + (drag.value.endMin === drag.value.startMin ? 60 : 0))
  return {
    top: `${blockTop(start, props.bounds)}px`,
    height: `${blockHeight(start, end, props.bounds)}px`,
  }
}

function dragDurationLabel(): string {
  if (!drag.value) return ''
  const start = Math.min(drag.value.startMin, drag.value.endMin)
  const end = Math.max(drag.value.startMin, drag.value.endMin + (drag.value.endMin === drag.value.startMin ? 60 : 0))
  return formatDuration(Math.max(15, end - start))
}

const nowTop = computed(() => {
  const mins = effectiveNowMinutes.value
  if (mins == null) return null
  if (mins < props.bounds.startMinutes || mins > props.bounds.endMinutes) return null
  return blockTop(mins, props.bounds)
})

const nowTimeLabel = computed(() => {
  const mins = effectiveNowMinutes.value
  if (mins == null) return null
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
})

const showNowIndicator = computed(() =>
  props.showNow
  && props.days.some(d => d.is_today)
  && nowTop.value != null
  && nowTimeLabel.value != null,
)

function hourLabelHidden(mark: number): boolean {
  if (!showNowIndicator.value || effectiveNowMinutes.value == null) return false
  // Keep the rail readable when the red now-label sits near an hour tick.
  return Math.abs(mark - effectiveNowMinutes.value) < 18
}

function gapSummary(gap: DiaryGap): string {
  return `${formatDuration(gap.duration_minutes)} free`
}

function gapOfferLabel(gap: DiaryGap): string {
  const n = gap.matches?.length ?? 0
  if (n === 0) return 'Offer it'
  return n === 1 ? '1 pupil' : `${n} pupils`
}

function lessonFocusKind(lesson: DiaryLesson): 'paid' | 'unpaid' | 'package' | 'other' {
  if (lesson.status === 'cancelled' || lesson.status === 'no_show') return 'other'
  const s = lesson.settlement
  if (s === 'outstanding') return 'unpaid'
  if (s === 'package') return 'package'
  // Paid + unsettled scheduled share the lime tone
  return 'paid'
}

function isDimmed(kind: 'paid' | 'unpaid' | 'package' | 'break' | 'offer' | 'other'): boolean {
  const focus = props.focusType ?? 'all'
  if (focus === 'all') return false
  return kind !== focus
}

function centerTargetTop(): number {
  if (nowTop.value != null && props.days.some(d => d.is_today)) {
    return nowTop.value
  }
  return blockTop(parseHm(props.workStartTime || '08:00'), props.bounds)
}

function centerOnCurrentHour(force = false) {
  const scroller = scrollerEl.value
  if (!scroller) return
  if (didCenter.value && !force) return

  const viewport = scroller.clientHeight || 1
  const top = centerTargetTop()
  const next = Math.max(0, Math.min(
    scroller.scrollHeight - viewport,
    top - viewport / 2,
  ))
  scroller.scrollTop = next
  didCenter.value = true
}

onMounted(() => {
  clockTimer = setInterval(() => {
    clockTick.value += 1
  }, 30_000)
  nextTick(() => {
    requestAnimationFrame(() => centerOnCurrentHour(true))
  })
})

onBeforeUnmount(() => {
  if (clockTimer) clearInterval(clockTimer)
})

watch(
  () => props.days.map(d => d.date).join(','),
  () => {
    didCenter.value = false
    nextTick(() => requestAnimationFrame(() => centerOnCurrentHour(true)))
  },
)

watch(
  () => [props.bounds.pxPerMinute, props.bounds.endMinutes, heightPx.value],
  () => {
    if (!didCenter.value) {
      nextTick(() => requestAnimationFrame(() => centerOnCurrentHour(true)))
    }
  },
)
</script>

<template>
  <div
    ref="rootEl"
    class="tg"
    :data-cols="days.length"
    :data-compact="compact ? 'yes' : 'no'"
    :style="{ '--tg-cols': Math.max(days.length, 1) }"
  >
    <div v-if="days.length > 1" class="tg__heads">
      <div class="tg__heads-spacer" aria-hidden="true" />
      <div
        v-for="day in days"
        :key="`head-${day.date}`"
        class="tg__day-head"
        :data-today="day.is_today ? 'yes' : 'no'"
      >
        <button
          class="tg__day-btn"
          type="button"
          @click="emit('openDay', day.date)"
        >
          <span class="tg__dow">{{ day.weekday.slice(0, 3) }}</span>
          <span class="tg__dom" :data-today="day.is_today ? 'yes' : 'no'">
            {{ Number(day.date.slice(8)) }}
          </span>
        </button>
      </div>
    </div>

    <div ref="scrollerEl" class="tg__scroller">
      <div class="tg__board" :data-cols="days.length">
        <div class="tg__rail" aria-hidden="true">
          <div class="tg__rail-body" :style="{ height: `${heightPx}px` }">
            <div
              v-for="mark in marks"
              :key="mark"
              class="tg__hour-label"
              :class="{ 'tg__hour-label--hidden': hourLabelHidden(mark) }"
              :style="{ top: `${blockTop(mark, bounds)}px` }"
            >
              {{ formatHourLabel(mark) }}
            </div>
            <div
              v-if="showNowIndicator"
              class="tg__now-label"
              :style="{ top: `${nowTop}px` }"
            >
              {{ nowTimeLabel }}
            </div>
          </div>
        </div>

        <div
          v-for="day in days"
          :key="day.date"
          class="tg__day"
          :data-today="day.is_today ? 'yes' : 'no'"
          :data-off="!isWorkingDay(day.date) ? 'yes' : 'no'"
        >
          <div
            class="tg__canvas"
            :style="{ height: `${heightPx}px` }"
            @pointerdown="onPointerDown($event, day.date, $event.currentTarget as HTMLElement)"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="onPointerCancel"
          >
            <div
              v-if="!isWorkingDay(day.date)"
              class="tg__off-day"
              aria-hidden="true"
            />

            <div
              v-for="(band, bi) in outsideBands"
              :key="`out-${day.date}-${bi}`"
              class="tg__outside"
              :style="{ top: `${band.top}px`, height: `${band.height}px` }"
            />

            <div
              v-for="mark in halfMarks"
              :key="`half-${day.date}-${mark}`"
              class="tg__hline tg__hline--half"
              :style="{ top: `${blockTop(mark, bounds)}px` }"
            />

            <div
              v-for="mark in marks"
              :key="`line-${day.date}-${mark}`"
              class="tg__hline tg__hline--hour"
              :style="{ top: `${blockTop(mark, bounds)}px` }"
            />

            <button
              v-for="item in gapMarkers(day)"
              :key="`gap-${item.gap.previous_lesson_id}-${item.gap.next_lesson_id}`"
              class="tg__offer"
              :class="{ 'tg__dimmed': isDimmed('offer') }"
              type="button"
              :style="{ top: `${item.top}px`, height: `${item.height}px` }"
              :title="`${gapSummary(item.gap)} · ${item.gap.starts_at_display}–${item.gap.ends_at_display}`"
              :tabindex="isDimmed('offer') ? -1 : undefined"
              @pointerdown.stop
              @click="emit('gapOpen', item.gap)"
            >
              <span class="tg__offer-time">{{ gapSummary(item.gap) }}</span>
              <span v-if="item.height >= 44" class="tg__offer-cta">{{ gapOfferLabel(item.gap) }}</span>
            </button>

            <button
              v-for="item in breaksForDay(day.date)"
              :key="item.break.id"
              class="tg__break"
              :class="{ 'tg__dimmed': isDimmed('break') }"
              type="button"
              :style="{ top: `${item.top}px`, height: `${Math.max(item.height, 22)}px` }"
              :title="`${item.break.label}. Click to remove.`"
              :tabindex="isDimmed('break') ? -1 : undefined"
              @pointerdown.stop
              @click="emit('breakRemove', item.break.id)"
            >
              <span class="tg__break-label">{{ item.break.label }}</span>
            </button>

            <div
              v-for="item in travelMarkers(day)"
              :key="item.key"
              class="tg__travel"
              :class="{ 'tg__dimmed': (focusType ?? 'all') !== 'all' }"
              :data-severity="item.warning.severity"
              :data-warning="item.warning.is_warning ? 'yes' : 'no'"
              :style="{ top: `${item.top}px`, height: `${Math.max(item.height, 10)}px` }"
              :title="item.warning.message"
            >
              <span v-if="item.warning.travel_minutes != null">
                {{ item.warning.travel_minutes }}m
              </span>
            </div>

            <CalendarLessonBlock
              v-for="block in lessonsForDay(day)"
              :key="block.id"
              :lesson="block"
              :compact="compact || days.length > 1"
              :block-height="block.height"
              :dimmed="isDimmed(lessonFocusKind(block))"
              :select-mode="selectLessons"
              :style-inline="{
                top: `${block.top}px`,
                height: `${block.height}px`,
                left: `calc(${block.leftPct}% + 3px)`,
                width: `calc(${block.widthPct}% - 6px)`,
                position: 'absolute',
                zIndex: '3',
              }"
              @select="emit('selectLesson', $event)"
            />

            <div
              v-if="drag && drag.date === day.date && dragStyle()"
              class="tg__drag"
              :style="dragStyle()!"
            >
              <span class="tg__drag-title">New slot</span>
              <span class="tg__drag-duration">
                {{ dragDurationLabel() }}
              </span>
              <span class="tg__drag-handle" aria-hidden="true" />
            </div>

            <div
              v-if="showNowIndicator && day.is_today"
              class="tg__now"
              :style="{ top: `${nowTop}px` }"
              aria-hidden="true"
            >
              <span class="tg__now-dot" />
            </div>
            <div
              v-else-if="showNowIndicator"
              class="tg__now tg__now--muted"
              :style="{ top: `${nowTop}px` }"
              aria-hidden="true"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tg {
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
  height: 100%;
  background: var(--color-paper-white);
}

.tg__heads {
  display: grid;
  grid-template-columns: 44px repeat(var(--tg-cols, 7), minmax(0, 1fr));
  flex-shrink: 0;
  border-bottom: 1px solid var(--color-border);
  background: var(--color-paper-white);
  z-index: 4;
}

.tg[data-cols='7'] .tg__heads,
.tg[data-cols='7'] .tg__board {
  grid-template-columns: 44px repeat(7, minmax(0, 1fr));
  min-width: 0;
}

.tg__heads-spacer {
  min-width: 0;
}

.tg__scroller {
  flex: 1;
  min-height: 0;
  overflow: auto;
  -webkit-overflow-scrolling: touch;
  overscroll-behavior: contain;
}

.tg__board {
  display: grid;
  grid-template-columns: 44px repeat(var(--tg-cols, 7), minmax(0, 1fr));
  gap: 0;
  min-width: 0;
  width: 100%;
}

.tg[data-cols='1'] .tg__heads,
.tg[data-cols='1'] .tg__board {
  grid-template-columns: 44px minmax(0, 1fr);
  min-width: 0;
}

.tg__rail {
  display: flex;
  flex-direction: column;
  border-right: none;
  background: var(--color-paper-white);
  position: sticky;
  left: 0;
  z-index: 5;
}

.tg__rail-body {
  position: relative;
}

.tg__hour-label {
  position: absolute;
  left: 0;
  right: 8px;
  transform: translateY(-50%);
  font-size: 11px;
  line-height: 1;
  text-align: right;
  color: var(--color-muted);
  font-variant-numeric: tabular-nums;
  font-weight: 400;
  letter-spacing: -0.01em;
}

.tg__hour-label--hidden {
  opacity: 0;
}

.tg__now-label {
  position: absolute;
  left: 0;
  right: 6px;
  transform: translateY(-50%);
  font-size: 10px;
  line-height: 1;
  text-align: right;
  color: #ff3b30;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
  letter-spacing: -0.02em;
  z-index: 2;
}

.tg__day {
  min-width: 0;
  border-right: 1px solid var(--color-border);
}

.tg__day:last-child {
  border-right: none;
}

.tg[data-cols='7'] .tg__day[data-today='yes'] {
  background: color-mix(in srgb, var(--color-ownlane-green) 6%, var(--color-paper-white));
}

.tg[data-cols='7'] .tg__day[data-today='yes'] .tg__canvas {
  background: transparent;
}

.tg[data-cols='7'] .tg__day-head[data-today='yes'] {
  background: color-mix(in srgb, var(--color-ownlane-green) 6%, var(--color-paper-white));
}

.tg[data-cols='7'] .tg__dom[data-today='yes'] {
  box-shadow: none;
}

.tg__day[data-off='yes'] .tg__canvas {
  background: var(--surface-wash);
}

.tg__day-head {
  height: 52px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
}

.tg__day-btn,
.tg__day-static {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  border: none;
  background: transparent;
  padding: 2px 8px;
  border-radius: 0;
  cursor: pointer;
}

.tg__day-btn:hover .tg__dom:not([data-today='yes']) {
  background: var(--surface-wash);
}

.tg__dow {
  font-size: 11px;
  color: var(--color-muted);
  letter-spacing: 0.01em;
  font-weight: 500;
  text-transform: none;
}

.tg__dom {
  font-size: 20px;
  font-variant-numeric: tabular-nums;
  font-weight: 400;
  line-height: 1;
  width: 32px;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  color: var(--color-ink-black);
}

.tg__dom[data-today='yes'] {
  background: var(--color-ownlane-green);
  color: #ffffff;
  font-weight: 500;
}

.tg__canvas {
  position: relative;
  background: transparent;
  cursor: crosshair;
  touch-action: none;
}

.tg__off-day {
  position: absolute;
  inset: 0;
  background: #f7f7f8;
  pointer-events: none;
  z-index: 0;
}

.tg__outside {
  position: absolute;
  left: 0;
  right: 0;
  background: #fafafa;
  pointer-events: none;
  z-index: 0;
}

.tg[data-compact='yes'] .tg__canvas {
  cursor: pointer;
}

.tg__hline {
  position: absolute;
  left: 0;
  right: 0;
  pointer-events: none;
}

.tg__hline--hour {
  border-top: 1px solid #e5e5ea;
  z-index: 1;
}

.tg__hline--half {
  border-top: 1px solid #f2f2f7;
  z-index: 1;
}

.tg__offer {
  position: absolute;
  left: 4px;
  right: 4px;
  z-index: 2;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 6px 8px;
  border: 1.5px dashed color-mix(in srgb, var(--color-diary-offer-ink) 45%, white);
  border-radius: 12px;
  background: color-mix(in srgb, var(--color-diary-offer) 70%, transparent);
  overflow: hidden;
  cursor: pointer;
  text-align: center;
  appearance: none;
  font: inherit;
  color: var(--color-diary-offer-ink);
}

.tg__offer:hover {
  background: var(--color-diary-offer);
  border-color: var(--color-diary-offer-ink);
}

.tg__offer-time {
  font-size: 13px;
  font-weight: 600;
  line-height: 1.2;
}

.tg__offer-cta {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 22px;
  padding: 2px 10px;
  border-radius: 999px;
  background: var(--color-diary-offer-cta);
  color: var(--color-diary-unpaid-ink);
  font-size: 11px;
  font-weight: 600;
  line-height: 1;
}

.tg__break {
  position: absolute;
  left: 4px;
  right: 4px;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px 10px;
  border: none;
  border-radius: 999px;
  background:
    repeating-linear-gradient(
      -45deg,
      var(--color-diary-break),
      var(--color-diary-break) 6px,
      color-mix(in srgb, var(--color-diary-break) 55%, white) 6px,
      color-mix(in srgb, var(--color-diary-break) 55%, white) 12px
    );
  color: var(--color-diary-break-ink);
  cursor: pointer;
  appearance: none;
  font: inherit;
  overflow: hidden;
}

.tg__break:hover {
  filter: brightness(0.97);
}

.tg__break-label {
  font-size: 12px;
  font-weight: 500;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.tg__dimmed {
  opacity: 0.05;
  pointer-events: none;
}

.tg__travel {
  position: absolute;
  left: 10px;
  right: 10px;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  font-size: 10px;
  line-height: 1;
  border-radius: 0;
  background: transparent;
  color: var(--color-muted);
  pointer-events: none;
  overflow: hidden;
  white-space: nowrap;
  border-top: 1px dotted var(--color-driftwood);
}

.tg__travel[data-warning='yes'] {
  color: #8a6d00;
  border-top-color: #e6c35c;
  font-weight: 500;
}

.tg__travel[data-severity='impossible'] {
  color: #c62828;
  border-top-color: #e57373;
}

.tg__drag {
  position: absolute;
  left: 4px;
  right: 4px;
  z-index: 5;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 10px;
  border: none;
  background: #1a5c45;
  color: #ffffff;
  pointer-events: none;
  box-shadow: 0 8px 24px rgba(17, 17, 24, 0.12);
}

.tg__drag-title {
  font-size: 13px;
  font-weight: 500;
  line-height: 1.2;
}

.tg__drag-duration {
  font-size: 12px;
  font-variant-numeric: tabular-nums;
  opacity: 0.85;
}

.tg__drag-handle {
  position: absolute;
  right: 10px;
  bottom: -6px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: #ffffff;
  border: 2px solid #1a5c45;
  box-shadow: 0 1px 3px rgba(17, 17, 24, 0.2);
}

.tg__now {
  position: absolute;
  left: 0;
  right: 0;
  z-index: 6;
  height: 0;
  border-top: 2px solid #ff3b30;
  pointer-events: none;
}

.tg__now--muted {
  border-top-width: 1px;
  opacity: 0.35;
}

.tg__now-dot {
  position: absolute;
  left: -4px;
  top: -5px;
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: #ff3b30;
}
</style>
