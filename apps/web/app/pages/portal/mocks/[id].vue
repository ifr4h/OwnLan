<template>
  <PortalPage>
    <template #header>
      <NuxtLink to="/portal/mocks" class="portal-back">← Mocks</NuxtLink>
      <h1 class="portal-page__title">Mock test</h1>
    </template>

    <p v-if="loading" class="ol-muted">Loading…</p>
    <p v-else-if="error" class="progress__error" role="alert">{{ error }}</p>

    <template v-else-if="mock">
      <p class="mock-meta">{{ mock.date_display }} · {{ mock.elapsed_display }}</p>
      <p class="mock-result" :data-result="mock.result">{{ mock.result_label }}</p>

      <PortalMetricStrip>
        <PortalMetric :value="mock.driving_faults_count" label="Driving faults" compact />
        <PortalMetric :value="mock.serious_faults_count" label="Serious" compact />
        <PortalMetric :value="mock.dangerous_faults_count" label="Dangerous" compact />
      </PortalMetricStrip>

      <section v-if="mock.instructor_note" class="portal-panel">
        <h2 class="portal-panel__title">From your instructor</h2>
        <p>{{ mock.instructor_note }}</p>
      </section>

      <section v-if="mock.suggested_next_focus" class="portal-panel">
        <h2 class="portal-panel__title">Next focus</h2>
        <p>{{ mock.suggested_next_focus }}</p>
      </section>

      <section v-for="group in mock.fault_summary" :key="group.area" class="portal-panel">
        <h2 class="portal-panel__title">{{ group.area }}</h2>
        <ul>
          <li v-for="item in group.items" :key="item.label">
            {{ item.label }}
            <span class="ol-meta">
              <template v-if="item.driving">{{ item.driving }} driving</template>
              <template v-if="item.serious"> · {{ item.serious }} serious</template>
            </span>
          </li>
        </ul>
      </section>
    </template>
  </PortalPage>
</template>

<script setup lang="ts">
import type { MockTest } from '~/composables/useMockTest'

definePageMeta({ layout: 'portal' })

const route = useRoute()
const id = computed(() => Number(route.params.id))
const mock = ref<MockTest | null>(null)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    mock.value = await apiFetch<MockTest>(`/portal/mocks/${id.value}`)
  } catch (e) {
    error.value = extractApiError(e, 'Could not load mock.')
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.mock-result {
  font-weight: 600;
  font-size: var(--text-body-lg);
  margin-bottom: var(--spacing-16);
}

.mock-result[data-result='pass_standard'] {
  color: var(--color-ownlane-green);
}
</style>
