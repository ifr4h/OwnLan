<template>
  <PortalPage>
    <template #header>
      <h1 class="portal-page__title">{{ title }}</h1>
    </template>

    <p v-if="loading" class="ol-muted" role="status">Checking payment…</p>
    <p v-else-if="error" class="ol-error" role="alert">{{ error }}</p>

    <div v-else-if="done" class="success ol-stack">
      <p class="success__amount">{{ amountLabel }}</p>
      <p v-if="description" class="success__desc">{{ description }}</p>
      <p class="ol-meta">Your balance will update shortly if it has not already.</p>
      <NuxtLink class="ol-btn" to="/portal/money">Done</NuxtLink>
    </div>

    <p v-else class="ol-muted" role="status">Checking payment…</p>
  </PortalPage>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'portal' })
useHead({ title: 'Payment received · OwnLane' })

const route = useRoute()
const token = computed(() => String(route.params.token || ''))
const { confirmPortalPayment } = useOnlinePayments()

const loading = ref(true)
const error = ref('')
const done = ref(false)
const title = ref('Payment received')
const amountLabel = ref('')
const description = ref('')

async function reconcile() {
  loading.value = true
  error.value = ''
  try {
    let attempts = 0
    while (attempts < 8) {
      const result = await confirmPortalPayment(token.value)
      if (result.status === 'succeeded' || result.status === 'fulfilled' || result.status === 'already_fulfilled') {
        const checkout = result.checkout as { amount_label?: string; description?: string } | undefined
        amountLabel.value = checkout?.amount_label || ''
        description.value = checkout?.description || ''
        done.value = true
        return
      }
      if (result.status === 'failed') {
        error.value = result.message || 'Payment didn\'t go through.'
        await navigateTo(`/portal/pay/${token.value}`)
        return
      }
      attempts += 1
      await new Promise((r) => setTimeout(r, 1500))
    }
    title.value = 'Payment processing'
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
.success__amount {
  font-family: var(--font-martian-mono);
  font-size: var(--text-heading-lg);
  font-variant-numeric: tabular-nums;
}

.success__desc {
  font-size: var(--text-body);
}
</style>
