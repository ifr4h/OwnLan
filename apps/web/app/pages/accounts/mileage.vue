<template>
  <section class="ol-page">
    <header class="ol-page-header">
      <p class="ol-eyebrow">Accounts</p>
      <h1 class="ol-page-title">Mileage</h1>
    </header>
    <AccountsNav />
    <p v-if="error" class="ol-error">{{ error }}</p>
    <p v-else-if="loading" class="ol-muted">Loading…</p>
    <template v-else>
      <button class="ol-btn ol-btn--sm" type="button" @click="showForm = !showForm">
        {{ showForm ? 'Cancel' : 'Add mileage' }}
      </button>

      <form v-if="showForm" class="ol-panel ol-stack" @submit.prevent="onCreate">
        <label class="ol-field">
          <span class="ol-field__label">Vehicle</span>
          <select v-model="vehicleId" class="ol-select" required>
            <option v-for="v in vehicles" :key="v.id" :value="v.id">
              {{ v.registration }} · {{ v.display_name }}
            </option>
          </select>
        </label>
        <label class="ol-field">
          <span class="ol-field__label">Date</span>
          <input v-model="loggedOn" class="ol-input ol-input--date" type="date" required>
        </label>
        <label class="ol-field">
          <span class="ol-field__label">Distance (miles)</span>
          <input v-model="distance" class="ol-input" inputmode="decimal" required>
        </label>
        <label class="ol-field">
          <span class="ol-field__label">Purpose</span>
          <select v-model="purpose" class="ol-select">
            <option value="business">Business</option>
            <option value="personal">Personal</option>
          </select>
        </label>
        <label class="ol-field">
          <span class="ol-field__label">Notes</span>
          <input v-model="notes" class="ol-input">
        </label>
        <button class="ol-btn" type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save' }}</button>
        <p v-if="formError" class="ol-error">{{ formError }}</p>
      </form>

      <ul v-if="entries.length" class="ol-list-divide">
        <li v-for="entry in entries" :key="entry.id" class="ol-row">
          <div class="ol-row__main">
            <p class="ol-row__title">
              {{ entry.logged_on_display }} · {{ entry.distance_label }}
            </p>
            <p class="ol-row__meta">
              {{ entry.vehicle_registration }} · {{ entry.purpose_label }}
              <template v-if="entry.notes"> · {{ entry.notes }}</template>
            </p>
          </div>
          <button class="ol-link-action" type="button" @click="onDelete(entry.id)">Delete</button>
        </li>
      </ul>
      <p v-else class="ol-meta">No mileage logged yet.</p>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { MileageRow, VehicleRow } from '~/composables/useBusinessFinance'

useHead({ title: 'Mileage · Accounts · OwnLane' })

const { fetchMileage, fetchVehicles, createMileage, deleteMileage } = useBusinessFinance()
const { from, to, initFromRoute } = useAccountsPeriod()

const entries = ref<MileageRow[]>([])
const vehicles = ref<VehicleRow[]>([])
const loading = ref(true)
const error = ref('')
const showForm = ref(false)
const saving = ref(false)
const formError = ref('')
const vehicleId = ref<number | ''>('')
const loggedOn = ref('')
const distance = ref('')
const purpose = ref('business')
const notes = ref('')

async function reload() {
  loading.value = true
  error.value = ''
  try {
    vehicles.value = await fetchVehicles()
    entries.value = await fetchMileage(from.value || undefined, to.value || undefined)
    if (!vehicleId.value && vehicles.value.length) {
      vehicleId.value = vehicles.value.find(v => v.is_primary)?.id ?? vehicles.value[0].id
    }
    if (!loggedOn.value) loggedOn.value = new Date().toISOString().slice(0, 10)
  } catch (e) {
    error.value = extractApiError(e, 'Could not load mileage.')
  } finally {
    loading.value = false
  }
}

async function onCreate() {
  formError.value = ''
  saving.value = true
  try {
    await createMileage({
      vehicle_id: Number(vehicleId.value),
      logged_on: loggedOn.value,
      distance_miles: parseFloat(distance.value),
      purpose: purpose.value,
      notes: notes.value || undefined,
    })
    showForm.value = false
    distance.value = ''
    notes.value = ''
    await reload()
  } catch (e) {
    formError.value = extractApiError(e, 'Could not save mileage.')
  } finally {
    saving.value = false
  }
}

async function onDelete(id: number) {
  if (!window.confirm('Delete this mileage entry?')) return
  try {
    await deleteMileage(id)
    await reload()
  } catch (e) {
    error.value = extractApiError(e, 'Could not delete entry.')
  }
}

onMounted(async () => {
  initFromRoute()
  await reload()
})
</script>
