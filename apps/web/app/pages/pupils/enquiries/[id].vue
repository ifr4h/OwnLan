<template>
  <section class="ol-page">
    <header class="ol-page-header">
      <NuxtLink to="/pupils/enquiries" class="ol-link-action">← Enquiries</NuxtLink>
      <p class="ol-eyebrow">Enquiry</p>
      <h1 class="ol-page-title">{{ enquiry?.full_name ?? '…' }}</h1>
      <p v-if="enquiry" class="ol-meta">{{ enquiry.status_label }} · {{ enquiry.postcode }}</p>
    </header>

    <p v-if="loading" class="ol-muted">Loading…</p>
    <p v-else-if="error" class="ol-error" role="alert">{{ error }}</p>

    <template v-else-if="enquiry">
      <section v-if="enquiry.duplicates.length" class="ol-panel warn">
        <p class="warn__title">{{ enquiry.first_name }} may already be in your pupils</p>
        <ul>
          <li v-for="dup in enquiry.duplicates" :key="dup.learner_id">
            <NuxtLink :to="dup.path">{{ dup.full_name }}</NuxtLink>
            — {{ dup.reasons.join(', ') }}
          </li>
        </ul>
      </section>

      <div class="grid">
        <section class="ol-panel ol-stack">
          <h2 class="ol-section-title">Who</h2>
          <p>{{ enquiry.full_name }}</p>
          <p class="ol-meta">{{ enquiry.mobile }}</p>
          <p v-if="enquiry.email" class="ol-meta">{{ enquiry.email }}</p>
          <p class="ol-meta">{{ enquiry.postcode }}</p>
        </section>

        <section class="ol-panel ol-stack">
          <h2 class="ol-section-title">Looking for</h2>
          <p v-if="enquiry.service_interest">Interested in: {{ enquiry.service_interest }}</p>
          <p v-if="enquiry.transmission">{{ enquiry.transmission }} lessons</p>
          <p v-if="enquiry.experience_label">{{ enquiry.experience_label }}</p>
          <p v-if="enquiry.desired_start_label">{{ enquiry.desired_start_label }}</p>
          <p v-if="enquiry.practical_test_date">Practical test: {{ enquiry.practical_test_date }}</p>
        </section>

        <section class="ol-panel ol-stack">
          <h2 class="ol-section-title">Usually free</h2>
          <p>{{ enquiry.fit.availability_summary || 'Not specified' }}</p>
        </section>

        <section class="ol-panel ol-stack">
          <h2 class="ol-section-title">Fit</h2>
          <ul class="flags">
            <li v-for="flag in enquiry.fit.flags" :key="flag.type + flag.label">{{ flag.label }}</li>
          </ul>
          <p v-if="enquiry.fit.travel_note" class="ol-meta">{{ enquiry.fit.travel_note }}</p>
        </section>
      </div>

      <section v-if="enquiry.fit.suggested_times.length" class="ol-panel ol-stack">
        <h2 class="ol-section-title">Possible lesson times</h2>
        <ul class="times">
          <li v-for="slot in enquiry.fit.suggested_times" :key="slot.starts_at_local">
            <span>{{ slot.date_label }} · {{ slot.time_label }}</span>
            <button
              v-if="enquiry.converted_learner_id"
              class="ol-btn ol-btn--ghost ol-btn--sm"
              type="button"
              :disabled="booking"
              @click="book(slot)"
            >
              Book
            </button>
          </li>
        </ul>
      </section>

      <section v-if="enquiry.message" class="ol-panel">
        <h2 class="ol-section-title">Message</h2>
        <p>{{ enquiry.message }}</p>
      </section>

      <div class="actions">
        <button
          v-if="enquiry.actions.includes('mark_contacted')"
          class="ol-btn ol-btn--ghost"
          type="button"
          :disabled="acting"
          @click="run(() => markContacted(enquiry!.id))"
        >
          Mark contacted
        </button>
        <button
          v-if="enquiry.actions.includes('accept')"
          class="ol-btn ol-btn--ghost"
          type="button"
          :disabled="acting"
          @click="run(() => accept(enquiry!.id))"
        >
          Accept
        </button>
        <button
          v-if="enquiry.actions.includes('add_pupil')"
          class="ol-btn"
          type="button"
          :disabled="acting"
          @click="run(() => convert(enquiry!.id))"
        >
          Add as pupil
        </button>
        <button
          v-if="enquiry.actions.includes('waiting_list')"
          class="ol-btn ol-btn--ghost"
          type="button"
          :disabled="acting"
          @click="run(() => addToWaitingList(enquiry!.id))"
        >
          Add to waiting list
        </button>
        <button
          v-if="enquiry.actions.includes('decline')"
          class="ol-btn ol-btn--ghost"
          type="button"
          :disabled="acting"
          @click="run(() => decline(enquiry!.id))"
        >
          Decline
        </button>
        <NuxtLink
          v-if="enquiry.learner_path"
          :to="enquiry.learner_path"
          class="ol-btn ol-btn--ghost"
        >
          Open pupil
        </NuxtLink>
      </div>

      <p v-if="actionMessage" class="ok" role="status">{{ actionMessage }}</p>
      <p v-if="actionError" class="ol-error" role="alert">{{ actionError }}</p>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { EnquiryDetail } from '~/composables/useEnquiries'

