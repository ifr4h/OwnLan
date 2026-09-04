<template>
  <section class="ol-page">
    <header class="ol-page-header">
      <p class="ol-eyebrow">Accounts</p>
      <h1 class="ol-page-title">Payments</h1>
    </header>
    <AccountsNav />
    <p v-if="error" class="ol-error">{{ error }}</p>
    <p v-else-if="loading" class="ol-muted">Loading…</p>
    <template v-else>
      <section v-if="accountSettings" class="ol-panel ol-stack online-setup">
        <h2 class="ol-section-title">Online payments</h2>
        <p class="ol-meta">
          Accept card, Apple Pay and Google Pay. Money is paid to your connected Stripe account.
        </p>
        <p v-if="accountSettings.fee_notice" class="ol-meta">{{ accountSettings.fee_notice }}</p>
        <p v-if="accountSettings.account" class="ol-meta">
          Status: {{ accountSettings.account.status_label }}
        </p>
        <div class="ol-actions">
          <button
            v-if="!accountSettings.account?.ready"
            class="ol-btn"
            type="button"
            :disabled="accountBusy"
            @click="onSetupPayments"
          >
            {{ accountBusy ? 'Working…' : 'Set up payments' }}
          </button>
          <button
            v-else
            class="ol-btn ol-btn--ghost"
            type="button"
            :disabled="accountBusy"
            @click="onRefreshAccount"
          >
            Refresh status
          </button>
        </div>
        <fieldset v-if="accountSettings.account?.ready" class="booking-policy">
          <legend class="ol-meta">When a pupil books online</legend>
          <label
            v-for="opt in accountSettings.booking_payment_options"
            :key="opt.value"
            class="booking-policy__option"
          >
            <input
              v-model="bookingPolicy"
              type="radio"
              name="booking_policy"
              :value="opt.value"
              @change="onPolicyChange"
            >
            {{ opt.label }}
          </label>
        </fieldset>
        <p v-if="accountError" class="ol-error">{{ accountError }}</p>
      </section>

      <template v-if="overview">
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
          <a class="ol-btn ol-btn--ghost ol-btn--sm" :href="exportHref" download>Export payments</a>
        </template>
      </AccountsPeriodFilter>

      <section class="ol-panel ol-stack">
        <p class="ol-eyebrow">Payments received</p>
        <p class="ol-metric">{{ overview.money_received_label }}</p>
        <p class="ol-meta">{{ overview.income_count }} in this period</p>
        <ul v-if="overview.payments_received_breakdown?.length" class="breakdown">
          <li v-for="row in overview.payments_received_breakdown" :key="row.method">
            {{ row.label }} · {{ row.amount_label }}
          </li>
        </ul>
      </section>

      <section class="ol-panel ol-stack">
        <h2 class="ol-section-title">Who owes you</h2>
        <ul v-if="overview.who_owes.length" class="ol-list-divide">
          <li v-for="row in overview.who_owes" :key="row.learner_id" class="ol-row">
            <div class="ol-row__main">
              <p class="ol-row__title">{{ row.learner_name }}</p>
              <p class="ol-row__meta">{{ row.amount_owed_label }}</p>
            </div>
            <div class="ol-row__actions">
              <button
                v-if="accountSettings?.account?.ready"
                class="ol-btn ol-btn--ghost ol-btn--sm"
                type="button"
                :disabled="requestingId === row.learner_id"
                @click="onRequestPayment(row.learner_id)"
              >
                Request payment
              </button>
              <NuxtLink :to="row.cta_path" class="ol-btn ol-btn--ghost ol-btn--sm">Record payment</NuxtLink>
            </div>
          </li>
        </ul>
        <p v-else class="ol-meta">Nobody owes you right now.</p>
      </section>

      <section class="ol-panel ol-stack">
        <h2 class="ol-section-title">Recent payments</h2>
        <ul v-if="overview.recent_income.length" class="ol-list-divide">
          <li v-for="item in overview.recent_income" :key="item.id" class="ol-row">
            <div class="ol-row__main">
              <p class="ol-row__title">{{ item.learner_name || 'Pupil' }} · {{ item.amount_label }}</p>
              <p class="ol-row__meta">{{ item.recorded_at_display }} · {{ methodLabel(item.method) }}</p>
            </div>
            <NuxtLink :to="`/pupils/${item.learner_id}`" class="ol-link-action">View</NuxtLink>
          </li>
        </ul>
        <p v-else class="ol-meta">No payments in this period.</p>
      </section>
      </template>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { BusinessOverview } from '~/composables/useBusinessFinance'
