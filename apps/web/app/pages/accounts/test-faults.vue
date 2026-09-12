<template>
  <section class="ol-page">
    <header class="ol-page-header">
      <p class="ol-eyebrow">Accounts</p>
      <h1 class="ol-page-title">Test faults</h1>
      <p class="ol-lede">
        Rolling view of faults marked on your pupils’ practical tests — same idea as the DVSA ADI report.
      </p>
    </header>
    <AccountsNav />

    <div class="toolbar">
      <div class="range">
        <label class="ol-field">
          <span class="ol-label">From</span>
          <input v-model="from" class="ol-input" type="date" @change="reload">
        </label>
        <label class="ol-field">
          <span class="ol-label">To</span>
          <input v-model="to" class="ol-input" type="date" @change="reload">
        </label>
      </div>
      <div class="toolbar__actions">
        <button type="button" class="ol-btn ol-btn--ghost ol-btn--sm" @click="setRolling12">
          Last 12 months
        </button>
        <NuxtLink to="/pupils" class="ol-btn ol-btn--sm">Log a test result</NuxtLink>
      </div>
    </div>

    <p v-if="error" class="ol-error" role="alert">{{ error }}</p>
    <p v-else-if="loading" class="ol-muted">Loading…</p>

    <template v-else-if="stats">
      <p class="ol-meta range-label">{{ stats.range_label }}</p>

      <section v-if="stats.empty" class="ol-panel empty">
        <h2 class="ol-section-title">No test results in this window</h2>
        <p>
          After a practical, open the pupil and log the sheet. Once you’ve got a few in, this fills with fault
          trends you can actually teach against.
        </p>
        <NuxtLink to="/pupils" class="ol-btn">Find a pupil</NuxtLink>
      </section>

      <template v-else>
        <section class="kpi-grid" aria-label="Test summary">
          <article class="ol-card ol-card--flat">
            <p class="ol-eyebrow">Pass rate</p>
            <p class="ol-metric">{{ stats.summary.pass_rate_label || '—' }}</p>
            <p class="ol-meta">
              {{ stats.summary.tests_passed }} passed of {{ stats.summary.tests_taken }}
            </p>
          </article>
          <article class="ol-card ol-card--flat">
            <p class="ol-eyebrow">Avg driving faults</p>
            <p class="ol-metric">{{ formatAvg(stats.summary.avg_driving_faults) }}</p>
            <p class="ol-meta">{{ stats.summary.total_driving_faults }} in total</p>
          </article>
          <article class="ol-card ol-card--flat">
            <p class="ol-eyebrow">Avg serious</p>
            <p class="ol-metric">{{ formatAvg(stats.summary.avg_serious_faults) }}</p>
            <p class="ol-meta">{{ stats.summary.total_serious_faults }} in total</p>
          </article>
          <article class="ol-card ol-card--flat">
            <p class="ol-eyebrow">Examiner action</p>
            <p class="ol-metric">
              {{
                stats.summary.examiner_action_percent == null
                  ? '—'
                  : `${trimNum(stats.summary.examiner_action_percent)}%`
              }}
            </p>
            <p class="ol-meta">{{ stats.summary.examiner_action_count }} tests</p>
          </article>
        </section>

        <section class="ol-panel ol-stack">
          <h2 class="ol-section-title">DVSA-style indicators</h2>
          <p class="ol-meta">
            Same four numbers DVSA watches on a rolling 12 months. OwnLane shows your logged tests only —
            not an official DVSA score.
          </p>
          <ul class="indicators">
            <li
              v-for="ind in stats.indicators"
              :key="ind.key"
              class="indicator"
              :data-triggered="ind.triggered ? 'yes' : 'no'"
            >
              <div>
                <p class="indicator__label">{{ ind.label }}</p>
                <p class="indicator__trigger">Trigger: {{ ind.trigger }}</p>
              </div>
              <p class="indicator__value">{{ ind.value_label }}</p>
            </li>
          </ul>
        </section>

        <section class="ol-panel ol-stack">
          <div class="panel-head">
            <h2 class="ol-section-title">Faults by category</h2>
            <div class="seg">
              <button
                type="button"
                class="ol-chip"
                :class="{ 'ol-chip--on': viewMode === 'category' }"
                @click="viewMode = 'category'"
              >
                Category
              </button>
              <button
                type="button"
                class="ol-chip"
                :class="{ 'ol-chip--on': viewMode === 'area' }"
                @click="viewMode = 'area'"
              >
                Area
              </button>
            </div>
          </div>

          <ul v-if="viewMode === 'category'" class="bars">
            <li v-for="row in stats.faults_by_category" :key="row.fault_code" class="bar">
              <div class="bar__meta">
                <p class="bar__label">{{ row.fault_label }}</p>
                <p class="bar__counts">
                  <span>{{ row.driving }} DF</span>
                  <span v-if="row.serious"> · {{ row.serious }} S</span>
                  <span v-if="row.dangerous"> · {{ row.dangerous }} D</span>
                </p>
              </div>
              <div class="bar__track" aria-hidden="true">
                <span class="bar__fill" :style="{ width: `${row.bar_percent}%` }" />
              </div>
              <div class="bar__end">
                <strong>{{ row.total }}</strong>
                <NuxtLink
                  v-if="row.skill_code"
                  class="ol-link-action"
                  :to="`/teaching?skill=${row.skill_code}`"
                >
                  Teach
                </NuxtLink>
              </div>
            </li>
          </ul>

          <ul v-else class="bars">
            <li v-for="row in stats.faults_by_area" :key="row.area" class="bar">
              <div class="bar__meta">
                <p class="bar__label">{{ row.area }}</p>
                <p class="bar__counts">
                  <span>{{ row.driving }} DF</span>
                  <span v-if="row.serious"> · {{ row.serious }} S</span>
                  <span v-if="row.dangerous"> · {{ row.dangerous }} D</span>
                </p>
              </div>
              <div class="bar__track" aria-hidden="true">
                <span class="bar__fill" :style="{ width: `${row.bar_percent}%` }" />
              </div>
              <strong class="bar__total">{{ row.total }}</strong>
            </li>
          </ul>
        </section>

        <section v-if="stats.mock_comparison.items.length" class="ol-panel ol-stack">
          <h2 class="ol-section-title">Same window in mocks</h2>
          <p class="ol-meta">{{ stats.mock_comparison.note }}</p>
          <ul class="mock-list">
            <li v-for="row in stats.mock_comparison.items" :key="row.fault_code" class="mock-row">
              <span>{{ row.fault_label }}</span>
              <strong>{{ row.total }}</strong>
            </li>
          </ul>
        </section>

        <section class="ol-panel ol-stack">
          <h2 class="ol-section-title">Recent tests</h2>
          <ul class="test-list">
            <li v-for="t in stats.recent_tests" :key="t.id">
              <NuxtLink :to="`/practical-tests/${t.id}`" class="test-card">
                <div>
                  <p class="test-card__name">{{ t.learner_name }}</p>
                  <p class="ol-meta">{{ t.date_display }}{{ t.test_centre ? ` · ${t.test_centre}` : '' }}</p>
                </div>
                <div class="test-card__right">
                  <span class="badge" :data-result="t.result">{{ t.result_label }}</span>
                  <p class="ol-meta">
                    {{ t.driving_faults_count }} DF
                    <span v-if="t.serious_faults_count"> · {{ t.serious_faults_count }} S</span>
                    <span v-if="t.dangerous_faults_count"> · {{ t.dangerous_faults_count }} D</span>
                  </p>
                </div>
              </NuxtLink>
            </li>
          </ul>
        </section>
      </template>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { PracticalTestStats } from '~/composables/usePracticalTests'

