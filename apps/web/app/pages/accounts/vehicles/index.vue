<template>
  <section class="ol-page">
    <header class="ol-page-header">
      <p class="ol-eyebrow">Accounts</p>
      <h1 class="ol-page-title">Vehicles</h1>
    </header>
    <AccountsNav />
    <p v-if="error" class="ol-error">{{ error }}</p>
    <p v-else-if="loading" class="ol-muted">Loading…</p>
    <template v-else>
      <div class="panel-head">
        <p class="ol-meta">Your teaching cars</p>
        <button class="ol-btn ol-btn--sm" type="button" @click="showForm = !showForm">
          {{ showForm ? 'Cancel' : 'Add vehicle' }}
        </button>
      </div>

      <form v-if="showForm" class="ol-panel ol-stack" @submit.prevent="onCreate">
        <label class="ol-field">
          <span class="ol-field__label">Registration</span>
          <input v-model="registration" class="ol-input" placeholder="AB23 XYZ" required>
        </label>
        <label class="ol-field">
          <span class="ol-field__label">Make</span>
          <input v-model="make" class="ol-input" placeholder="Toyota">
        </label>
        <label class="ol-field">
          <span class="ol-field__label">Model</span>
          <input v-model="model" class="ol-input" placeholder="Corolla">
        </label>
        <label class="ol-field">
          <span class="ol-field__label">Transmission</span>
          <select v-model="transmission" class="ol-select">
            <option value="manual">Manual</option>
            <option value="automatic">Automatic</option>
          </select>
        </label>
        <label class="ol-check">
          <input v-model="isPrimary" type="checkbox">
          Primary car
        </label>
        <button class="ol-btn" type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save vehicle' }}</button>
        <p v-if="formError" class="ol-error">{{ formError }}</p>
      </form>

      <ul v-if="vehicles.length" class="vehicle-list">
        <li v-for="v in vehicles" :key="v.id" class="ol-panel vehicle-card">
          <div>
            <p class="vehicle-card__name">{{ v.display_name.toUpperCase() }}</p>
            <p class="vehicle-card__reg">{{ v.registration }}</p>
            <p class="ol-meta">
              {{ v.transmission_label }}
              <span v-if="v.is_primary"> · Primary car</span>
            </p>
          </div>
          <NuxtLink :to="`/accounts/vehicles/${v.id}`" class="ol-btn ol-btn--ghost ol-btn--sm">View</NuxtLink>
        </li>
      </ul>
      <p v-else class="ol-meta">No vehicles added yet.</p>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { VehicleRow } from '~/composables/useBusinessFinance'

useHead({ title: 'Vehicles · Accounts · OwnLane' })

const { fetchVehicles, createVehicle } = useBusinessFinance()

const vehicles = ref<VehicleRow[]>([])
const loading = ref(true)
const error = ref('')
const showForm = ref(false)
const saving = ref(false)
const formError = ref('')
const registration = ref('')
const make = ref('')
const model = ref('')
const transmission = ref('manual')
const isPrimary = ref(true)

async function reload() {
  loading.value = true
  error.value = ''
  try {
    vehicles.value = await fetchVehicles()
  } catch (e) {
    error.value = extractApiError(e, 'Could not load vehicles.')
  } finally {
    loading.value = false
  }
}

async function onCreate() {
  formError.value = ''
  saving.value = true
  try {
    await createVehicle({
      registration: registration.value,
      make: make.value || undefined,
      model: model.value || undefined,
      transmission: transmission.value,
      is_primary: isPrimary.value,
    })
    showForm.value = false
    registration.value = ''
    make.value = ''
    model.value = ''
    await reload()
  } catch (e) {
    formError.value = extractApiError(e, 'Could not save vehicle.')
  } finally {
    saving.value = false
  }
}

onMounted(reload)
</script>

<style scoped>
.panel-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: var(--spacing-16);
}

.vehicle-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.vehicle-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--spacing-12);
}

.vehicle-card__name {
  font-weight: 700;
  margin: 0;
}

.vehicle-card__reg {
  font-size: var(--text-body-lg);
  margin: 4px 0;
}
</style>
