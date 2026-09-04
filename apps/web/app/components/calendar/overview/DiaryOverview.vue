<script setup lang="ts">
import type { DiaryOverviewModel } from '~/utils/diary/overviewModel'
import DayRhythm from '~/components/calendar/overview/DayRhythm.vue'
import WeekRhythm from '~/components/calendar/overview/WeekRhythm.vue'
import MonthRhythm from '~/components/calendar/overview/MonthRhythm.vue'
import PeriodComparisonBar from '~/components/calendar/overview/PeriodComparison.vue'
import OverviewObservationBlock from '~/components/calendar/overview/OverviewObservation.vue'
import { formatOverviewHours } from '~/utils/diary/overviewModel'

defineProps<{
  model: DiaryOverviewModel
}>()

const emit = defineEmits<{
  selectDay: [date: string]
}>()
</script>

<template>
  <div class="ov" :data-mode="model.mode">
    <header class="ov__hero">
      <p class="ov__period">{{ model.periodLabel }}</p>
      <p class="ov__headline">
        <span class="ov__headline-main">{{ formatOverviewHours(model.teachingMinutes) }}</span>
        <span class="ov__headline-sub">teaching</span>
      </p>
      <p class="ov__subhead">
        {{ model.lessonCount }} {{ model.lessonCount === 1 ? 'lesson' : 'lessons' }}
        <template v-if="model.mode !== 'day' && model.pupilCount">
          · {{ model.pupilCount }} {{ model.pupilCount === 1 ? 'pupil' : 'pupils' }}
        </template>
        <template v-if="model.mode === 'month'">
          · {{ model.teachingDays }} teaching day{{ model.teachingDays === 1 ? '' : 's' }}
        </template>
      </p>
    </header>

    <DayRhythm v-if="model.dayRhythm" :rhythm="model.dayRhythm" class="ov__rhythm" />
    <WeekRhythm
      v-else-if="model.weekBars.length"
      :bars="model.weekBars"
      class="ov__rhythm"
      @select-day="emit('selectDay', $event)"
    />
    <MonthRhythm v-else-if="model.monthBars.length" :bars="model.monthBars" class="ov__rhythm" />

    <div v-if="model.facts.length" class="ov__facts">
      <div v-for="fact in model.facts" :key="fact.label" class="ov__fact">
        <p class="ov__fact-value">{{ fact.value }}</p>
        <p class="ov__fact-label">{{ fact.label }}</p>
      </div>
    </div>

    <PeriodComparisonBar
      v-if="model.comparison"
      :comparison="model.comparison"
      class="ov__compare"
    />

    <div v-if="model.observations.length" class="ov__obs">
      <OverviewObservationBlock
        v-for="item in model.observations"
        :key="item.id"
        :item="item"
      />
    </div>
  </div>
</template>

<style scoped>
.ov {
  display: flex;
  flex-direction: column;
  gap: 18px;
  min-width: 0;
}

.ov__hero {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.ov__period {
  margin: 0;
  font-size: 10px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  font-weight: 600;
  color: var(--color-muted);
}

.ov__headline {
  margin: 0;
  display: flex;
  align-items: baseline;
  gap: 8px;
  flex-wrap: wrap;
}

.ov__headline-main {
  font-family: var(--font-haas-grot-disp);
  font-size: 1.75rem;
  font-weight: 600;
  letter-spacing: -0.03em;
  color: var(--color-ink-black);
  line-height: 1.1;
}

.ov__headline-sub {
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.ov__subhead {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-bark);
}

.ov__facts {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px 16px;
}

.ov__fact-value {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 650;
  font-variant-numeric: tabular-nums;
  color: var(--color-ink-black);
  line-height: 1.15;
}

.ov__fact-label {
  margin: 2px 0 0;
  font-size: 11px;
  color: var(--color-muted);
}

.ov__compare {
  padding-top: 4px;
}

.ov__obs {
  display: flex;
  flex-direction: column;
  gap: 0;
}
</style>
