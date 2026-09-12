<template>
  <section class="day-wrap ol-page">
    <header class="day-wrap__header">
      <p class="ol-eyebrow">Temp preview</p>
      <h1 class="ol-page-title">{{ data?.date_display || 'Day wrap' }}</h1>
      <p class="ol-meta">
        Admin recap for the day. Not wired into Today fully yet — try it here first.
      </p>
    </header>

    <div v-if="error" class="ol-card ol-card--flat" style="background: var(--color-danger-wash)">
      <p class="ol-error" role="alert">{{ error }}</p>
    </div>

    <div v-else-if="loading" class="day-wrap__skel" aria-hidden="true">
      <div class="ol-skeleton" style="height: 22px; width: 50%" />
      <div class="ol-skeleton" style="height: 96px; width: 100%; margin-top: 12px" />
      <div class="ol-skeleton" style="height: 72px; width: 100%; margin-top: 12px" />
      <div class="ol-skeleton" style="height: 72px; width: 100%; margin-top: 8px" />
    </div>

    <template v-else-if="data">
      <div v-if="data.empty" class="ol-empty">
        <h2 class="ol-empty__title">No lessons today</h2>
        <p class="ol-empty__copy">Nothing to wrap up. Book a lesson or check back after teaching.</p>
        <div class="ol-empty__actions">
          <NuxtLink to="/lessons/new" class="ol-btn">Book lesson →</NuxtLink>
          <NuxtLink to="/today" class="ol-btn ol-btn--ghost">Back to Today</NuxtLink>
        </div>
      </div>

      <template v-else>
        <DayDoneCard
          v-if="data.celebration"
          :title="data.celebration.title"
          :subtitle="data.celebration.subtitle"
          :complete="!!data.day_complete"
          :actions="[]"
        />

        <DayWrapStats v-if="data.stats?.length" :stats="data.stats" class="day-wrap__stats" />

        <section v-if="openLessons.length" class="day-wrap__block" aria-label="Still to finish">
          <h2 class="ol-section-title">Still to finish</h2>
          <p class="ol-meta day-wrap__hint">
            Progress, money, or next bookings left from today’s lessons.
          </p>
          <div class="ol-panel ol-panel--flush">
            <ul class="ol-list-divide">
              <li v-for="lesson in openLessons" :key="lesson.id" class="day-wrap__row">
                <div class="day-wrap__row-main">
                  <div class="day-wrap__row-top">
                    <span class="ol-row__time" :data-done="lesson.checks.completed ? 'yes' : 'no'">
                      {{ lesson.starts_at_time }}
                    </span>
                    <div class="day-wrap__names">
                      <p class="ol-row__title">{{ lesson.learner_name || 'Pupil' }}</p>
                      <p class="ol-badge" :data-kind="statusKind(lesson)">{{ lesson.status_label }}</p>
                    </div>
                  </div>
                  <p v-if="lesson.detail_line" class="ol-meta day-wrap__detail">
                    {{ lesson.detail_line }}
                  </p>
                  <div v-if="lesson.cta" class="day-wrap__cta">
                    <NuxtLink :to="lesson.cta.path" class="ol-btn ol-btn--dark ol-btn--sm">
                      {{ lesson.cta.label }}
                    </NuxtLink>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </section>

        <section v-if="caughtUpLessons.length" class="day-wrap__block" aria-label="Caught up">
          <h2 class="ol-section-title">Caught up</h2>
          <div class="ol-panel ol-panel--flush">
            <ul class="ol-list-divide">
              <li v-for="lesson in caughtUpLessons" :key="lesson.id" class="day-wrap__row">
                <div class="day-wrap__row-main">
                  <div class="day-wrap__row-top">
                    <span class="ol-row__time" data-done="yes">{{ lesson.starts_at_time }}</span>
                    <div class="day-wrap__names">
                      <p class="ol-row__title">{{ lesson.learner_name || 'Pupil' }}</p>
                      <p class="ol-badge" data-kind="success">{{ lesson.status_label }}</p>
                    </div>
                  </div>
                  <p v-if="lesson.detail_line" class="ol-meta day-wrap__detail">
                    {{ lesson.detail_line }}
                  </p>
                  <p class="ol-meta day-wrap__ok">Caught up</p>
                </div>
              </li>
            </ul>
          </div>
        </section>

        <p class="ol-meta day-wrap__foot">
          <NuxtLink to="/today" class="ol-back">← Back to Today</NuxtLink>
        </p>
      </template>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { DayWrapLesson, DayWrapResponse } from '~/composables/useDayWrap'
import DayDoneCard from '~/components/day-wrap/DayDoneCard.vue'
import DayWrapStats from '~/components/day-wrap/DayWrapStats.vue'

useHead({ title: 'Day wrap · OwnLane' })

const { fetchDayWrap } = useDayWrap()
const { extractApiError } = useApi()

const data = ref<DayWrapResponse | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

const openLessons = computed(() => (data.value?.lessons ?? []).filter(l => l.cta))
const caughtUpLessons = computed(() => (data.value?.lessons ?? []).filter(l => !l.cta))

function statusKind(lesson: DayWrapLesson): string {
  if (lesson.checks.overdue) return 'warning'
  if (lesson.status === 'completed') return 'success'
  if (lesson.status === 'no_show') return 'muted'
  return 'neutral'
}

async function load() {
  loading.value = true
  error.value = null
  try {
    data.value = await fetchDayWrap()
  } catch (e) {
    error.value = extractApiError(e, 'Could not load the day wrap.')
  } finally {
    loading.value = false
  }
}

onMounted(() => { void load() })
</script>

<style scoped>
.day-wrap__header {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.day-wrap__header + :deep(.day-done) {
  margin-top: 12px;
}

.day-wrap__stats {
  margin-top: 12px;
}

.day-wrap__block {
  margin-top: 20px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.day-wrap__hint {
  margin: -4px 0 0;
}

.day-wrap__row {
  padding: 14px 16px;
}

.day-wrap__row-main {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.day-wrap__row-top {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.day-wrap__names {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.day-wrap__detail {
  margin: 0;
  padding-left: 56px;
}

.day-wrap__cta,
.day-wrap__ok {
  padding-left: 56px;
}

.day-wrap__ok {
  color: var(--color-ownlane-green);
}

.day-wrap__foot {
  margin-top: 20px;
}

.ol-row__time[data-done='yes'] {
  text-decoration: line-through;
  color: var(--color-muted);
}

.ol-badge[data-kind='success'] {
  background: var(--color-success-wash);
  color: var(--color-ownlane-green);
}

.ol-badge[data-kind='warning'] {
  background: var(--color-warning-wash);
  color: var(--color-bark);
}

.ol-badge[data-kind='muted'],
.ol-badge[data-kind='neutral'] {
  background: var(--color-parchment);
  color: var(--color-muted);
}
</style>
