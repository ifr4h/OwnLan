<script setup lang="ts">
import type { PricingRule, PupilRate, ServicesHome } from '~/composables/useServices'

useHead({ title: 'Pricing · OwnLane' })

const { fetchHome, createPricingRule, updatePricingRule, createPupilRate } = useServices()

const home = ref<ServicesHome | null>(null)
const loading = ref(true)
const error = ref('')
const saving = ref(false)

const showRuleForm = ref(false)
const ruleLabel = ref('')
const ruleDays = ref<number[]>([])
const ruleAfter = ref('')
const ruleBefore = ref('')
const ruleKind = ref<'add_pence' | 'percent' | 'set_pence'>('add_pence')
const ruleAmount = ref('')
const rulePercent = ref('25')

const showPupilForm = ref(false)
const pupilQuery = ref('')
const pupilResults = ref<Array<{ id: number; full_name: string }>>([])
const selectedPupil = ref<{ id: number; full_name: string } | null>(null)
const pupilServiceId = ref<number | null>(null)
const pupilPrice = ref('')
const pupilFrom = ref('')

const rules = computed(() => home.value?.pricing_rules ?? [])
const pupilRates = computed(() => home.value?.pupil_rates ?? [])
const services = computed(() => home.value?.services.filter(s => s.status === 'active') ?? [])
const weekdays = computed(() => home.value?.weekday_options ?? [])

async function load() {
  loading.value = true
  error.value = ''
  try {
    home.value = await fetchHome()
  } catch (e) {
    error.value = extractApiError(e, 'Could not load pricing.')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
  const today = new Date()
  pupilFrom.value = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
})

function toggleDay(day: number) {
  if (ruleDays.value.includes(day)) {
    ruleDays.value = ruleDays.value.filter(d => d !== day)
  } else {
    ruleDays.value = [...ruleDays.value, day].sort((a, b) => a - b)
  }
}

async function onSaveRule() {
  saving.value = true
  error.value = ''
  try {
    const payload: Record<string, unknown> = {
      label: ruleLabel.value.trim(),
      days: ruleDays.value,
      time_after: ruleAfter.value || null,
      time_before: ruleBefore.value || null,
      adjustment_kind: ruleKind.value,
    }
    if (ruleKind.value === 'percent') {
      payload.percent = Number(rulePercent.value)
    } else {
      payload.price = ruleAmount.value.trim()
    }
    await createPricingRule(payload)
    showRuleForm.value = false
    ruleLabel.value = ''
    ruleDays.value = []
    ruleAfter.value = ''
    ruleBefore.value = ''
    ruleAmount.value = ''
    await load()
  } catch (e) {
    error.value = extractApiError(e, 'Could not save rule.')
  } finally {
    saving.value = false
  }
}

async function toggleRule(rule: PricingRule) {
  try {
    await updatePricingRule(rule.id, { active: !rule.active })
    await load()
  } catch (e) {
    error.value = extractApiError(e, 'Could not update rule.')
  }
}

async function searchPupils() {
  const q = pupilQuery.value.trim()
  if (q.length < 2) {
    pupilResults.value = []
    return
  }
  try {
    const data = await apiFetch<{ items: Array<{ id: number; full_name: string }> }>(
      `/learners?q=${encodeURIComponent(q)}`,
    )
    pupilResults.value = data.items ?? []
  } catch {
    pupilResults.value = []
  }
}

async function onSavePupilRate() {
  if (!selectedPupil.value) return
  saving.value = true
  error.value = ''
  try {
    await createPupilRate({
      learner_id: selectedPupil.value.id,
      service_id: pupilServiceId.value,
      price: pupilPrice.value.trim(),
      effective_from: pupilFrom.value,
    })
    showPupilForm.value = false
    selectedPupil.value = null
    pupilQuery.value = ''
    pupilPrice.value = ''
    await load()
  } catch (e) {
    error.value = extractApiError(e, 'Could not save pupil rate.')
  } finally {
    saving.value = false
  }
}

function presetEvening() {
  ruleLabel.value = 'Evenings after 18:00'
  ruleDays.value = []
  ruleAfter.value = '18:00'
  ruleBefore.value = ''
  ruleKind.value = 'add_pence'
  ruleAmount.value = '8'
  showRuleForm.value = true
}

function presetSaturday() {
  ruleLabel.value = 'Saturday'
  ruleDays.value = [6]
  ruleAfter.value = ''
  ruleBefore.value = ''
  ruleKind.value = 'percent'
  rulePercent.value = '25'
  showRuleForm.value = true
}

function presetSunday() {
  ruleLabel.value = 'Sunday'
  ruleDays.value = [7]
  ruleAfter.value = ''
  ruleBefore.value = ''
  ruleKind.value = 'set_pence'
  ruleAmount.value = '55'
  showRuleForm.value = true
}
</script>

