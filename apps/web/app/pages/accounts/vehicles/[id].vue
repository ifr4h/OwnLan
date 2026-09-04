<template>
  <section class="ol-page">
    <p v-if="error" class="ol-error">{{ error }}</p>
    <p v-else-if="loading" class="ol-muted">Loading…</p>
    <template v-else-if="vehicle">
      <NuxtLink to="/accounts/vehicles" class="ol-link-action">← Vehicles</NuxtLink>
      <header class="ol-page-header">
        <h1 class="ol-page-title">{{ vehicle.display_name }}</h1>
        <p class="ol-meta">{{ vehicle.registration }} · {{ vehicle.transmission_label }}</p>
      </header>

      <section class="ol-panel ol-stack">
        <h2 class="ol-section-title">This year</h2>
        <p v-if="vehicle.mileage_label" class="ol-meta">Mileage logged: {{ vehicle.mileage_label }}</p>
        <ul v-if="vehicle.expenses_by_category?.length" class="ol-list-divide">
          <li v-for="row in vehicle.expenses_by_category" :key="row.category" class="ol-row">
            <span>{{ row.label }}</span>
            <strong>{{ row.amount_label }}</strong>
          </li>
        </ul>
        <p v-else class="ol-meta">No vehicle expenses recorded this year.</p>
      </section>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { VehicleRow } from '~/composables/useBusinessFinance'

const route = useRoute()
const id = computed(() => Number(route.params.id))

const { fetchVehicleDetail } = useBusinessFinance()
const vehicle = ref<(VehicleRow & { expenses_by_category: Array<{ category: string; label: string; amount_label: string }>; mileage_label: string }) | null>(null)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    vehicle.value = await fetchVehicleDetail(id.value)
  } catch (e) {
    error.value = extractApiError(e, 'Could not load vehicle.')
  } finally {
    loading.value = false
  }
})
</script>
