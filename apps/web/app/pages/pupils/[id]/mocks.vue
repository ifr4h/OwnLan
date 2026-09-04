<template>
  <section class="ol-page">
    <NuxtLink :to="`/pupils/${pupilId}`" class="ol-back">← Pupil</NuxtLink>

    <h1 class="ol-page-title">Mock tests</h1>

    <p v-if="loading" class="ol-muted">Loading…</p>
    <p v-else-if="error" class="ol-error" role="alert">{{ error }}</p>

    <template v-else>
      <section v-if="comparison" class="ol-panel compare">
        <h2 class="panel__title">Latest comparison</h2>
        <div class="compare__grid">
          <div />
          <p>{{ comparison.older.date_display }}</p>
          <p>{{ comparison.newer.date_display }}</p>
          <p>Driving faults</p>
          <p>{{ comparison.driving_faults.older }}</p>
          <p>{{ comparison.driving_faults.newer }}</p>
          <p>Serious</p>
          <p>{{ comparison.serious_faults.older }}</p>
          <p>{{ comparison.serious_faults.newer }}</p>
        </div>
      </section>

      <ul class="mock-list">
        <li v-for="mock in items" :key="mock.id" class="mock-list__item">
          <NuxtLink :to="`/mock-tests/${mock.id}/review`" class="mock-card">
            <p class="mock-card__date">{{ mock.date_display }}</p>
            <p class="mock-card__result" :data-result="mock.result">{{ mock.result_label || 'Abandoned' }}</p>
            <p class="mock-card__faults">
              {{ mock.driving_faults_count }} driving
              <span v-if="mock.serious_faults_count"> · {{ mock.serious_faults_count }} serious</span>
            </p>
            <p v-if="mock.elapsed_display" class="ol-meta">{{ mock.elapsed_display }}</p>
          </NuxtLink>
        </li>
      </ul>

      <p v-if="!items.length" class="ol-muted">No mock tests yet.</p>

      <button class="ol-btn" type="button" @click="onStartMock">Start mock</button>
    </template>
  </section>
</template>

<script setup lang="ts">
import { newClientSessionId, type MockTest } from '~/composables/useMockTest'

const route = useRoute()
const pupilId = computed(() => Number(route.params.id))
const { listForLearner, startForLearner } = useMockTest()

const items = ref<MockTest[]>([])
const comparison = ref<Record<string, unknown> | null>(null)
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  try {
    const data = await listForLearner(pupilId.value)
    items.value = data.items
    comparison.value = data.comparison as Record<string, unknown> | null
  } catch (e) {
    error.value = extractApiError(e, 'Could not load mocks.')
  } finally {
    loading.value = false
  }
}

async function onStartMock() {
  try {
    const mock = await startForLearner(pupilId.value, newClientSessionId())
    if (mock.lesson_id) {
      await navigateTo(`/lessons/${mock.lesson_id}/mock`)
    } else {
      await navigateTo(`/mock-tests/${mock.id}/review`)
    }
  } catch (e) {
    error.value = extractApiError(e, 'Could not start mock.')
  }
}

onMounted(() => { void load() })
</script>

<style scoped>
.mock-list {
  list-style: none;
  padding: 0;
  margin: var(--spacing-16) 0;
}

.mock-card {
  display: block;
  padding: var(--spacing-16);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-small);
  margin-bottom: var(--spacing-8);
  text-decoration: none;
  color: inherit;
}

.mock-card__result[data-result='pass_standard'] {
  color: var(--color-ownlane-green);
  font-weight: 600;
}

.compare__grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: var(--spacing-8);
  font-size: var(--text-body-sm);
}
</style>
