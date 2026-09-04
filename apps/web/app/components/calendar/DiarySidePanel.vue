<script setup lang="ts">
import type { DiaryLesson } from '~/composables/useLessons'
import type { DiaryOverviewModel } from '~/utils/diary/overviewModel'
import DiaryOverview from '~/components/calendar/overview/DiaryOverview.vue'

export type DiarySidePanelMode = 'welcome' | 'lesson'

defineProps<{
  mode: DiarySidePanelMode
  lesson: DiaryLesson | null
  overview: DiaryOverviewModel | null
}>()

const emit = defineEmits<{
  close: []
  openFull: [id: number]
  selectDay: [date: string]
}>()

function settlementLabel(lesson: DiaryLesson): string | null {
  if (lesson.status === 'no_show' && lesson.settlement === 'waived') return 'No charge'
  const s = lesson.settlement
  if (!s) return null
  if (s === 'paid') return 'Paid'
  if (s === 'package') return 'Package'
  if (s === 'outstanding') {
    if (lesson.price_pence) {
      return `£${(lesson.price_pence / 100).toFixed(0)} due`
    }
    return 'Unpaid'
  }
  return null
}
</script>

<template>
  <aside
    class="panel"
    :aria-label="mode === 'welcome' ? 'Diary overview' : 'Lesson details'"
  >
    <div class="panel__head">
      <p class="panel__eyebrow">{{ mode === 'welcome' ? 'Overview' : 'Lesson' }}</p>
      <button class="panel__x" type="button" aria-label="Close panel" @click="emit('close')">
        <OlIcon name="close" :size="16" />
      </button>
    </div>

    <div class="panel__body">
      <DiaryOverview
        v-if="mode === 'welcome' && overview"
        :model="overview"
        @select-day="emit('selectDay', $event)"
      />

      <template v-else-if="mode === 'welcome'">
        <p class="panel__quiet">Nothing to show for this period yet.</p>
      </template>

      <template v-else-if="lesson">
        <h2 class="panel__title">{{ lesson.learner_name }}</h2>
        <p class="panel__when">
          {{ lesson.starts_at_time }}–{{ lesson.ends_at_time }}
          <span v-if="lesson.duration_minutes"> · {{ lesson.duration_minutes }} min</span>
        </p>

        <dl class="panel__facts">
          <div v-if="lesson.pickup_address" class="panel__row">
            <dt>Pickup</dt>
            <dd>{{ lesson.pickup_address }}</dd>
          </div>
          <div v-if="settlementLabel(lesson)" class="panel__row">
            <dt>Payment</dt>
            <dd>{{ settlementLabel(lesson) }}</dd>
          </div>
          <div v-if="lesson.learner_last_lesson_summary" class="panel__row">
            <dt>Last focus</dt>
            <dd>{{ lesson.learner_last_lesson_summary }}</dd>
          </div>
          <div v-if="lesson.learner_next_focus" class="panel__row">
            <dt>Next focus</dt>
            <dd>{{ lesson.learner_next_focus }}</dd>
          </div>
          <div v-if="lesson.test_journey?.countdown_label" class="panel__row">
            <dt>Test</dt>
            <dd>{{ lesson.test_journey.countdown_label }}</dd>
          </div>
          <div v-if="lesson.travel_to_next?.is_warning" class="panel__row panel__row--warn">
            <dt>Travel</dt>
            <dd>{{ lesson.travel_to_next.message || 'Tight gap to the next lesson.' }}</dd>
          </div>
          <div v-if="lesson.overlaps" class="panel__row panel__row--warn">
            <dt>Overlap</dt>
            <dd>This booking overlaps another lesson.</dd>
          </div>
        </dl>
      </template>
    </div>

    <div v-if="mode === 'lesson' && lesson" class="panel__foot">
      <button class="ol-btn ol-btn--sm" type="button" @click="emit('openFull', lesson.id)">
        Open full
      </button>
      <button class="ol-btn ol-btn--ghost ol-btn--sm" type="button" @click="emit('close')">
        Close
      </button>
    </div>
  </aside>
</template>

<style scoped>
.panel {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 0;
  min-height: 0;
  height: 100%;
  padding: 18px 16px;
  background: var(--color-parchment);
  color: var(--color-ink-black);
  outline: none;
  overflow: auto;
}

.panel__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  flex-shrink: 0;
}

.panel__eyebrow {
  margin: 0;
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
}

.panel__x {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: var(--color-muted);
  cursor: pointer;
}

.panel__x:hover {
  background: rgba(255, 255, 255, 0.7);
  color: var(--color-ink-black);
}

.panel__body {
  display: flex;
  flex-direction: column;
  gap: 10px;
  flex: 1;
  min-height: 0;
}

.panel__quiet {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.panel__title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 600;
  line-height: 1.3;
  color: var(--color-ink-black);
}

.panel__when {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-muted);
  line-height: 1.45;
  font-variant-numeric: tabular-nums;
}

.panel__facts {
  margin: 8px 0 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.panel__row {
  display: grid;
  grid-template-columns: 5.5rem 1fr;
  gap: 8px;
  font-size: var(--text-body-sm);
}

.panel__row dt {
  margin: 0;
  color: var(--color-muted);
}

.panel__row dd {
  margin: 0;
  color: var(--color-ink-black);
}

.panel__row--warn dd {
  color: #8a6d00;
}

.panel__foot {
  margin-top: auto;
  padding-top: 14px;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  border-top: 1px solid var(--color-border);
  flex-shrink: 0;
}
</style>
