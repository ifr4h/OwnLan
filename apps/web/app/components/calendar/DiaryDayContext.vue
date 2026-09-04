<script setup lang="ts">
import type { DiaryLesson } from '~/composables/useLessons'

const props = defineProps<{
  lessons: DiaryLesson[]
  nowMinutes: number | null
}>()

const nextLesson = computed(() => {
  const scheduled = props.lessons
    .filter(l => l.status === 'scheduled')
    .sort((a, b) => (a.starts_at_time || '').localeCompare(b.starts_at_time || ''))

  if (!scheduled.length) return null

  if (props.nowMinutes != null) {
    const upcoming = scheduled.find((l) => {
      const start = l.starts_at_time
      if (!start) return true
      const [h, m] = start.split(':').map(Number)
      const mins = h * 60 + m
      return mins >= props.nowMinutes!
    })
    return upcoming ?? scheduled[scheduled.length - 1]
  }

  return scheduled[0]
})
</script>

<template>
  <aside v-if="nextLesson" class="context" aria-label="Next lesson">
    <p class="context__eyebrow">Next lesson</p>
    <NuxtLink :to="`/lessons/${nextLesson.id}`" class="context__card">
      <p class="context__name">{{ nextLesson.learner_name }}</p>
      <p class="context__when">{{ nextLesson.starts_at_time }}–{{ nextLesson.ends_at_time }}</p>
      <dl class="context__facts">
        <div v-if="nextLesson.pickup_address" class="context__row">
          <dt>Pickup</dt>
          <dd>{{ nextLesson.pickup_address }}</dd>
        </div>
        <div v-if="nextLesson.learner_last_lesson_summary" class="context__row">
          <dt>Last focus</dt>
          <dd>{{ nextLesson.learner_last_lesson_summary }}</dd>
        </div>
        <div v-if="nextLesson.learner_next_focus" class="context__row">
          <dt>Next focus</dt>
          <dd>{{ nextLesson.learner_next_focus }}</dd>
        </div>
        <div v-if="nextLesson.test_journey?.countdown_label" class="context__row">
          <dt>Test</dt>
          <dd>{{ nextLesson.test_journey.countdown_label }}</dd>
        </div>
      </dl>
    </NuxtLink>
  </aside>
</template>

<style scoped>
.context {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.context__eyebrow {
  margin: 0;
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
}

.context__card {
  display: block;
  padding: var(--spacing-16);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-panel);
  background: var(--surface-card);
  text-decoration: none;
  color: inherit;
  transition: border-color var(--duration-fast) ease;
}

.context__card:hover {
  border-color: rgba(22, 139, 85, 0.45);
}

.context__name {
  margin: 0;
  font-size: var(--text-body);
  font-weight: 600;
}

.context__when {
  margin: 4px 0 0;
  font-size: var(--text-body-sm);
  font-variant-numeric: tabular-nums;
  color: var(--color-muted);
}

.context__facts {
  margin: 12px 0 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.context__row {
  display: grid;
  grid-template-columns: 5.5rem 1fr;
  gap: 8px;
  font-size: var(--text-body-sm);
}

.context__row dt {
  margin: 0;
  color: var(--color-muted);
}

.context__row dd {
  margin: 0;
}
</style>
