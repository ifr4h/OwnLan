<template>
  <PortalPage>
    <template #header>
      <h1 class="portal-page__title">Your mocks</h1>
    </template>

    <p v-if="loading" class="ol-muted">Loading…</p>
    <p v-else-if="error" class="progress__error" role="alert">{{ error }}</p>

    <template v-else>
      <ul class="mock-list">
        <li v-for="mock in items" :key="mock.id">
          <NuxtLink :to="`/portal/mocks/${mock.id}`" class="mock-card">
            <p class="mock-card__date">{{ mock.date_display }}</p>
            <p class="mock-card__result" :data-result="mock.result">{{ mock.result_label }}</p>
            <p class="mock-card__faults">
              {{ mock.driving_faults_count }} driving faults
              <span v-if="mock.serious_faults_count"> · {{ mock.serious_faults_count }} serious</span>
            </p>
          </NuxtLink>
        </li>
      </ul>
      <p v-if="!items.length" class="ol-muted">No mock tests yet.</p>
    </template>
  </PortalPage>
</template>

<script setup lang="ts">
import type { MockTest } from '~/composables/useMockTest'

definePageMeta({ layout: 'portal' })
useHead({ title: 'Your mocks · OwnLane' })

const items = ref<MockTest[]>([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const data = await apiFetch<{ items: MockTest[] }>('/portal/mocks')
    items.value = data.items
  } catch (e) {
    error.value = extractApiError(e, 'Could not load mocks.')
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.mock-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.mock-card {
  display: block;
  padding: var(--spacing-16);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-small);
  margin-bottom: var(--spacing-12);
  text-decoration: none;
  color: inherit;
}

.mock-card__result[data-result='pass_standard'] {
  color: var(--color-ownlane-green);
  font-weight: 600;
}
</style>