<template>
  <section class="pricing ol-page">
    <header class="pricing__header">
      <NuxtLink to="/services" class="ol-link-action">← Services</NuxtLink>
      <h1 class="ol-page-title">Pricing</h1>
      <p class="pricing__lead">
        Extra prices for evenings and weekends, plus rates for individual pupils.
      </p>
    </header>

    <p v-if="error" class="ol-error" role="alert">{{ error }}</p>
    <p v-else-if="loading" class="ol-muted">Loading…</p>

    <template v-else>
      <section class="block">
        <div class="block__head">
          <h2 class="block__title">Time & day prices</h2>
          <button class="ol-btn ol-btn--sm" type="button" @click="showRuleForm = !showRuleForm">
            {{ showRuleForm ? 'Cancel' : 'Add rule' }}
          </button>
        </div>

        <div v-if="!showRuleForm" class="presets">
          <button type="button" class="preset" @click="presetEvening">Evenings +£8</button>
          <button type="button" class="preset" @click="presetSaturday">Saturday +25%</button>
          <button type="button" class="preset" @click="presetSunday">Sunday £55</button>
        </div>

        <form v-if="showRuleForm" class="rule-form" @submit.prevent="onSaveRule">
          <label class="field">
            <span class="field__label">Name</span>
            <input v-model="ruleLabel" class="field__input" required placeholder="Evenings after 18:00">
          </label>
          <div class="block-inner">
            <p class="field__label">Days</p>
            <div class="ol-seg">
              <button
                v-for="day in weekdays"
                :key="day.value"
                class="ol-chip"
                type="button"
                :class="{ 'ol-chip--on': ruleDays.includes(day.value) }"
                @click="toggleDay(day.value)"
              >
                {{ day.short }}
              </button>
            </div>
            <p class="hint">Leave blank for every day.</p>
          </div>
          <div class="row">
            <label class="field">
              <span class="field__label">From</span>
              <input v-model="ruleAfter" class="field__input" placeholder="18:00">
            </label>
            <label class="field">
              <span class="field__label">Until</span>
              <input v-model="ruleBefore" class="field__input" placeholder="optional">
            </label>
          </div>
          <div class="block-inner">
            <p class="field__label">Change</p>
            <div class="ol-seg">
              <button class="ol-chip" type="button" :class="{ 'ol-chip--on': ruleKind === 'add_pence' }" @click="ruleKind = 'add_pence'">Add £</button>
              <button class="ol-chip" type="button" :class="{ 'ol-chip--on': ruleKind === 'percent' }" @click="ruleKind = 'percent'">Add %</button>
              <button class="ol-chip" type="button" :class="{ 'ol-chip--on': ruleKind === 'set_pence' }" @click="ruleKind = 'set_pence'">Set £</button>
            </div>
          </div>
          <label v-if="ruleKind === 'percent'" class="field">
            <span class="field__label">Percent</span>
            <input v-model="rulePercent" class="field__input" inputmode="numeric" required>
          </label>
          <label v-else class="field">
            <span class="field__label">Amount</span>
            <span class="price-wrap">
              <span aria-hidden="true">£</span>
              <input v-model="ruleAmount" class="field__input field__input--bare" inputmode="decimal" required>
            </span>
          </label>
          <button class="ol-btn" type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save rule' }}</button>
        </form>

        <ul v-if="rules.length" class="list">
          <li v-for="rule in rules" :key="rule.id" class="row-item" :data-off="rule.active ? 'no' : 'yes'">
            <div>
              <p class="row-item__title">{{ rule.label }}</p>
              <p class="row-item__meta">{{ rule.summary }}</p>
            </div>
            <button class="ol-chip" type="button" :class="{ 'ol-chip--on': rule.active }" @click="toggleRule(rule)">
              {{ rule.active ? 'On' : 'Off' }}
            </button>
          </li>
        </ul>
        <p v-else-if="!showRuleForm" class="hint">No special pricing yet.</p>
      </section>

      <section id="pupil-rates" class="block">
        <div class="block__head">
          <h2 class="block__title">Pupil rates</h2>
          <button class="ol-btn ol-btn--sm" type="button" @click="showPupilForm = !showPupilForm">
            {{ showPupilForm ? 'Cancel' : 'Add rate' }}
          </button>
        </div>

        <form v-if="showPupilForm" class="rule-form" @submit.prevent="onSavePupilRate">
          <label class="field">
            <span class="field__label">Pupil</span>
            <input
              v-model="pupilQuery"
              class="field__input"
              placeholder="Search by name"
              @input="searchPupils"
            >
          </label>
          <ul v-if="pupilResults.length && !selectedPupil" class="search-hits">
            <li v-for="p in pupilResults" :key="p.id">
              <button type="button" @click="selectedPupil = p; pupilQuery = p.full_name; pupilResults = []">
                {{ p.full_name }}
              </button>
            </li>
          </ul>
          <p v-if="selectedPupil" class="hint">Selected: {{ selectedPupil.full_name }}</p>
          <div class="block-inner">
            <p class="field__label">Applies to</p>
            <div class="ol-seg">
              <button class="ol-chip" type="button" :class="{ 'ol-chip--on': pupilServiceId === null }" @click="pupilServiceId = null">
                All lessons
              </button>
              <button
                v-for="s in services"
                :key="s.id"
                class="ol-chip"
                type="button"
                :class="{ 'ol-chip--on': pupilServiceId === s.id }"
                @click="pupilServiceId = s.id"
              >
                {{ s.name }}
              </button>
            </div>
          </div>
          <label class="field">
            <span class="field__label">Price</span>
            <span class="price-wrap">
              <span aria-hidden="true">£</span>
              <input v-model="pupilPrice" class="field__input field__input--bare" inputmode="decimal" required>
            </span>
          </label>
          <label class="field">
            <span class="field__label">From</span>
            <input v-model="pupilFrom" class="field__input" type="date" required>
          </label>
          <button class="ol-btn" type="submit" :disabled="saving || !selectedPupil">
            {{ saving ? 'Saving…' : 'Save pupil rate' }}
          </button>
        </form>

        <ul v-if="pupilRates.length" class="list">
          <li v-for="rate in pupilRates" :key="rate.id" class="row-item">
            <div>
              <p class="row-item__title">{{ rate.learner_name }}</p>
              <p class="row-item__meta">
                {{ rate.service_name }} · from {{ rate.effective_from }}
                <template v-if="rate.effective_to"> to {{ rate.effective_to }}</template>
              </p>
            </div>
            <p class="row-item__value">{{ rate.price_label }}</p>
          </li>
        </ul>
        <p v-else-if="!showPupilForm" class="hint">No pupil-specific rates yet.</p>
      </section>
    </template>
  </section>