useHead({ title: 'Test faults · Accounts · OwnLane' })

const { fetchStats } = usePracticalTests()

const stats = ref<PracticalTestStats | null>(null)
const loading = ref(true)
const error = ref('')
const from = ref('')
const to = ref('')
const viewMode = ref<'category' | 'area'>('category')

function formatAvg(n: number | null) {
  if (n == null) return '—'
  return trimNum(n)
}

function trimNum(n: number) {
  return String(Number(n.toFixed(2)))
}

function setRolling12() {
  const end = new Date()
  const start = new Date()
  start.setFullYear(start.getFullYear() - 1)
  to.value = iso(end)
  from.value = iso(start)
  void reload()
}

function iso(d: Date) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

async function reload() {
  loading.value = true
  error.value = ''
  try {
    stats.value = await fetchStats(from.value || undefined, to.value || undefined)
    from.value = stats.value.from
    to.value = stats.value.to
  } catch (e) {
    error.value = extractApiError(e, 'Could not load test fault stats.')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void reload()
})
</script>

<style scoped>
.ol-lede {
  margin: var(--spacing-8) 0 0;
  max-width: 42rem;
  color: var(--color-bark);
  font: 400 15px/1.45 var(--font-haas-grot-text);
}

.toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-12);
  justify-content: space-between;
  align-items: end;
  margin-bottom: var(--spacing-16);
}

