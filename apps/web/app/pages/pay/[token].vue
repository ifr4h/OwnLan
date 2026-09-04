<template>
  <main class="guest-pay ol-page">
    <p v-if="loading" class="ol-muted">Loading…</p>
    <p v-else-if="error" class="ol-error" role="alert">{{ error }}</p>

    <section v-else-if="view" class="guest-pay__card ol-panel ol-stack">
      <p class="ol-eyebrow">{{ view.business_name }}</p>
      <h1 class="ol-page-title">{{ view.amount_label }} due</h1>
      <p class="ol-meta">{{ view.description }}</p>
      <button class="ol-btn" type="button" :disabled="paying" @click="onPay">
        {{ paying ? 'Starting…' : `Pay ${view.amount_label}` }}
      </button>
      <p v-if="payError" class="ol-error" role="alert">{{ payError }}</p>
    </section>
  </main>
</template>

<script setup lang="ts">
import type { GuestPaymentView } from '~/composables/useOnlinePayments'

definePageMeta({ layout: false })
useHead({ title: 'Pay · OwnLane' })

const route = useRoute()
const token = computed(() => String(route.params.token || ''))
const { fetchGuestPayment, startGuestPayment, redirectToCheckout } = useOnlinePayments()

const view = ref<GuestPaymentView | null>(null)
const loading = ref(true)
const error = ref('')
const paying = ref(false)
const payError = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    view.value = await fetchGuestPayment(token.value)
  } catch (e) {
    error.value = extractApiError(e, 'This payment link is not valid.')
  } finally {
    loading.value = false
  }
}

async function onPay() {
  paying.value = true
  payError.value = ''
  try {
    const session = await startGuestPayment(token.value)
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
.guest-pay {
  max-width: 28rem;
  margin: 0 auto;
  padding: var(--spacing-24) var(--spacing-16);
}

.guest-pay__card {
  margin-top: var(--spacing-24);
}
</style>