import type { PaymentAccountSettings } from '~/composables/useOnlinePayments'

useHead({ title: 'Payments · Accounts · OwnLane' })

const route = useRoute()
const { fetchOverview, exportUrl } = useBusinessFinance()
const {
  fetchAccountSettings,
  startOnboarding,
  refreshAccount,
  updateBookingPolicy,
  requestPayment,
} = useOnlinePayments()
const { from, to, applyPreset, initFromRoute } = useAccountsPeriod()

const overview = ref<BusinessOverview | null>(null)
const accountSettings = ref<PaymentAccountSettings | null>(null)
const bookingPolicy = ref('none')
const loading = ref(true)
const error = ref('')
const accountBusy = ref(false)
const accountError = ref('')
const requestingId = ref<number | null>(null)
const paymentLink = ref('')

const exportHref = computed(() =>
  from.value && to.value ? exportUrl(from.value, to.value, 'payments') : '#',
)

function methodLabel(method: string): string {
  if (method === 'card') return 'Online'
  if (method === 'cash') return 'Cash'
  if (method === 'bank_transfer') return 'Bank transfer'
  return 'Other'
}

async function loadAccount() {
  try {
    accountSettings.value = await fetchAccountSettings()
    bookingPolicy.value = accountSettings.value.booking_payment_policy
    if (route.query.onboarding) {
      await onRefreshAccount()
    }
  } catch {
    accountSettings.value = null
  }
}

async function onSetupPayments() {
  accountBusy.value = true
  accountError.value = ''
  try {
    const result = await startOnboarding()
    if (result.onboarding_url) {
      window.location.href = result.onboarding_url
      return
    }
    await loadAccount()
  } catch (e) {
    accountError.value = extractApiError(e, 'Could not start payment setup.')
  } finally {
    accountBusy.value = false
  }
}

async function onRefreshAccount() {
  accountBusy.value = true
  accountError.value = ''
  try {
    await refreshAccount()
    await loadAccount()
  } catch (e) {
    accountError.value = extractApiError(e, 'Could not refresh payment account.')
  } finally {
    accountBusy.value = false
  }
}

async function onPolicyChange() {
  try {
    accountSettings.value = await updateBookingPolicy(bookingPolicy.value)
  } catch (e) {
    accountError.value = extractApiError(e, 'Could not save booking policy.')
  }
}

async function onRequestPayment(learnerId: number) {
  requestingId.value = learnerId
  accountError.value = ''
  try {
    const result = await requestPayment(learnerId)
    if (result.payment_url) {
      await navigator.clipboard.writeText(result.payment_url)
      paymentLink.value = result.payment_url
    }
  } catch (e) {
    accountError.value = extractApiError(e, 'Could not create payment request.')
  } finally {
    requestingId.value = null
  }
}

async function reload() {
  loading.value = true
  error.value = ''
  try {
    overview.value = await fetchOverview(from.value || undefined, to.value || undefined)
    if (!from.value) from.value = overview.value.from
    if (!to.value) to.value = overview.value.to
  } catch (e) {
    error.value = extractApiError(e, 'Could not load payments.')
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  initFromRoute()
  await Promise.all([reload(), loadAccount()])
})
</script>

<style scoped>
.online-setup {
  margin-bottom: var(--spacing-16);
}

.booking-policy {
  border: 0;
  margin: 0;
  padding: 0;
}

.booking-policy__option {
  display: flex;
  gap: var(--spacing-8);
  align-items: flex-start;
  margin-top: var(--spacing-8);
  font-size: var(--text-body-sm);
}

.breakdown {
  list-style: none;
  margin: var(--spacing-8) 0 0;
  padding: 0;
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.ol-row__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
}
</style>
