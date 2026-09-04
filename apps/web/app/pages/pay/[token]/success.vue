<template>
  <main class="guest-pay ol-page">
    <p v-if="loading" class="ol-muted" role="status">Checking payment…</p>
    <p v-else-if="error" class="ol-error" role="alert">{{ error }}</p>

    <section v-else-if="done" class="guest-pay__card ol-panel ol-stack">
      <h1 class="ol-page-title">Payment received</h1>
      <p v-if="amountLabel" class="guest-pay__amount">{{ amountLabel }}</p>
      <p v-if="description" class="ol-meta">{{ description }}</p>
      <p class="ol-meta">You can close this page.</p>
    </section>
  </main>
</template>

<script setup lang="ts">
definePageMeta({ layout: false })
useHead({ title: 'Payment received · OwnLane' })

const route = useRoute()
const token = computed(() => String(route.params.token || ''))
const { confirmGuestPayment } = useOnlinePayments()

const loading = ref(true)
const error = ref('')
const done = ref(false)
const amountLabel = ref('')
const description = ref('')

async function reconcile() {
  try {
    let attempts = 0
    while (attempts < 8) {
      const result = await confirmGuestPayment(token.value)
      if (result.status === 'succeeded' || result.status === 'fulfilled' || result.status === 'already_fulfilled') {
        const checkout = result.checkout as { amount_label?: string; description?: string } | undefined
        amountLabel.value = checkout?.amount_label || ''
        description.value = checkout?.description || ''
        done.value = true
        return
      }
      if (result.status === 'failed') {
        error.value = result.message || 'Payment didn\'t go through.'
        await navigateTo(`/pay/${token.value}`)
        return
      }
      attempts += 1
      await new Promise((r) => setTimeout(r, 1500))
    }
    done.value = true
  } catch (e) {
    error.value = extractApiError(e, 'Could not confirm payment.')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void reconcile()
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

.guest-pay__amount {
  font-family: var(--font-martian-mono);
  font-size: var(--text-heading-lg);
}
</style>
