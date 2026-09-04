<script setup lang="ts">
import type { WeekDayBar } from '~/utils/diary/overviewModel'
import { formatOverviewHours } from '~/utils/diary/overviewModel'

const props = defineProps<{
  bars: WeekDayBar[]
}>()

const max = computed(() => Math.max(1, ...props.bars.map(b => b.teachingMinutes)))

const emit = defineEmits<{
  selectDay: [date: string]
}>()

const tip = ref<string | null>(null)

function showTip(bar: WeekDayBar) {
  tip.value = `${bar.lessonCount} lesson${bar.lessonCount === 1 ? '' : 's'} · ${formatOverviewHours(bar.teachingMinutes)}`
}

function hideTip() {
  tip.value = null
}

function onSelect(bar: WeekDayBar) {
  emit('selectDay', bar.date)
}
</script>

<template>
  <div class="week-rhythm">
    <div class="week-rhythm__bars" role="img" aria-label="Teaching hours by day">
      <button
        v-for="bar in bars"
        :key="bar.date"
        type="button"
        class="week-rhythm__col"
        :data-today="bar.isToday ? 'yes' : 'no'"
        :data-busy="bar.isBusiest ? 'yes' : 'no'"
        :aria-label="`${bar.date}: ${formatOverviewHours(bar.teachingMinutes)}`"
        @mouseenter="showTip(bar)"
        @mouseleave="hideTip"
        @focus="showTip(bar)"
        @blur="hideTip"
        @click="onSelect(bar)"
      >
        <span class="week-rhythm__shaft">
          <span
            class="week-rhythm__fill"
            :style="{ height: `${Math.max(bar.teachingMinutes > 0 ? 8 : 0, (bar.teachingMinutes / max) * 100)}%` }"
          />
        </span>
        <span class="week-rhythm__label">{{ bar.label }}</span>
      </button>
    </div>
    <p v-if="tip" class="week-rhythm__tip">{{ tip }}</p>
  </div>
</template>

<style scoped>
.week-rhythm {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.week-rhythm__bars {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 6px;
  align-items: end;
  height: 88px;
}

.week-rhythm__col {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  height: 100%;
  padding: 0;
  border: none;
  background: transparent;
  cursor: pointer;
  min-width: 0;
}

.week-rhythm__shaft {
  flex: 1;
  width: 100%;
  max-width: 22px;
  display: flex;
  align-items: flex-end;
  border-radius: 6px;
  background: color-mix(in srgb, var(--color-border) 50%, transparent);
  overflow: hidden;
}

.week-rhythm__fill {
  width: 100%;
  border-radius: 6px 6px 0 0;
  background: color-mix(in srgb, var(--color-ownlane-green) 72%, white);
  transition: height 220ms ease;
}

.week-rhythm__col[data-busy='yes'] .week-rhythm__fill {
  background: var(--color-ownlane-green);
}

.week-rhythm__col[data-today='yes'] .week-rhythm__label {
  color: var(--color-ownlane-green);
  font-weight: 600;
}

.week-rhythm__label {
  font-size: 11px;
  color: var(--color-muted);
  line-height: 1;
}

.week-rhythm__tip {
  margin: 0;
  min-height: 1.2em;
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}
</style>
