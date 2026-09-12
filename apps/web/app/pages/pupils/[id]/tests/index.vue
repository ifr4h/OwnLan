<template>
  <section class="ol-page">
    <NuxtLink :to="`/pupils/${pupilId}`" class="ol-back">← Pupil</NuxtLink>
    <header class="ol-page-header head">
      <div>
        <h1 class="ol-page-title">Practical tests</h1>
        <p class="ol-meta">Official DVSA results for this pupil</p>
      </div>
      <NuxtLink :to="`/pupils/${pupilId}/tests/new`" class="ol-btn">Log test result</NuxtLink>
    </header>

    <p v-if="loading" class="ol-muted">Loading…</p>
    <p v-else-if="error" class="ol-error" role="alert">{{ error }}</p>

    <template v-else>
      <ul v-if="items.length" class="list">
        <li v-for="t in items" :key="t.id">
          <NuxtLink :to="`/practical-tests/${t.id}`" class="card">
            <div>
              <p class="card__date">{{ t.date_display }}</p>
              <p class="ol-meta">{{ t.test_centre || 'Centre not set' }}</p>
            </div>
            <div class="card__right">
              <span class="badge" :data-result="t.result">{{ t.result_label }}</span>
              <p class="ol-meta">
                {{ t.driving_faults_count }} DF
                <span v-if="t.serious_faults_count"> · {{ t.serious_faults_count }} S</span>
                <span v-if="t.dangerous_faults_count"> · {{ t.dangerous_faults_count }} D</span>
              </p>
            </div>
          </NuxtLink>
        </li>
      </ul>
      <p v-else class="ol-muted">
        No practical test results yet. Log the sheet after each test and it feeds your rolling fault trends.
      </p>
      <p class="footer-link">
        <NuxtLink to="/accounts/test-faults" class="ol-link-action">View school fault trends</NuxtLink>
      </p>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { PracticalTest } from '~/composables/usePracticalTests'

const route = useRoute()
const pupilId = computed(() => Number(route.params.id))
const { listForLearner } = usePracticalTests()

useHead({ title: 'Practical tests · OwnLane' })

const items = ref<PracticalTest[]>([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const data = await listForLearner(pupilId.value)
    items.value = data.items
  } catch (e) {
    error.value = extractApiError(e, 'Could not load practical tests.')
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.head {
  display: flex;
  justify-content: space-between;
  gap: var(--spacing-12);
  align-items: flex-start;
  flex-wrap: wrap;
}

.list {
  list-style: none;
  margin: var(--spacing-16) 0 0;
  padding: 0;
}

.card {
  display: flex;
  justify-content: space-between;
  gap: var(--spacing-12);
  padding: var(--spacing-16);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-small);
  margin-bottom: var(--spacing-8);
  text-decoration: none;
  color: inherit;
  background: var(--color-paper-white);
}

.card__date {
  margin: 0;
  font: 600 15px/1.3 var(--font-haas-grot-text);
}

.card__right {
  text-align: right;
}

.badge {
  display: inline-flex;
  padding: 2px 10px;
  border-radius: 999px;
  font: 600 12px/1.4 var(--font-haas-grot-text);
}

.badge[data-result='pass'] {
  background: var(--color-success-wash);
  color: var(--color-success);
}

.badge[data-result='fail'] {
  background: var(--color-danger-wash);
  color: var(--color-danger);
}

.footer-link {
  margin-top: var(--spacing-16);
}
</style>