</template>

<style scoped>
.pricing__header { margin-bottom: 28px; }
.pricing__lead { margin: 8px 0 0; max-width: 36rem; color: var(--color-muted); font-size: var(--text-body-sm); }
.block { margin-bottom: 36px; }
.block__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
.block__title { margin: 0; font-family: var(--font-haas-grot-disp); font-size: 1.25rem; font-weight: 600; }
.presets { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.preset { border: 1px solid var(--color-border); background: var(--color-paper-white); border-radius: 999px; padding: 8px 14px; font: inherit; font-size: var(--text-body-sm); font-weight: 500; cursor: pointer; }
.preset:hover { border-color: var(--color-driftwood); }
.rule-form { display: flex; flex-direction: column; gap: 14px; max-width: 32rem; margin-bottom: 18px; padding: 18px; border: 1px solid var(--color-border); border-radius: var(--radius-cards); background: var(--color-parchment); }
.field__label { display: block; margin-bottom: 8px; font-size: var(--text-meta); font-weight: 600; color: var(--color-coffee-stone); }
.field__input { width: 100%; min-height: 44px; padding: 10px 12px; border: 1px solid var(--color-driftwood); border-radius: 4px; background: var(--color-paper-white); font: inherit; }
.field__input:focus { outline: none; border-color: var(--color-ink-black); }
.price-wrap { display: flex; align-items: center; gap: 4px; min-height: 44px; padding: 0 12px; border: 1px solid var(--color-driftwood); border-radius: 4px; background: var(--color-paper-white); }
.price-wrap:focus-within { border-color: var(--color-ink-black); }
.field__input--bare { border: none; min-height: 42px; padding: 0; background: transparent; }
.field__input--bare:focus { outline: none; }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.hint { margin: 6px 0 0; color: var(--color-muted); font-size: var(--text-meta); }
.list { list-style: none; margin: 0; padding: 0; border: 1px solid var(--color-border); border-radius: var(--radius-cards); overflow: hidden; background: var(--color-paper-white); }
.row-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; }
.row-item + .row-item { border-top: 1px solid var(--color-border); }
.row-item[data-off='yes'] { opacity: 0.55; }
.row-item__title { margin: 0; font-weight: 600; }
.row-item__meta { margin: 3px 0 0; color: var(--color-muted); font-size: var(--text-meta); }
.row-item__value { margin: 0; font-weight: 600; }
.search-hits { list-style: none; margin: 0; padding: 0; border: 1px solid var(--color-border); border-radius: 4px; overflow: hidden; background: var(--color-paper-white); }
.search-hits button { width: 100%; text-align: left; padding: 10px 12px; border: none; background: transparent; font: inherit; cursor: pointer; }
.search-hits button:hover { background: var(--color-parchment); }
.search-hits li + li { border-top: 1px solid var(--color-border); }
</style>
