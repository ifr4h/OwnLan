<template>
  <section class="ol-page">
    <header class="ol-page-header">
      <p class="ol-eyebrow">Accounts</p>
      <h1 class="ol-page-title">Reports</h1>
    </header>
    <AccountsNav />
    <p v-if="error" class="ol-error">{{ error }}</p>
    <p v-else-if="loading" class="ol-muted">Loading…</p>
    <template v-else-if="report">
      <AccountsPeriodFilter
        :presets="overview?.presets ?? []"
        :from="from"
        :to="to"
        :range-label="report.range_label"
        @apply-preset="applyPreset"
        @update:from="from = $event"
        @update:to="to = $event"
        @reload="reload"
      />

      <section class="report-grid">
        <article class="ol-card ol-card--flat">
          <p class="ol-eyebrow">Teaching income</p>
          <p class="ol-metric">{{ report.teaching_income_label }}</p>
        </article>
        <article class="ol-card ol-card--flat">
          <p class="ol-eyebrow">Payments received</p>
          <p class="ol-metric">{{ report.money_received_label }}</p>
        </article>
        <article class="ol-card ol-card--flat">
          <p class="ol-eyebrow">Expenses recorded</p>
          <p class="ol-metric">{{ report.spending_label }}</p>
        </article>
        <article class="ol-card ol-card--flat">
          <p class="ol-eyebrow">Difference</p>
          <p class="ol-metric">{{ report.difference_label }}</p>
          <p class="ol-meta">Teaching income minus expenses. Not profit after tax.</p>
        </article>
      </section>

      <section class="ol-panel ol-stack">
        <h2 class="ol-section-title">Export</h2>
        <ul class="export-list">
          <li v-for="t in report.export_types" :key="t.id">
            <a class="ol-btn ol-btn--ghost ol-btn--sm" :href="exportUrl(from, to, t.id)" download>
              {{ t.label }}
            </a>
          </li>
        </ul>
      </section>

      <section class="ol-panel ol-stack">
        <h2 class="ol-section-title">Insights</h2>
        <p class="ol-meta">
          How many active pupils you have, plus new starters, passes and inactive in the dates you pick.
        </p>
        <NuxtLink to="/pupils/report" class="ol-btn ol-btn--sm">Open insights</NuxtLink>
      </section>

      <section class="ol-panel ol-stack">
        <h2 class="ol-section-title">Test fault trends</h2>
        <p class="ol-meta">
          Rolling faults from practical tests you have logged — pass rate, averages, and category breakdown.
        </p>
        <NuxtLink to="/accounts/test-faults" class="ol-btn ol-btn--sm">Open test faults</NuxtLink>
      </section>

      <section v-if="report.spending_by_category?.length" class="ol-panel ol-stack">
        <h2 class="ol-section-title">Expenses by category</h2>
        <ul class="ol-list-divide">
          <li v-for="cat in report.spending_by_category" :key="cat.category" class="ol-row">
            <span>{{ cat.label }}</span>
            <strong>{{ cat.amount_label }}</strong>
          </li>
        </ul>
      </section>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { BusinessOverview } from '~/composables/useBusinessFinance'

useHead({ title: 'Reports · Accounts · OwnLane' })

const { fetchOverview, fetchReport, exportUrl } = useBusinessFinance()
const { from, to, applyPreset, initFromRoute } = useAccountsPeriod()

const overview = ref<BusinessOverview | null>(null)
const report = ref<Record<string, unknown> | null>(null)
const loading = ref(true)
const error = ref('')

async function reload() {
  loading.value = true
  error.value = ''
  try {
    overview.value = await fetchOverview(from.value || undefined, to.value || undefined)
    report.value = await fetchReport(from.value || undefined, to.value || undefined)
    if (!from.value) from.value = overview.value.from
    if (!to.value) to.value = overview.value.to
  } catch (e) {
    error.value = extractApiError(e, 'Could not load report.')
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  initFromRoute()
  await reload()
})
</script>

<style scoped>
.report-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: var(--spacing-8);
  margin-bottom: var(--spacing-16);
}

.export-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
}

@media (max-width: 560px) {
  .report-grid {
    grid-template-columns: 1fr;
  }
}
</style>
