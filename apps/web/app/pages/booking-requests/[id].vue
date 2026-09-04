<template>
  <section class="ol-page">
    <header class="ol-page-header">
      <p class="ol-eyebrow">Scheduling</p>
      <h1 class="ol-page-title">Lesson request</h1>
    </header>

    <p v-if="loading" class="ol-muted">Loading…</p>
    <p v-else-if="error" class="ol-error" role="alert">{{ error }}</p>

    <template v-else-if="request">
      <article class="ol-card ol-stack">
        <h2 class="ol-section-title">{{ request.learner_name }}</h2>
        <p class="request__when">{{ request.starts_at_day }}</p>
        <p class="request__when">{{ request.starts_at_time }}–{{ request.ends_at_time }}</p>
        <p class="ol-meta">{{ request.duration_label }}</p>
        <p v-if="request.pickup_address" class="ol-meta">Pickup: {{ request.pickup_address }}</p>
      </article>

      <div class="ol-actions">
        <button class="ol-btn" type="button" :disabled="busy" @click="onAccept">Accept</button>
        <button class="ol-btn ol-btn--ghost" type="button" :disabled="busy" @click="showSuggest = !showSuggest">
          Suggest another time
        </button>
        <button class="ol-btn ol-btn--ghost" type="button" :disabled="busy" @click="onDecline">Decline</button>
      </div>

      <section v-if="showSuggest" class="ol-card ol-stack">
        <p class="ol-field__label">Pick an available time</p>
        <div v-for="week in suggestSlots?.days ?? []" :key="week.week_key" class="suggest-week">
          <p class="ol-meta">{{ week.week_label }}</p>
          <div v-for="day in week.days" :key="day.date" class="suggest-day">
            <p>{{ day.date_label }}</p>
            <div class="suggest-times">
              <button
                v-for="slot in day.times"
                :key="slot.starts_at_local"
                class="ol-chip"
                type="button"
                @click="onSuggest(slot.starts_at_local)"
              >
                {{ slot.starts_at_time }}
              </button>
            </div>
          </div>
        </div>
      </section>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { BookingAvailability, BookingRequest } from '~/composables/usePortalBooking'

useHead({ title: 'Lesson request · OwnLane' })

const route = useRoute()
const { listPending, accept, decline, suggest, availabilityForLearner } = useInstructorBooking()

const loading = ref(true)
const error = ref('')
const busy = ref(false)
const showSuggest = ref(false)
const request = ref<(BookingRequest & { learner_name?: string; learner_id?: number }) | null>(null)
const suggestSlots = ref<BookingAvailability | null>(null)

const id = computed(() => Number(route.params.id))

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { items } = await listPending()
    request.value = items.find(r => r.id === id.value) ?? null
    if (!request.value) {
      error.value = 'This request is no longer available.'
      return
    }
    if (request.value.learner_id) {
      suggestSlots.value = await availabilityForLearner(request.value.learner_id, {
        duration_minutes: request.value.duration_minutes,
        pickup_address: request.value.pickup_address ?? undefined,
      })
    }
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not load request.'
  } finally {
    loading.value = false
  }
}

async function onAccept() {
  busy.value = true
  try {
    await accept(id.value)
    await navigateTo('/today')
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not accept.'
  } finally {
    busy.value = false
  }
}

async function onDecline() {
  busy.value = true
  try {
    await decline(id.value)
    await navigateTo('/today')
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not decline.'
  } finally {
    busy.value = false
  }
}

async function onSuggest(startsAtLocal: string) {
  busy.value = true
  try {
    await suggest(id.value, startsAtLocal)
    await navigateTo('/today')
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not suggest a time.'
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.request__when {
  font-size: var(--text-heading-sm);
}

.suggest-times {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
  margin-top: var(--spacing-8);
}

.suggest-week + .suggest-week {
  margin-top: var(--spacing-16);
}
</style>
