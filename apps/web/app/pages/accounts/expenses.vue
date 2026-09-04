<template>
  <section class="ol-page">
    <header class="ol-page-header">
      <p class="ol-eyebrow">Accounts</p>
      <h1 class="ol-page-title">Expenses</h1>
    </header>
    <AccountsNav />
    <p v-if="error" class="ol-error">{{ error }}</p>
    <p v-else-if="loading" class="ol-muted">Loading…</p>
    <template v-else-if="overview">
      <AccountsPeriodFilter
        :presets="overview.presets"
        :from="from"
        :to="to"
        :range-label="overview.range_label"
        @apply-preset="applyPreset"
        @update:from="from = $event"
        @update:to="to = $event"
        @reload="reload"
      >
        <template #actions>
          <button class="ol-btn ol-btn--sm" type="button" @click="showForm = !showForm">
            {{ showForm ? 'Cancel' : 'Add expense' }}
          </button>
        </template>
      </AccountsPeriodFilter>

      <form v-if="showForm" class="ol-panel expense-form ol-stack" @submit.prevent="onCreate">
        <label class="ol-field">
          <span class="ol-field__label">Amount (£)</span>
          <input v-model="amount" class="ol-input" inputmode="decimal" required>
        </label>
        <label class="ol-field">
          <span class="ol-field__label">Category</span>
          <select v-model="category" class="ol-select" required>
            <option v-for="cat in overview.categories" :key="cat.value" :value="cat.value">{{ cat.label }}</option>
          </select>
        </label>
        <label class="ol-field">
          <span class="ol-field__label">Supplier</span>
          <input v-model="supplier" class="ol-input" placeholder="Shell">
        </label>
        <label class="ol-field">
          <span class="ol-field__label">Date</span>
          <input v-model="spentOn" class="ol-input ol-input--date" type="date" required>
        </label>
        <label v-if="showVehicleField" class="ol-field">
          <span class="ol-field__label">Vehicle</span>
          <select v-model="vehicleId" class="ol-select">
            <option value="">None</option>
            <option v-for="v in overview.vehicles" :key="v.value" :value="v.value">{{ v.label }}</option>
          </select>
        </label>
        <label class="ol-field">
          <span class="ol-field__label">Notes</span>
          <input v-model="notes" class="ol-input">
        </label>
        <button class="ol-btn" type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save expense' }}</button>
        <p v-if="formError" class="ol-error">{{ formError }}</p>
      </form>

      <section class="ol-panel">
        <p class="ol-metric">{{ overview.spending_label }}</p>
        <p class="ol-meta">{{ overview.expense_count }} entries in this period</p>
      </section>

      <ul v-if="overview.expenses.length" class="ol-list-divide">
        <li v-for="item in overview.expenses" :key="item.id" class="ol-row expense-row">
          <div class="ol-row__main">
            <p class="ol-row__title">
              {{ item.category_label }} · {{ item.amount_label }}
              <span v-if="item.supplier"> · {{ item.supplier }}</span>
            </p>
            <p class="ol-row__meta">
              {{ item.spent_on_display }}
              <template v-if="item.notes"> · {{ item.notes }}</template>
            </p>
          </div>
          <div class="expense-row__actions">
            <label v-if="!item.has_receipt" class="ol-btn ol-btn--ghost ol-btn--sm receipt-btn">
              Add receipt
              <input type="file" accept="image/*,.pdf" hidden @change="onReceipt($event, item.id)">
            </label>
            <a
              v-else
              class="ol-link-action"
              :href="receiptUrl(item.id)"
              target="_blank"
              rel="noopener"
            >
              Receipt
            </a>
            <button class="ol-link-action" type="button" @click="onVoid(item.id)">Void</button>
          </div>
        </li>
      </ul>
      <p v-else class="ol-meta">No expenses in this period.</p>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { BusinessOverview } from '~/composables/useBusinessFinance'
import { poundsInputToPence } from '~/composables/useFinance'

useHead({ title: 'Expenses · Accounts · OwnLane' })

const { fetchOverview, createExpense, voidExpense, uploadReceipt, receiptUrl } = useBusinessFinance()
const { from, to, applyPreset, initFromRoute } = useAccountsPeriod()

const overview = ref<BusinessOverview | null>(null)
const loading = ref(true)
const error = ref('')
const showForm = ref(false)
const saving = ref(false)
const formError = ref('')
const amount = ref('')
const category = ref('fuel')
const supplier = ref('')
const spentOn = ref('')
const vehicleId = ref<number | ''>('')
const notes = ref('')

const vehicleCategories = ['fuel', 'insurance', 'vehicle_repairs', 'servicing', 'tyres', 'parking', 'tolls']
const showVehicleField = computed(() => vehicleCategories.includes(category.value))

async function reload() {
  loading.value = true
  error.value = ''
  try {
    overview.value = await fetchOverview(from.value || undefined, to.value || undefined)
    if (!from.value) from.value = overview.value.from
    if (!to.value) to.value = overview.value.to
    if (!spentOn.value) spentOn.value = new Date().toISOString().slice(0, 10)
  } catch (e) {
    error.value = extractApiError(e, 'Could not load expenses.')
  } finally {
    loading.value = false
  }
}

async function onCreate() {
  formError.value = ''
  const pence = poundsInputToPence(amount.value)
  if (pence === null || pence <= 0) {
    formError.value = 'Enter an amount like 45.00'
    return
  }
  saving.value = true
  try {
    await createExpense({
      amount_pence: pence,
      category: category.value,
      spent_on: spentOn.value,
      supplier: supplier.value || undefined,
      vehicle_id: vehicleId.value ? Number(vehicleId.value) : undefined,
      notes: notes.value || undefined,
    })
    showForm.value = false
    amount.value = ''
    supplier.value = ''
    notes.value = ''
    await reload()
  } catch (e) {
    formError.value = extractApiError(e, 'Could not save expense.')
  } finally {
    saving.value = false
  }
}

async function onVoid(id: number) {
  const reason = window.prompt('Why void this expense?')
  if (!reason?.trim()) return
  try {
    await voidExpense(id, reason.trim())
    await reload()
  } catch (e) {
    error.value = extractApiError(e, 'Could not void expense.')
  }
}

async function onReceipt(e: Event, expenseId: number) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  try {
    await uploadReceipt(expenseId, file)
    await reload()
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Could not upload receipt.'
  }
}

onMounted(async () => {
  initFromRoute()
  await reload()
})
</script>

<style scoped>
.expense-form {
  margin-bottom: var(--spacing-16);
}

.expense-row {
  flex-wrap: wrap;
}

.expense-row__actions {
  display: flex;
  gap: var(--spacing-8);
  align-items: center;
}

.receipt-btn {
  cursor: pointer;
}
</style>
