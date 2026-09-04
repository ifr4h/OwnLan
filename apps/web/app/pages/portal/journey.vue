<template>
  <div class="journey">
    <header class="journey__head">
      <h1 class="journey__title">{{ t('journey.title') }}</h1>
      <p v-if="data?.started_on_display" class="journey__started">
        {{ t('journey.started', { date: data.started_on_display }) }}
      </p>
      <p v-else-if="!loading" class="journey__started">{{ t('journey.notStarted') }}</p>
    </header>

    <template v-if="loading && !data">
      <PortalSkeleton variant="block" />
      <PortalSkeleton variant="line" />
    </template>
    <p v-else-if="error" class="journey__error" role="alert">{{ error }}</p>

    <template v-else-if="data">
      <div class="journey__metrics">
        <PortalMetric :value="data.stats.total_hours_label" :label="t('home.hoursDriven')" />
        <PortalMetric :value="data.stats.lessons_completed" :label="t('home.lessonsDone')" />
        <PortalMetric
          :value="data.stats.hours_this_month_label"
          label="This month"
          compact
        />
      </div>

      <p v-if="data.current_focus" class="journey__focus">
        <span class="journey__focus-label">{{ t('journey.currentFocus') }}</span>
        {{ data.current_focus }}
      </p>

      <section v-if="data.hours_by_month.length" class="journey__chart" aria-labelledby="hours-chart">
        <h2 id="hours-chart" class="journey__section">{{ t('journey.hoursChart') }}</h2>
        <ClientOnly>
          <PortalHoursChart
            :months="data.hours_by_month"
            :aria-label="t('journey.hoursChart')"
            @select-month="onSelectMonth"
          />
        </ClientOnly>
        <p v-if="selectedMonth" class="journey__month-note">
          {{ t('journey.selectMonth', { month: selectedMonth.label }) }}
          — {{ selectedMonth.lesson_ids.length }}
          {{ selectedMonth.lesson_ids.length === 1 ? 'lesson' : 'lessons' }}
        </p>
      </section>

      <section class="journey__history" aria-labelledby="history">
        <h2 id="history" class="journey__section">{{ t('journey.history') }}</h2>
        <p v-if="!filteredHistory.length" class="journey__empty">{{ t('journey.emptyHistory') }}</p>
        <ol v-else class="timeline">
          <li v-for="item in filteredHistory" :key="item.id" class="timeline__item">
            <div class="timeline__rail" aria-hidden="true" />
            <time class="timeline__date">{{ item.date_label }}</time>
            <div class="timeline__body">
              <p class="timeline__title">{{ item.title }}</p>
              <p class="timeline__meta">{{ item.duration_label }}</p>
              <p v-if="item.learner_summary" class="timeline__summary">{{ item.learner_summary }}</p>
              <NuxtLink :to="`/portal/recap/${item.id}`" class="timeline__link">
                {{ t('home.viewRecap') }}
              </NuxtLink>
            </div>
          </li>
        </ol>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { PortalHoursMonth, PortalJourney } from '~/composables/usePortal'

definePageMeta({ layout: 'portal' })
useHead({ title: 'Your journey · OwnLane' })

const { t } = usePortalI18n()
const { fetchJourney } = usePortal()

const data = ref<PortalJourney | null>(null)
const loading = ref(true)
const error = ref('')
const selectedMonth = ref<PortalHoursMonth | null>(null)

const filteredHistory = computed(() => {
  if (!data.value) return []
  if (!selectedMonth.value) return data.value.history
  const ids = new Set(selectedMonth.value.lesson_ids)
  return data.value.history.filter(h => ids.has(h.id))
})

function onSelectMonth(payload: PortalHoursMonth) {
  if (selectedMonth.value?.month === payload.month) {
    selectedMonth.value = null
  } else {
    selectedMonth.value = payload
  }
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await fetchJourney()
  } catch (e) {
    error.value = extractApiError(e, t('common.errorGeneric'))
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.journey {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-28);
  padding-top: var(--spacing-8);
}

.journey__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-md);
  letter-spacing: var(--tracking-heading-md);
  line-height: var(--leading-heading-md);
}

.journey__started {
  margin-top: var(--spacing-4);
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.journey__error {
  color: var(--color-danger);
  font-size: var(--text-body-sm);
}

.journey__metrics {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--spacing-20);
}

.journey__focus {
  font-size: var(--text-body-sm);
  line-height: var(--leading-body-sm);
  padding: var(--spacing-16) 0;
  border-top: 1px solid var(--color-border);
  border-bottom: 1px solid var(--color-border);
}

.journey__focus-label {
  display: block;
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
  margin-bottom: var(--spacing-4);
}

.journey__section {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
  margin-bottom: var(--spacing-16);
}

.journey__month-note {
  margin-top: var(--spacing-8);
  font-size: var(--text-meta);
  color: var(--color-ownlane-green);
}

.journey__empty {
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.timeline {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0;
}

.timeline__item {
  position: relative;
  display: grid;
  grid-template-columns: 4.5rem 1fr;
  gap: var(--spacing-12);
  padding: 0 0 var(--spacing-24);
}

.timeline__rail {
  position: absolute;
  left: calc(4.5rem + 6px);
  top: 8px;
  bottom: 0;
  width: 2px;
  background: var(--color-frost-green);
}

.timeline__item:last-child .timeline__rail {
  display: none;
}

.timeline__date {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  color: var(--color-muted);
  padding-top: 2px;
}

.timeline__title {
  font-size: var(--text-body-sm);
  font-weight: 500;
}

.timeline__meta,
.timeline__summary {
  font-size: var(--text-meta);
  color: var(--color-muted);
  margin-top: 2px;
}

.timeline__summary {
  color: rgba(17, 17, 24, 0.75);
  margin-top: var(--spacing-4);
}

.timeline__link {
  display: inline-flex;
  min-height: 44px;
  align-items: center;
  font-size: var(--text-body-sm);
  color: var(--color-ownlane-green);
  text-decoration: none;
}
</style>
