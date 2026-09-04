<script setup lang="ts">
import type { MonthWeekBar } from '~/utils/diary/overviewModel'
import { formatOverviewHours } from '~/utils/diary/overviewModel'

const props = defineProps<{
  bars: MonthWeekBar[]
}>()

const max = computed(() => Math.max(1, ...props.bars.map(b => b.teachingMinutes)))
</script>

<template>
  <div class="month-rhythm" role="img" aria-label="Teaching hours by week">
    <div
      v-for="bar in bars"
      :key="bar.key"
      class="month-rhythm__row"
    >
      <span class="month-rhythm__label">{{ bar.label }}</span>
      <span class="month-rhythm__track">
        <span
          class="month-rhythm__fill"
          :style="{ width: `${(bar.teachingMinutes / max) * 100}%` }"
        />
      </span>
      <span class="month-rhythm__value">{{ formatOverviewHours(bar.teachingMinutes) }}</span>
    </div>
  </div>
</template>

<style scoped>
.month-rhythm {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 0;
}

.month-rhythm__row {
  display: grid;
  grid-template-columns: 1.6rem minmax(0, 1fr) auto;
  gap: 8px;
  align-items: center;
}

.month-rhythm__label {
  font-size: 11px;
  color: var(--color-muted);
  font-variant-numeric: tabular-nums;
}

.month-rhythm__track {
  height: 10px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--color-border) 55%, transparent);
  overflow: hidden;
}

.month-rhythm__fill {
  display: block;
  height: 100%;
  border-radius: 999px;
  background: color-mix(in srgb, var(--color-ownlane-green) 48%, var(--color-soft-sage));
  min-width: 0;
  transition: width 220ms ease;
}

.month-rhythm__value {
  font-size: 11px;
  color: var(--color-bark);
  font-variant-numeric: tabular-nums;
  min-width: 2.8rem;
  text-align: right;
}
</style>