.range {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--spacing-8);
}

.toolbar__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
}

.range-label {
  margin: 0 0 var(--spacing-12);
}

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--spacing-8);
  margin-bottom: var(--spacing-16);
}

.empty p {
  margin: 0 0 var(--spacing-16);
  color: var(--color-bark);
}

.indicators {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: var(--spacing-8);
}

.indicator {
  display: flex;
  justify-content: space-between;
  gap: var(--spacing-12);
  align-items: center;
  padding: var(--spacing-12);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-small);
  background: var(--color-paper-white);
}

.indicator[data-triggered='yes'] {
  border-color: var(--color-warning);
  background: var(--color-warning-wash);
}

.indicator__label {
  margin: 0;
  font: 600 14px/1.3 var(--font-haas-grot-text);
}

.indicator__trigger {
  margin: 2px 0 0;
  font: 400 12px/1.3 var(--font-haas-grot-text);
  color: var(--color-muted);
}

.indicator__value {
  margin: 0;
  font: 600 18px/1 var(--font-haas-grot-disp);
}

.panel-head {
  display: flex;
  justify-content: space-between;
  gap: var(--spacing-12);
  align-items: center;
  flex-wrap: wrap;
}

.seg {
  display: flex;
  gap: var(--spacing-6);
}

.bars {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: var(--spacing-12);
}

.bar {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1.6fr) auto;
  gap: var(--spacing-12);
  align-items: center;
}

.bar__label {
  margin: 0;
  font: 500 14px/1.3 var(--font-haas-grot-text);
}

.bar__counts {
  margin: 2px 0 0;
  font: 400 12px/1.3 var(--font-haas-grot-text);
  color: var(--color-muted);
}

.bar__track {
  height: 10px;
  border-radius: 999px;
  background: var(--color-parchment);
  overflow: hidden;
}

.bar__fill {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--color-ownlane-green);
}

.bar__end {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}

.bar__total {
  text-align: right;
}

.mock-list,
.test-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.mock-row {
  display: flex;
  justify-content: space-between;
  gap: var(--spacing-12);
  padding: var(--spacing-8) 0;
  border-bottom: 1px solid var(--color-border);
  font: 400 14px/1.3 var(--font-haas-grot-text);
}

.test-card {
  display: flex;
  justify-content: space-between;
  gap: var(--spacing-12);
  padding: var(--spacing-12) 0;
  border-bottom: 1px solid var(--color-border);
  text-decoration: none;
  color: inherit;
}

.test-card__name {
  margin: 0;
  font: 600 15px/1.3 var(--font-haas-grot-text);
}

.test-card__right {
  text-align: right;
}

.badge {
  display: inline-flex;
  padding: 2px 10px;
  border-radius: 999px;
  font: 600 12px/1.4 var(--font-haas-grot-text);
  background: var(--color-parchment);
}

.badge[data-result='pass'] {
  background: var(--color-success-wash);
  color: var(--color-success);
}

.badge[data-result='fail'] {
  background: var(--color-danger-wash);
  color: var(--color-danger);
}

@media (max-width: 900px) {
  .kpi-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .bar {
    grid-template-columns: 1fr auto;
  }

  .bar__track {
    grid-column: 1 / -1;
    order: 3;
  }
}

@media (max-width: 560px) {
  .kpi-grid,
  .range {
    grid-template-columns: 1fr;
  }
}
</style>
