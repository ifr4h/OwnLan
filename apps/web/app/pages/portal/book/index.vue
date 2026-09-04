<template>
  <PortalPage>
    <template #header>
      <header class="book__header">
        <p class="portal-page__meta">Find a time</p>
        <h1 class="portal-page__title">{{ headline }}</h1>
      </header>
    </template>

    <p v-if="loading" class="ol-muted">Checking availability…</p>
    <p v-else-if="error" class="ol-error" role="alert">{{ error }}</p>

    <template v-else-if="availability">
      <section v-if="availability.suggested" class="book__suggested ol-card">
        <p class="book__eyebrow">Suggested</p>
        <p class="book__day">{{ availability.suggested.date_label }}</p>
        <button
          class="book__slot book__slot--primary"
          type="button"
          @click="selectSlot(availability.suggested!.starts_at_local)"
        >
          {{ availability.suggested.starts_at_time }}
        </button>
        <p v-if="availability.suggested.recommendation" class="book__hint">
          {{ availability.suggested.recommendation }}
        </p>
      </section>

      <section v-for="week in availability.days" :key="week.week_key" class="book__week">
        <h2 class="book__week-title">{{ week.week_label }}</h2>
        <div v-for="day in week.days" :key="day.date" class="book__day-block">
          <p class="book__day">{{ day.date_label }}</p>
          <div class="book__times">
            <button
              v-for="slot in day.times"
              :key="slot.starts_at_local"
              class="book__slot"
              type="button"
              @click="selectSlot(slot.starts_at_local)"
            >
              {{ slot.starts_at_time }}
            </button>
          </div>
        </div>
      </section>

      <p v-if="availability.total_slots === 0" class="ol-muted">
        No suitable times right now. Try again later or ask your instructor.
      </p>
    </template>
  </PortalPage>
</template>

<script setup lang="ts">
import type { BookingAvailability } from '~/composables/usePortalBooking'

definePageMeta({ layout: 'portal' })
useHead({ title: 'Find a time · OwnLane' })

const route = useRoute()
const { fetchAvailability, fetchSettings } = usePortalBooking()

const loading = ref(true)
const error = ref('')
const availability = ref<BookingAvailability | null>(null)
const mode = ref('request')

const lessonId = computed(() => {
  const raw = route.query.lesson_id
  return raw ? Number(raw) : null
})

const headline = computed(() => {
  if (lessonId.value) return 'Choose a new time'
  return mode.value === 'request' ? 'Request a lesson' : 'Book a lesson'
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const settings = await fetchSettings()
    mode.value = settings.booking_mode
    if (!settings.can_book && !lessonId.value) {
      error.value = 'Your instructor arranges lessons directly.'
      return
    }
    availability.value = await fetchAvailability({
      exclude_lesson_id: lessonId.value ?? undefined,
      duration_minutes: route.query.duration ? Number(route.query.duration) : undefined,
      pickup_address: typeof route.query.pickup === 'string' ? route.query.pickup : undefined,
    })
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not load availability.'
  } finally {
    loading.value = false
  }
}

function selectSlot(startsAtLocal: string) {
  const query: Record<string, string> = { starts_at_local: startsAtLocal }
  if (lessonId.value) query.lesson_id = String(lessonId.value)
  if (availability.value?.duration_minutes) {
    query.duration_minutes = String(availability.value.duration_minutes)
  }
  if (availability.value?.pickup_address) {
    query.pickup_address = availability.value.pickup_address
  }
  navigateTo({ path: '/portal/book/confirm', query })
}

onMounted(load)
</script>

<style scoped>
.book__header {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4);
}

.book__week {
  margin-top: var(--spacing-20);
}

.book__week-title {
  font-size: var(--text-body-sm);
  color: rgba(17, 17, 24, 0.65);
  margin-bottom: var(--spacing-8);
}

.book__day-block + .book__day-block {
  margin-top: var(--spacing-16);
}

.book__day {
  font-weight: 600;
  margin-bottom: var(--spacing-8);
}

.book__times {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
}

.book__slot {
  min-width: 4.5rem;
  min-height: 44px;
  padding: 10px 14px;
  border-radius: var(--radius-buttons);
  border: 1px solid var(--color-border);
  background: var(--color-paper-white);
  font-size: var(--text-body);
}

.book__slot--primary {
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  border-color: transparent;
}

.book__suggested {
  padding: var(--spacing-16);
  margin-bottom: var(--spacing-16);
}

.book__eyebrow {
  font-size: var(--text-caption-mono);
  text-transform: uppercase;
  letter-spacing: var(--tracking-caption-mono);
  color: var(--color-ownlane-green);
}

.book__hint {
  margin-top: var(--spacing-8);
  font-size: var(--text-body-sm);
  color: rgba(17, 17, 24, 0.7);
}

@media (min-width: 1024px) {
  .book__layout {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: var(--spacing-24);
  }
}
</style>
