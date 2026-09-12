<template>
  <PortalPage>
    <template #header>
      <header class="places__header">
        <NuxtLink to="/portal/profile" class="places__back">← Profile</NuxtLink>
        <h1 class="portal-page__title">Pickup places</h1>
        <p class="places__hint">Shared with your instructor</p>
      </header>
    </template>

    <p v-if="loading" class="ol-muted">Loading places…</p>
    <p v-else-if="error" class="ol-error" role="alert">{{ error }}</p>

    <PlacesManager
      v-else
      :locations="locations"
      audience="learner"
      :busy="busy"
      @create="onCreate"
      @update="onUpdate"
      @remove="onRemove"
      @set-default="onDefault"
    />
  </PortalPage>
</template>

<script setup lang="ts">
import type { LearnerLocation, LocationIconName } from '~/composables/useLearnerLocations'
import PlacesManager from '~/components/locations/PlacesManager.vue'

definePageMeta({ layout: 'portal' })
useHead({ title: 'Pickup places · OwnLane' })

const {
  listPortalPlaces,
  createPortalPlace,
  updatePortalPlace,
  deletePortalPlace,
  setDefaultPortalPlace,
} = useLearnerLocations()

const locations = ref<LearnerLocation[]>([])
const loading = ref(true)
const busy = ref(false)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    locations.value = await listPortalPlaces()
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not load your places.'
  } finally {
    loading.value = false
  }
}

async function onCreate(payload: {
  label: string
  icon: LocationIconName
  address: string
  is_default?: boolean
}) {
  busy.value = true
  error.value = ''
  try {
    await createPortalPlace(payload)
    locations.value = await listPortalPlaces()
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not save that place.'
  } finally {
    busy.value = false
  }
}

async function onUpdate(
  id: number,
  payload: { label: string; icon: LocationIconName; address: string },
) {
  busy.value = true
  error.value = ''
  try {
    await updatePortalPlace(id, payload)
    locations.value = await listPortalPlaces()
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not update that place.'
  } finally {
    busy.value = false
  }
}

async function onRemove(id: number) {
  busy.value = true
  error.value = ''
  try {
    await deletePortalPlace(id)
    locations.value = await listPortalPlaces()
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not remove that place.'
  } finally {
    busy.value = false
  }
}

async function onDefault(id: number) {
  busy.value = true
  error.value = ''
  try {
    await setDefaultPortalPlace(id)
    locations.value = await listPortalPlaces()
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not set usual pickup.'
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.places__header {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.places__back {
  font-size: var(--text-body-sm);
  font-weight: 600;
  color: var(--color-ownlane-green);
  text-decoration: none;
}

.places__hint {
  margin: 0;
  font-size: 13px;
  color: var(--color-muted);
}
</style>