const route = useRoute()
const router = useRouter()
const id = computed(() => Number(route.params.id))

const {
  fetchEnquiry,
  markContacted,
  accept,
  decline,
  convert,
  addToWaitingList,
  bookFirstLesson,
} = useEnquiries()

const enquiry = ref<EnquiryDetail | null>(null)
const loading = ref(true)
const error = ref('')
const acting = ref(false)
const booking = ref(false)
const actionMessage = ref('')
const actionError = ref('')

useHead(() => ({
  title: enquiry.value ? `${enquiry.value.full_name} · Enquiry · OwnLane` : 'Enquiry · OwnLane',
}))

async function load() {
  loading.value = true
  error.value = ''
  try {
    enquiry.value = await fetchEnquiry(id.value)
  } catch (e) {
    error.value = extractApiError(e, 'Enquiry not found.')
  } finally {
    loading.value = false
  }
}

async function run(fn: () => Promise<unknown>) {
  acting.value = true
  actionError.value = ''
  actionMessage.value = ''
  try {
    const result = await fn()
    if (result && typeof result === 'object' && 'message' in result) {
      actionMessage.value = String((result as { message: string }).message)
    }
    if (result && typeof result === 'object' && 'path' in result) {
      const path = String((result as { path: string }).path)
      if (path.includes('/pupils/') && !path.includes('/enquiries/')) {
        await router.push(path)
        return
      }
    }
    await load()
  } catch (e) {
    actionError.value = extractApiError(e, 'Could not update enquiry.')
  } finally {
    acting.value = false
  }
}

async function book(slot: { starts_at_local: string; duration_minutes: number }) {
  if (!enquiry.value) return
  booking.value = true
  actionError.value = ''
  try {
    const result = await bookFirstLesson(enquiry.value.id, slot.starts_at_local, slot.duration_minutes)
    await router.push(result.path)
  } catch (e) {
    actionError.value = extractApiError(e, 'Could not book lesson.')
  } finally {
    booking.value = false
  }
}

onMounted(() => void load())
</script>

<style scoped>
.grid {
  display: grid;
  gap: var(--spacing-12);
  margin-bottom: var(--spacing-16);
}

@media (min-width: 800px) {
  .grid {
    grid-template-columns: 1fr 1fr;
  }
}

.flags,
.times {
  list-style: none;
  padding: 0;
  margin: 0;
}

.times li {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 8px 0;
  border-bottom: 1px solid var(--color-border);
}

.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: var(--spacing-16);
}

.warn {
  border-color: #f5c451;
  margin-bottom: var(--spacing-16);
}

.warn__title {
  font-weight: 600;
  margin: 0 0 8px;
}

.ok {
  color: var(--color-success);
  margin-top: 12px;
}
</style>
