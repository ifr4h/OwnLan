<script setup lang="ts">
import type { DiaryDay, DiaryGap, DiaryLesson } from '~/composables/useLessons'
import {
  type GridBounds,
  blockHeight,
  blockTop,
  formatHm,
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
}>()

const emit = defineEmits<{
  book: [payload: { date: string; starts_at_local: string; duration_minutes?: number }]
  openDay: [date: string]
  gapOpen: [gap: DiaryGap]
}>()

type LessonTimed = DiaryLesson & { startMinutes: number; endMinutes: number; id: number }

const marks = computed(() => hourMarks(props.bounds))
const halfMarks = computed(() => halfHourMarks(props.bounds))
const heightPx = computed(() => gridHeight(props.bounds))

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
    .filter(g => g.duration_minutes >= 60)
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

const nowTop = computed(() => {
  if (props.nowMinutes == null) return null
  if (props.nowMinutes < props.bounds.startMinutes || props.nowMinutes > props.bounds.endMinutes) return null
  return blockTop(props.nowMinutes, props.bounds)
})

function gapSummary(gap: DiaryGap): string {
  const n = gap.matches.length
  if (n === 0) return `${gap.label} free`
  return n === 1 ? '1 pupil could fit' : `${n} pupils could fit`
}
</script>

<template>
  <div class="tg" :data-cols="days.length" :data-compact="compact ? 'yes' : 'no'">
    <div class="tg__rail" aria-hidden="true">
      <div class="tg__rail-spacer" />
      <div class="tg__rail-body" :style="{ height: `${heightPx}px` }">
        <div
          v-for="mark in marks"
          :key="mark"
          class="tg__hour-label"
          :style="{ top: `${blockTop(mark, bounds)}px` }"
        >
          {{ formatHm(mark) }}
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
      <header class="tg__day-head">
        <button
          v-if="days.length > 1"
          class="tg__day-btn"
          type="button"
          @click="emit('openDay', day.date)"
        >
          <span class="tg__dow">{{ day.weekday.slice(0, 3).toUpperCase() }}</span>
          <span class="tg__dom" :data-today="day.is_today ? 'yes' : 'no'">
            {{ day.date.slice(8) }}
          </span>
        </button>
        <div v-else class="tg__day-static">
          <span class="tg__dow">{{ day.weekday }}</span>
          <span class="tg__dom" :data-today="day.is_today ? 'yes' : 'no'">
            {{ day.date_display }}
          </span>
        </div>
      </header>

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

        <div
          v-for="item in gapMarkers(day)"
          :key="`gap-${item.gap.previous_lesson_id}-${item.gap.next_lesson_id}`"
          class="tg__gap"
          :style="{ top: `${item.top}px`, height: `${item.height}px` }"
        >
          <p class="tg__gap-time">
            {{ item.gap.starts_at_display }}–{{ item.gap.ends_at_display }}
          </p>
          <p class="tg__gap-label">{{ gapSummary(item.gap) }}</p>
          <button
            v-if="item.gap.matches.length"
            class="tg__gap-cta"
            type="button"
            @pointerdown.stop
            @click="emit('gapOpen', item.gap)"
          >
            View
          </button>
        </div>

        <div
          v-for="item in travelMarkers(day)"
          :key="item.key"
          class="tg__travel"
          :data-severity="item.warning.severity"
          :data-warning="item.warning.is_warning ? 'yes' : 'no'"
          :style="{ top: `${item.top}px`, height: `${Math.max(item.height, 14)}px` }"
          :title="item.warning.message"
        >
          <span class="tg__travel-label">Travel</span>
          <span v-if="item.warning.travel_minutes != null">
            {{ item.warning.travel_minutes }} min
          </span>
        </div>

        <CalendarLessonBlock
          v-for="block in lessonsForDay(day)"
          :key="block.id"
          :lesson="block"
          :compact="compact || days.length > 1"
          :block-height="block.height"
          :style-inline="{
            top: `${block.top}px`,
            height: `${block.height}px`,
            left: `calc(${block.leftPct}% + 2px)`,
            width: `calc(${block.widthPct}% - 4px)`,
            position: 'absolute',
            zIndex: '3',
          }"
        />

        <div
          v-if="drag && drag.date === day.date && dragStyle()"
          class="tg__drag"
          :style="dragStyle()!"
        />

        <div
          v-if="showNow && day.is_today && nowTop != null"
          class="tg__now"
          :style="{ top: `${nowTop}px` }"
          aria-hidden="true"
        >
          <span class="tg__now-dot" />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tg {
  display: grid;
  grid-template-columns: 56px repeat(var(--tg-cols, 1), minmax(0, 1fr));
  gap: 0;
  min-width: 0;
  background: var(--surface-card);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-panel);
  overflow: hidden;
}

.tg[data-cols='1'] {
  --tg-cols: 1;
}

.tg[data-cols='7'] {
  --tg-cols: 7;
  grid-template-columns: 52px repeat(7, minmax(104px, 1fr));
  overflow-x: auto;
}

.tg__rail {
  display: flex;
  flex-direction: column;
  border-right: 1px solid var(--color-border);
  background: #f5f8f6;
  position: sticky;
  left: 0;
  z-index: 5;
}

