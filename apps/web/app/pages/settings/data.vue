<template>
  <section class="ol-page">
    <header class="ol-page-header">
      <NuxtLink to="/settings" class="ol-link-action">← Settings</NuxtLink>
      <p class="ol-eyebrow">Settings</p>
      <h1 class="ol-page-title">Data &amp; exports</h1>
      <p class="ol-meta">Download your business records or send them to your accountant.</p>
    </header>

    <section class="ol-panel ol-stack">
      <h2 class="ol-section-title">Export data</h2>
      <ul class="export-list">
        <li>
          <div>
            <p class="export-list__title">Pupils</p>
            <p class="ol-meta">Names, contact details, status — no private notes.</p>
          </div>
          <a class="ol-btn ol-btn--ghost ol-btn--sm" :href="downloadUrl('pupils')" download>Export pupils</a>
        </li>
        <li>
          <div>
            <p class="export-list__title">Lessons</p>
          </div>
          <a class="ol-btn ol-btn--ghost ol-btn--sm" :href="downloadUrl('lessons', from, to)" download>Export lessons</a>
        </li>
        <li>
          <div>
            <p class="export-list__title">Payments</p>
          </div>
          <a class="ol-btn ol-btn--ghost ol-btn--sm" :href="downloadUrl('payments', from, to)" download>Export payments</a>
        </li>
        <li>
          <div>
            <p class="export-list__title">Expenses</p>
          </div>
          <a class="ol-btn ol-btn--ghost ol-btn--sm" :href="downloadUrl('expenses', from, to)" download>Export expenses</a>
        </li>
        <li>
          <div>
            <p class="export-list__title">Mileage</p>
          </div>
          <a class="ol-btn ol-btn--ghost ol-btn--sm" :href="downloadUrl('mileage', from, to)" download>Export mileage</a>
        </li>
        <li>
          <div>
            <p class="export-list__title">Teaching income</p>
          </div>
          <a class="ol-btn ol-btn--ghost ol-btn--sm" :href="downloadUrl('teaching_income', from, to)" download>Export teaching income</a>
        </li>
      </ul>
    </section>

    <section class="ol-panel ol-stack">
      <h2 class="ol-section-title">Accountant export</h2>
      <div class="period-presets">
        <button class="ol-chip" type="button" @click="setThisTaxYear">This tax year</button>
        <button class="ol-chip" type="button" @click="setLastTaxYear">Last tax year</button>
      </div>
      <div class="period-row">
        <label class="ol-field">
          <span class="ol-field__label">From</span>
          <input v-model="from" class="ol-input ol-input--date" type="date">
        </label>
        <label class="ol-field">
          <span class="ol-field__label">To</span>
          <input v-model="to" class="ol-input ol-input--date" type="date">
        </label>
      </div>
      <p v-if="summary" class="ol-meta">{{ summary.range_label }}</p>
      <ul v-if="summary" class="includes">
        <li v-for="item in summary.includes" :key="item">{{ item }}</li>
      </ul>
      <a class="ol-btn" :href="accountantPackUrl(from, to)" download>Accountant export</a>
    </section>

    <section class="ol-panel ol-stack">
      <h2 class="ol-section-title">Import data</h2>
      <p class="ol-meta">Bring pupils in from a spreadsheet.</p>
      <NuxtLink to="/pupils/import" class="ol-btn ol-btn--ghost ol-btn--sm">Import pupils</NuxtLink>
    </section>
  </section>
</template>

<script setup lang="ts">
import type { AccountantExportSummary } from '~/composables/useDataExport'

useHead({ title: 'Data & exports · Settings · OwnLane' })

const { downloadUrl, accountantPackUrl, fetchAccountantSummary } = useDataExport()

const from = ref('')
const to = ref('')
const summary = ref<AccountantExportSummary | null>(null)

function monthStartIso(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`
}

function todayIso(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function ukTaxYearStart(forDate = new Date()): number {
  const month = forDate.getMonth() + 1
  const day = forDate.getDate()
  if (month > 4 || (month === 4 && day >= 6)) {
    return forDate.getFullYear()
  }
  return forDate.getFullYear() - 1
}

function isoFromParts(year: number, month: number, day: number): string {
  return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
}

function setThisTaxYear() {
  const startYear = ukTaxYearStart()
  from.value = isoFromParts(startYear, 4, 6)
  to.value = todayIso()
}

function setLastTaxYear() {
  const startYear = ukTaxYearStart() - 1
  from.value = isoFromParts(startYear, 4, 6)
  to.value = isoFromParts(startYear + 1, 4, 5)
}

watch([from, to], async () => {
  if (!from.value || !to.value) return
  try {
    summary.value = await fetchAccountantSummary(from.value, to.value)
  } catch {
    summary.value = null
  }
})

onMounted(() => {
  from.value = monthStartIso()
  to.value = todayIso()
})
</script>

<style scoped>
.export-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.export-list li {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--spacing-12);
  flex-wrap: wrap;
}

.export-list__title {
  font-weight: 600;
  margin: 0 0 2px;
}

.period-presets {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
}

.period-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--spacing-12);
}

.includes {
  margin: 0;
  padding-left: 1.2rem;
  font-size: var(--text-body-sm);
  color: var(--color-text-muted);
}
</style>
