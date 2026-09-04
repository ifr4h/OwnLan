<template>
  <section class="accounts-page ol-page">
    <header class="ol-page-header">
      <p class="ol-eyebrow">Your business</p>
      <h1 class="ol-page-title">Accounts</h1>
    </header>

    <AccountsNav />

    <p v-if="error" class="ol-error" role="alert">{{ error }}</p>
    <p v-else-if="loading" class="ol-muted">Loading accounts…</p>

    <template v-else-if="overview">
      <AccountsPeriodFilter
        :presets="overview.presets"
        :from="from"
        :to="to"
        :range-label="overview.range_label"
        :loading="loading"
        @apply-preset="applyPreset"
        @update:from="from = $event"
        @update:to="to = $event"
        @reload="reload"
      />

      <section class="overview-grid" aria-label="This period">
        <article class="ol-card ol-card--flat kpi">
          <p class="ol-eyebrow">Teaching income</p>
          <p class="ol-metric">{{ overview.teaching_income_label }}</p>
          <p class="ol-meta">
            {{ overview.period_is_partial ? 'Completed lessons so far' : 'Completed lessons in period' }}
          </p>
        </article>
        <article class="ol-card ol-card--flat kpi" :class="{ 'kpi--owes': overview.still_owed_pence > 0 }">
          <p class="ol-eyebrow">Still to collect</p>
          <p class="ol-metric">{{ overview.still_owed_label }}</p>
        </article>
        <article class="ol-card ol-card--flat kpi">
          <p class="ol-eyebrow">Booked before {{ overview.to_label }}</p>
          <p class="ol-metric">{{ overview.booked_before_period_end_label }}</p>
        </article>
        <article class="ol-card ol-card--flat kpi kpi--projected">
          <p class="ol-eyebrow">Projected from current bookings</p>
          <p class="ol-metric">{{ overview.projected_from_bookings_label }}</p>
          <p class="ol-meta">Teaching income plus future booked lessons</p>
        </article>
      </section>

      <div class="overview-body">
        <section v-if="overview.goal" class="ol-panel ol-stack goal-panel">
          <h2 class="ol-section-title">{{ overview.goal.period_label }}</h2>
          <p class="ol-metric">{{ overview.goal.target_label }}</p>
          <dl class="goal-stats">
            <div>
              <dt>Teaching income</dt>
              <dd>{{ overview.teaching_income_label }}</dd>
            </div>
            <div>
              <dt>Currently booked to</dt>
              <dd>{{ overview.projected_from_bookings_label }}</dd>
            </div>
            <div v-if="overview.goal_gap_from_bookings_pence">
              <dt>Gap from current bookings</dt>
              <dd>{{ overview.goal_gap_from_bookings_label }}</dd>
            </div>
          </dl>
          <p v-if="overview.estimated_additional_teaching_label" class="ol-meta">
            Around {{ overview.estimated_additional_teaching_label }} more teaching at
            {{ overview.average_teaching_value.label }}
          </p>
          <button class="ol-link-action" type="button" @click="showGoalForm = !showGoalForm">Edit goal</button>
        </section>

        <section v-else class="ol-panel ol-stack">
          <p class="ol-meta">No monthly goal set.</p>
          <button class="ol-btn ol-btn--sm" type="button" @click="showGoalForm = true">Set a goal</button>
        </section>

        <section class="ol-panel ol-stack capacity-panel">
          <h2 class="ol-section-title">Diary capacity</h2>
          <p class="ol-metric">{{ overview.diary_capacity.usable_hours_label }}</p>
          <p class="ol-meta">Usable teaching time left in this period</p>
          <p v-if="overview.diary_capacity.distinct_pupils_matching > 0" class="ol-meta">
            {{ overview.diary_capacity.distinct_pupils_matching }}
            {{ overview.diary_capacity.distinct_pupils_matching === 1 ? 'pupil could fit' : 'pupils could fit' }}
            those gaps
            <span v-if="overview.diary_capacity.pupil_gap_matches > overview.diary_capacity.distinct_pupils_matching">
              ({{ overview.diary_capacity.pupil_gap_matches }} suitable matches)
            </span>
          </p>
          <NuxtLink :to="overview.diary_capacity.diary_path" class="ol-btn ol-btn--ghost ol-btn--sm">
            See diary opportunities
          </NuxtLink>
        </section>
      </div>

      <form v-if="showGoalForm" class="ol-panel goal-form ol-stack" @submit.prevent="onSaveGoal">
        <label class="ol-field">
          <span class="ol-field__label">Monthly goal (£)</span>
          <input v-model="goalAmount" class="ol-input" inputmode="decimal" placeholder="4000" required>
        </label>
        <div class="goal-form__actions">
          <button class="ol-btn ol-btn--sm" type="submit" :disabled="savingGoal">Save</button>
          <button
            v-if="overview.goal"
            class="ol-link-action"
            type="button"
            @click="onDeleteGoal"
          >
            Remove goal
          </button>
        </div>
        <p v-if="goalError" class="ol-error">{{ goalError }}</p>
      </form>

      <section class="ol-panel ol-stack">
        <div class="panel-head">
          <h2 class="ol-section-title">Still to collect</h2>
          <NuxtLink to="/accounts/payments" class="ol-link-action">View payments</NuxtLink>
        </div>
        <ul v-if="overview.who_owes.length" class="ol-list-divide">
          <li v-for="row in overview.who_owes.slice(0, 5)" :key="row.learner_id" class="ol-row">
            <div class="ol-row__main">
              <p class="ol-row__title">{{ row.learner_name }}</p>
              <p class="ol-row__meta">{{ row.amount_owed_label }}</p>
            </div>
            <NuxtLink :to="row.cta_path" class="ol-btn ol-btn--ghost ol-btn--sm">Record payment</NuxtLink>
          </li>
        </ul>
        <p v-else class="ol-meta">Nobody owes you right now.</p>
      </section>

      <section class="ol-panel ol-stack">
        <h2 class="ol-section-title">Monthly trend</h2>
        <ul class="trend">
          <li v-for="row in overview.monthly_trend" :key="row.month" class="trend__row">
            <span class="trend__label">{{ row.label }}</span>
            <span class="trend__value">
              {{ row.teaching_income_label }}
              <span v-if="row.is_partial" class="ol-meta"> so far</span>
            </span>
          </li>
        </ul>
      </section>

      <section class="ol-panel ol-stack secondary-stats">
        <article>
          <p class="ol-eyebrow">Payments received</p>
          <p class="ol-time">{{ overview.money_received_label }}</p>
        </article>
        <article>
          <p class="ol-eyebrow">Expenses recorded</p>
          <p class="ol-time">{{ overview.spending_label }}</p>
        </article>
      </section>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { BusinessOverview } from '~/composables/useBusinessFinance'
