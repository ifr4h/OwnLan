<script setup lang="ts">
import type { DayRhythm } from '~/utils/diary/overviewModel'
import { formatHm } from '~/utils/calendar/timeGrid'

const props = defineProps<{
  rhythm: DayRhythm
}>()

const span = computed(() => Math.max(1, props.rhythm.endMinutes - props.rhythm.startMinutes))

function leftPct(start: number): number {
  return ((start - props.rhythm.startMinutes) / span.value) * 100
}

function widthPct(start: number, end: number): number {
  return Math.max(1.2, ((end - start) / span.value) * 100)
}

const ticks = computed(() => {
  const start = Math.ceil(props.rhythm.startMinutes / 60) * 60
  const end = Math.floor(props.rhythm.endMinutes / 60) * 60
  const out: number[] = []
  for (let m = start; m <= end; m += 60) out.push(m)
  return out
})
</script>

<template>
  <div class="day-rhythm" role="img" :aria-label="`Day rhythm from ${formatHm(rhythm.startMinutes)} to ${formatHm(rhythm.endMinutes)}`">
    <div class="day-rhythm__track">
      <span
        v-for="(seg, i) in rhythm.segments"
        :key="i"
        class="day-rhythm__seg"
        :data-kind="seg.kind"
        :data-warn="'warning' in seg && seg.warning ? 'yes' : 'no'"
        :style="{
          left: `${leftPct(seg.start)}%`,
          width: `${widthPct(seg.start, seg.end)}%`,
        }"
      />
    </div>
    <div class="day-rhythm__ticks" aria-hidden="true">
      <span
        v-for="t in ticks"
        :key="t"
        class="day-rhythm__tick"
        :style="{ left: `${leftPct(t)}%` }"
      >
        {{ Math.floor(t / 60) }}
      </span>
    </div>
  </div>
</template>

<style scoped>
.day-rhythm {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 0;
}

.day-rhythm__track {
  position: relative;
  height: 28px;
  border-radius: 8px;
  background: color-mix(in srgb, var(--color-border) 55%, transparent);
  overflow: hidden;
}

.day-rhythm__seg {
  position: absolute;
  top: 4px;
  bottom: 4px;
  border-radius: 5px;
}

.day-rhythm__seg[data-kind='teach'] {
  background: var(--color-ownlane-green);
}

.day-rhythm__seg[data-kind='travel'] {
  background: color-mix(in srgb, var(--color-bark) 35%, white);
}

.day-rhythm__seg[data-kind='travel'][data-warn='yes'] {
  background: #e8c56a;
}

.day-rhythm__seg[data-kind='open'] {
  background: transparent;
  box-shadow: inset 0 0 0 1.5px dashed color-mix(in srgb, var(--color-muted) 45%, transparent);
}

.day-rhythm__ticks {
  position: relative;
  height: 14px;
  margin: 0 2px;
}

.day-rhythm__tick {
  position: absolute;
  transform: translateX(-50%);
  font-size: 10px;
  color: var(--color-muted);
  font-variant-numeric: tabular-nums;
}
</style>