.tg__rail-spacer {
  height: 56px;
  border-bottom: 1px solid var(--color-border);
  flex-shrink: 0;
  background: #fbfdfc;
}

.tg__rail-body {
  position: relative;
}

.tg__hour-label {
  position: absolute;
  left: 0;
  right: 6px;
  transform: translateY(-50%);
  font-size: 11px;
  line-height: 1;
  text-align: right;
  color: var(--color-muted);
  font-variant-numeric: tabular-nums;
  font-weight: 500;
}

.tg__day {
  min-width: 0;
  border-right: 1px solid var(--color-border);
}

.tg__day:last-child {
  border-right: none;
}

.tg__day[data-today='yes'] .tg__canvas {
  background: rgba(22, 139, 85, 0.03);
}

.tg__day[data-off='yes'] .tg__canvas {
  background: #f6f6f7;
}

.tg__day-head {
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-bottom: 1px solid var(--color-border);
  background: #fbfdfc;
  position: sticky;
  top: 0;
  z-index: 4;
}

.tg__day[data-today='yes'] .tg__day-head {
  background: var(--color-success-wash);
}

.tg__day-btn,
.tg__day-static {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  border: none;
  background: transparent;
  padding: 4px 10px;
  border-radius: 10px;
  cursor: pointer;
}

.tg__day-btn:hover {
  background: rgba(22, 139, 85, 0.08);
}

.tg__dow {
  font-size: 10px;
  color: var(--color-muted);
  letter-spacing: 0.06em;
  font-weight: 600;
}

.tg__dom {
  font-size: 17px;
  font-variant-numeric: tabular-nums;
  font-weight: 500;
  line-height: 1;
}

.tg__dom[data-today='yes'] {
  width: 30px;
  height: 30px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: var(--color-ownlane-green);
  color: white;
  font-size: 14px;
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
  background: repeating-linear-gradient(
    -45deg,
    rgba(17, 17, 24, 0.02),
    rgba(17, 17, 24, 0.02) 8px,
    transparent 8px,
    transparent 16px
  );
  pointer-events: none;
  z-index: 0;
}

.tg__outside {
  position: absolute;
  left: 0;
  right: 0;
  background: repeating-linear-gradient(
    -45deg,
    rgba(17, 17, 24, 0.025),
    rgba(17, 17, 24, 0.025) 6px,
    transparent 6px,
    transparent 12px
  );
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
  border-top: 1px solid #d8e4dc;
  z-index: 1;
}

.tg__hline--half {
  border-top: 1px dashed #ecf1ed;
  z-index: 1;
}

.tg__gap {
  position: absolute;
  left: 3px;
  right: 3px;
  z-index: 2;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: center;
  gap: 2px;
  padding: 4px 6px;
  border-radius: 6px;
  border: 1px dashed rgba(22, 139, 85, 0.35);
  background: rgba(243, 248, 244, 0.72);
  overflow: hidden;
}

.tg__gap-time {
  margin: 0;
  font-size: 10px;
  color: var(--color-muted);
  font-variant-numeric: tabular-nums;
}

.tg__gap-label {
  margin: 0;
  font-size: 11px;
  font-weight: 500;
  color: var(--color-ownlane-green);
  line-height: 1.2;
}

.tg__gap-cta {
  margin-top: 2px;
  padding: 2px 8px;
  font-size: 11px;
  font-weight: 500;
  color: var(--color-ownlane-green);
  background: white;
  border: 1px solid rgba(22, 139, 85, 0.35);
  border-radius: 6px;
  cursor: pointer;
}

.tg__gap-cta:hover {
  background: var(--color-success-wash);
}

.tg__travel {
  position: absolute;
  left: 8px;
  right: 8px;
  z-index: 2;
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 0 4px;
  font-size: 9px;
  line-height: 1.2;
  border-radius: 3px;
  background: rgba(255, 255, 255, 0.65);
  color: var(--color-muted);
  pointer-events: none;
  overflow: hidden;
  white-space: nowrap;
  border-left: 2px solid #d0d8d3;
}

.tg__travel[data-warning='yes'] {
  background: var(--color-warning-wash);
  color: var(--color-warning);
  border-left-color: var(--color-warning);
  font-weight: 500;
}

.tg__travel[data-severity='impossible'] {
  background: var(--color-danger-wash);
  color: var(--color-danger);
  border-left-color: var(--color-danger);
}

.tg__travel-label {
  text-transform: uppercase;
  letter-spacing: 0.04em;
  font-size: 8px;
}

.tg__drag {
  position: absolute;
  left: 4px;
  right: 4px;
  z-index: 5;
  border-radius: 6px;
  border: 1.5px dashed var(--color-ownlane-green);
  background: rgba(22, 139, 85, 0.12);
  pointer-events: none;
}

.tg__now {
  position: absolute;
  left: 0;
  right: 0;
  z-index: 6;
  height: 0;
  border-top: 2px solid var(--color-marker-red);
  pointer-events: none;
}

.tg__now-dot {
  position: absolute;
  left: -5px;
  top: -6px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--color-marker-red);
  box-shadow: 0 0 0 2px white;
}
</style>