import { poundsInputToPence } from '~/composables/useFinance'

useHead({ title: 'Accounts · OwnLane' })

const { fetchOverview, upsertGoal, deleteGoal } = useBusinessFinance()
const { from, to, applyPreset, initFromRoute } = useAccountsPeriod()

const overview = ref<BusinessOverview | null>(null)
const loading = ref(true)
const error = ref('')
const showGoalForm = ref(false)
const goalAmount = ref('')
const savingGoal = ref(false)
const goalError = ref('')

async function reload() {
  loading.value = true
  error.value = ''
  try {
    overview.value = await fetchOverview(from.value || undefined, to.value || undefined)
    if (!from.value) from.value = overview.value.from
    if (!to.value) to.value = overview.value.to
    if (overview.value.goal) {
      goalAmount.value = String(overview.value.goal.target_pence / 100)
    }
  } catch (e) {
    error.value = extractApiError(e, 'Could not load accounts.')
  } finally {
    loading.value = false
  }
}

async function onSaveGoal() {
  goalError.value = ''
  const pence = poundsInputToPence(goalAmount.value)
  if (pence === null || pence <= 0) {
    goalError.value = 'Enter a goal like 4000'
    return
  }
  if (!overview.value) return
  const [y, m] = overview.value.from.split('-').map(Number)
  savingGoal.value = true
  try {
    await upsertGoal({ period_year: y, period_month: m, target_pence: pence })
    showGoalForm.value = false
    await reload()
  } catch (e) {
    goalError.value = extractApiError(e, 'Could not save goal.')
  } finally {
    savingGoal.value = false
  }
}

async function onDeleteGoal() {
  if (!overview.value) return
  const [y, m] = overview.value.from.split('-').map(Number)
  try {
    await deleteGoal(y, m)
    showGoalForm.value = false
    await reload()
  } catch (e) {
    goalError.value = extractApiError(e, 'Could not remove goal.')
  }
}

onMounted(async () => {
  initFromRoute()
  await reload()
})
</script>

<style scoped>
.overview-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: var(--spacing-8);
  margin-bottom: var(--spacing-16);
}

.kpi {
  padding: var(--spacing-16);
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.kpi--owes {
  background: var(--color-danger-wash);
}

.kpi--projected {
  grid-column: 1 / -1;
  border-color: var(--color-ownlane-green);
}

.overview-body {
  display: grid;
  gap: var(--spacing-12);
  margin-bottom: var(--spacing-16);
}

@media (min-width: 900px) {
  .overview-body {
    grid-template-columns: 1fr 1fr;
  }
}

.goal-stats {
  display: grid;
  gap: var(--spacing-8);
  margin: 0;
}

.goal-stats dt {
  font-size: var(--text-body-sm);
  color: var(--color-text-muted);
}

.goal-stats dd {
  margin: 0;
  font-weight: 600;
}

.panel-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--spacing-8);
}

.trend {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.trend__row {
  display: flex;
  justify-content: space-between;
  gap: var(--spacing-12);
  font-size: var(--text-body-sm);
}

.trend__label {
  font-weight: 600;
  letter-spacing: 0.04em;
}

.secondary-stats {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--spacing-16);
}

.goal-form__actions {
  display: flex;
  gap: var(--spacing-12);
  align-items: center;
}

@media (max-width: 560px) {
  .overview-grid {
    grid-template-columns: 1fr;
  }

  .kpi--projected {
    grid-column: auto;
  }
}
</style>
