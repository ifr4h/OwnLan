<template>
  <PortalPage>
    <template #header>
      <h1 class="portal-page__title">Payments</h1>
    </template>

    <template v-if="loading && !data">
      <PortalSkeleton variant="circle" />
      <PortalSkeleton variant="line" />
    </template>
    <p v-else-if="error" class="money__error" role="alert">{{ error }}</p>

    <PortalMainGrid v-else-if="data" variant="split">
      <section class="money__hero" aria-labelledby="to-pay">
        <h2 id="to-pay" class="money__section">To pay</h2>
        <p v-if="payments?.can_pay_balance" class="money__due-amount">{{ payments.amount_owed_label }}</p>
        <p v-else class="money__nothing-due">Nothing to pay right now.</p>
        <button
          v-if="payments?.can_pay_balance && payments.online_payments_available"
          class="ol-btn money__pay-btn"
          type="button"
          :disabled="paying"
          @click="onPayBalance"
        >
          {{ paying ? 'Starting…' : `Pay ${payments.amount_owed_label}` }}
        </button>
        <p v-else-if="payments?.can_pay_balance" class="money__due-hint">
          Your instructor has not turned on online payments yet.
        </p>

        <h2 class="money__section money__section--spaced">Package</h2>
        <PortalCreditRing
          :used-percent="data.credit.used_percent ?? 0"
          :remaining-label="hoursShort(data.credit.credit_hours ?? data.credit.credit_minutes / 60)"
          :remaining-hint="'remaining'"
          :aria-label="data.credit.credit_label"
        />
        <p class="money__credit-line">{{ data.credit.credit_label }}</p>
      </section>

      <div class="money__side ol-stack">
        <section v-if="payments?.package_offerings?.length" class="money__packages" aria-labelledby="packages">
          <h2 id="packages" class="money__section">Buy a package</h2>
          <ul class="packages">
            <li v-for="pkg in payments.package_offerings" :key="pkg.id" class="packages__row">
              <div>
                <p class="packages__label">{{ pkg.label }}</p>
                <p class="packages__meta">{{ pkg.hours_label }} · {{ pkg.price_label }}</p>
              </div>
              <button
                class="ol-btn ol-btn--ghost ol-btn--sm"
                type="button"
                :disabled="paying"
                @click="onBuyPackage(pkg.id)"
              >
                Buy
              </button>
            </li>
          </ul>
        </section>

        <section class="money__history" aria-labelledby="history">
          <h2 id="history" class="money__section">Payment history</h2>
          <p v-if="!history.length" class="money__empty">No payments yet.</p>
          <ul v-else class="history">
            <li v-for="item in history" :key="item.id" class="history__row">
              <div>
                <p class="history__amount">{{ item.amount_label }}</p>
                <p class="history__meta">{{ item.date_label }} · {{ item.method_label }}</p>
              </div>
              <span class="history__status" :class="`history__status--${item.status}`">
                {{ statusLabel(item.status) }}
              </span>
            </li>
          </ul>
        </section>

        <section v-if="data.activity.length" class="money__activity" aria-labelledby="activity">
          <h2 id="activity" class="money__section">Package activity</h2>
          <ul class="activity">
            <li v-for="item in data.activity" :key="item.id" class="activity__row">
              <div class="activity__main">
                <p class="activity__label">
                  {{ item.type === 'package_added' ? 'Package added' : 'Lesson' }}
                </p>
                <p class="activity__date">{{ item.date_label }}</p>
              </div>
              <p
                class="activity__delta"
                :class="{
                  'activity__delta--plus': item.minutes_delta > 0,
                  'activity__delta--minus': item.minutes_delta < 0,
                }"
              >
                {{ item.minutes_label }}
              </p>
            </li>
          </ul>
        </section>
      </div>
    </PortalMainGrid>

    <p v-if="payError" class="money__error" role="alert">{{ payError }}</p>
  </PortalPage>
</template>

<script setup lang="ts">
import type { PortalMoney } from '~/composables/usePortal'

definePageMeta({ layout: 'portal' })
useHead({ title: 'Payments · OwnLane' })

const { fetchMoney } = usePortal()
const { payOutstandingBalance, payPackage, redirectToCheckout } = useOnlinePayments()

const data = ref<PortalMoney | null>(null)
const loading = ref(true)
const error = ref('')
const paying = ref(false)
const payError = ref('')

const payments = computed(() => data.value?.payments)
const history = computed(() => payments.value?.payment_history ?? [])

function hoursShort(hours: number): string {
  if (!hours) return '0h'
  const rounded = Math.round(hours * 10) / 10
  return `${rounded}h`
}

function statusLabel(status: string): string {
  if (status === 'refunded') return 'Refunded'
  if (status === 'processing') return 'Processing'
  return 'Paid'
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await fetchMoney()
  } catch (e) {
    error.value = extractApiError(e, 'Could not load payments.')
  } finally {
    loading.value = false
  }
}

async function onPayBalance() {
  paying.value = true
  payError.value = ''
  try {
    const session = await payOutstandingBalance()
    redirectToCheckout(session)
  } catch (e) {
    payError.value = extractApiError(e, 'Could not start payment.')
    paying.value = false
  }
}

async function onBuyPackage(offeringId: number) {
  paying.value = true
  payError.value = ''
  try {
    const session = await payPackage(offeringId)
    redirectToCheckout(session)
  } catch (e) {
    payError.value = extractApiError(e, 'Could not start payment.')
    paying.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.money__error {
  color: var(--color-danger);
  font-size: var(--text-body-sm);
}

.money__hero {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
  padding: var(--spacing-24);
  background: linear-gradient(180deg, var(--color-frost-green) 0%, var(--color-chalk-green) 100%);
  border-radius: var(--radius-panel);
  border: 1px solid color-mix(in srgb, var(--color-ownlane-green) 10%, var(--color-border));
}

.money__due-amount {
  font-family: var(--font-martian-mono);
  font-size: var(--text-heading-lg);
  font-variant-numeric: tabular-nums;
}

.money__nothing-due {
  font-size: var(--text-body);
  color: var(--color-muted);
}

.money__pay-btn {
  align-self: flex-start;
}

.money__due-hint,
.money__credit-line,
.money__empty {
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.money__section {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
}

.money__section--spaced {
  margin-top: var(--spacing-16);
}

.packages,
.history,
.activity {
  list-style: none;
  margin: 0;
  padding: 0;
}

.packages__row,
.history__row,
.activity__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-16);
  min-height: 56px;
  padding: var(--spacing-8) 0;
  border-bottom: 1px solid var(--color-border);
}

.packages__label,
.history__amount,
.activity__label {
  font-size: var(--text-body-sm);
}

.packages__meta,
.history__meta,
.activity__date {
  font-size: var(--text-meta);
  color: var(--color-muted);
  margin-top: 2px;
}

.history__status {
  font-size: var(--text-meta);
  color: var(--color-ownlane-green);
}

.history__status--refunded {
  color: var(--color-muted);
}

.history__status--processing {
  color: var(--color-ink-black);
}

.activity__delta {
  font-family: var(--font-martian-mono);
  font-size: var(--text-body-sm);
  font-variant-numeric: tabular-nums;
}

.activity__delta--plus {
  color: var(--color-ownlane-green);
}

.activity__delta--minus {
  color: var(--color-ink-black);
}
</style>
