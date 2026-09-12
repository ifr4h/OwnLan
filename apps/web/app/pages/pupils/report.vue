<template>
  <section class="ol-page learner-report">
    <header class="ol-page-header learner-report__header">
      <div>
        <p class="ol-eyebrow">Pupils</p>
        <h1 class="ol-page-title">Insights</h1>
      </div>
      <NuxtLink to="/pupils" class="ol-btn ol-btn--ghost ol-btn--sm">Back to pupils</NuxtLink>
    </header>

    <p v-if="error" class="ol-error" role="alert">{{ error }}</p>
    <p v-else-if="loading && !report" class="ol-muted">Loading…</p>

    <template v-else-if="report">
      <AccountsPeriodFilter
        :presets="report.presets"
        :from="from"
        :to="to"
        :range-label="report.range_label"
        :loading="loading"
        @apply-preset="onPreset"
        @update:from="from = $event"
        @update:to="to = $event"
        @reload="reload"
      />

      <div class="learner-report__board">
        <section class="learner-report__summary" aria-labelledby="learner-count-heading">
          <h2 id="learner-count-heading" class="learner-report__card-title">Learner count</h2>
          <p class="learner-report__hero-value">{{ report.learner_count }}</p>
          <p class="learner-report__range">{{ report.range_label }}</p>
          <ul class="learner-report__pills">
            <li v-for="row in report.breakdown" :key="row.key" class="learner-report__pill">
              <span>{{ row.label }}</span>
              <strong>{{ row.count }}</strong>
            </li>
          </ul>
        </section>

        <article class="learner-report__card">
          <h2 class="learner-report__card-title">Growth rate</h2>
          <p class="learner-report__hero-value">{{ report.growth_label }}</p>
          <PupilsLearnerSparkline
            class="learner-report__chart"
            :values="report.growth_series"
            :labels="report.month_labels"
            tone="green"
          />
        </article>

        <article class="learner-report__card">
          <h2 class="learner-report__card-title">New learners</h2>
          <p class="learner-report__hero-value">{{ report.new_learners }}</p>
          <PupilsLearnerSparkline
            class="learner-report__chart"
            :values="report.new_series"
            :labels="report.month_labels"
            tone="ink"
          />
        </article>
      </div>

      <p class="learner-report__hint">{{ report.hint }}</p>

      <PupilsPupilTracker
        class="learner-report__tracker"
        :counts="report.status_counts"
        :attention="attention"
      />

      <NuxtLink to="/pupils" class="learner-report__footer">
        <OlIcon name="pupils" :size="16" />
        <span>View pupils</span>
      </NuxtLink>
    </template>
  </section>
</template>

<script setup lang="ts">
import type {
  LearnerReport,
  LearnerReportPreset,
  PupilListAttention,
} from '~/composables/usePupils'

useHead({ title: 'Insights · Pupils · OwnLane' })

const { fetchLearnerReport, listPupils } = usePupils()
const route = useRoute()
const router = useRouter()

const report = ref<LearnerReport | null>(null)
const attention = ref<PupilListAttention | null>(null)
const loading = ref(true)
const error = ref('')
const from = ref('')
const to = ref('')

function syncQuery() {
  void router.replace({
    query: {
      ...route.query,
      from: from.value || undefined,
      to: to.value || undefined,
    },
  })
}

function onPreset(preset: LearnerReportPreset) {
  from.value = preset.from
  to.value = preset.to
  syncQuery()
  void reload()
}

async function reload() {
  loading.value = true
  error.value = ''
  try {
    const [nextReport] = await Promise.all([
      fetchLearnerReport(from.value || undefined, to.value || undefined),
      loadAttention(),
    ])
    report.value = nextReport
    from.value = nextReport.from
    to.value = nextReport.to
    syncQuery()
  } catch (e) {
    error.value = extractApiError(e, 'Could not load learner report.')
  } finally {
    loading.value = false
  }
}

async function loadAttention() {
  try {
    const result = await listPupils('', 'active')
    attention.value = result.attention ?? null
  } catch {
    attention.value = null
  }
}

onMounted(async () => {
  from.value = typeof route.query.from === 'string' ? route.query.from : ''
  to.value = typeof route.query.to === 'string' ? route.query.to : ''
  await reload()
})
</script>

<style scoped>
.learner-report__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.learner-report__board {
  display: grid;
  grid-template-columns: minmax(220px, 0.9fr) 1fr 1fr;
  gap: 16px;
  margin-top: 8px;
  align-items: stretch;
}

.learner-report__summary,
.learner-report__card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-height: 260px;
}

.learner-report__summary {
  padding: 8px 4px 8px 0;
}

.learner-report__card {
  background: var(--color-paper-white);
  border: 1px solid var(--color-border);
  border-radius: 20px;
  padding: 20px;
}

.learner-report__card-title {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink-black);
}

.learner-report__hero-value {
  margin: 0;
  font-size: 40px;
  font-weight: 650;
  line-height: 1.05;
  letter-spacing: -0.02em;
  color: var(--color-ink-black);
}

.learner-report__range {
  margin: 0;
  font-size: 13px;
  color: var(--color-muted);
}

.learner-report__pills {
  list-style: none;
  margin: 8px 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.learner-report__pill {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--color-ownlane-green) 8%, var(--color-parchment));
  font-size: 14px;
  color: var(--color-ink-black);
}

.learner-report__pill strong {
  font-weight: 650;
}

.learner-report__chart {
  margin-top: auto;
}

.learner-report__hint {
  margin: 16px 0 0;
  font-size: 13px;
  color: var(--color-muted);
  max-width: 62ch;
}

.learner-report__tracker {
  margin-top: 20px;
}

.learner-report__footer {
  margin-top: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 14px 16px;
  border-radius: 14px;
  background: color-mix(in srgb, var(--color-ownlane-green) 8%, var(--color-parchment));
  color: var(--color-ink-black);
  text-decoration: none;
  font-size: 14px;
  font-weight: 600;
}

.learner-report__footer:hover {
  filter: brightness(0.98);
}

@media (max-width: 960px) {
  .learner-report__board {
    grid-template-columns: 1fr;
  }
}
</style>
