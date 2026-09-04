<template>
  <section class="page">
    <NuxtLink :to="lesson ? `/lessons/${lesson.id}` : '/lessons'" class="back">← Back</NuxtLink>

    <p v-if="loading" class="muted">Loading…</p>
    <p v-else-if="loadError" class="error" role="alert">{{ loadError }}</p>

    <template v-else-if="lesson">
      <header class="page__header">
        <p class="page__eyebrow">Edit lesson</p>
        <h1 class="page__title">{{ lesson.learner_name }}</h1>
      </header>

      <form class="form" @submit.prevent="onSubmit">
        <label class="field">
          <span class="field__label">Pupil</span>
          <select v-model="learnerId" class="field__input" required>
            <option v-for="p in pupils" :key="p.id" :value="String(p.id)">
              {{ p.full_name }}
            </option>
          </select>
        </label>

        <label class="field">
          <span class="field__label">Date & time</span>
          <input v-model="startsAtLocal" class="field__input" type="datetime-local" required>
          <span class="field__hint">Times are in {{ lesson.timezone }}</span>
        </label>

        <label class="field">
          <span class="field__label">Duration</span>
          <select v-model="durationMinutes" class="field__input">
            <option v-for="opt in durationOptions" :key="opt" :value="String(opt)">
              {{ durationLabel(opt) }}
            </option>
          </select>
        </label>

        <label class="field">
          <span class="field__label">Pickup</span>
          <textarea v-model="pickup" class="field__textarea" rows="2" />
        </label>

        <fieldset v-if="lesson.series_id" class="scope">
          <legend class="field__label">Apply changes to</legend>
          <label class="radio">
            <input v-model="scope" type="radio" value="this">
            <span>This lesson only</span>
          </label>
          <label class="radio">
            <input v-model="scope" type="radio" value="this_and_future">
            <span>This and future lessons</span>
          </label>
        </fieldset>

        <div v-if="travelWarnings.length" class="travel-warn" role="status">
          <p
            v-for="(w, i) in travelWarnings"
            :key="i"
            :class="{ 'travel-warn__item--impossible': w.severity === 'impossible' }"
          >
            {{ w.message }}
          </p>
          <p class="travel-warn__note">You can still save — this is a heads-up, not a block.</p>
        </div>

        <p v-if="error" class="error" role="alert">{{ error }}</p>

        <button class="btn" type="submit" :disabled="pending">
          {{ pending ? 'Saving…' : 'Save changes' }}
        </button>
      </form>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { Lesson, TravelWarning } from '~/composables/useLessons'
import type { PupilListItem } from '~/composables/usePupils'

const route = useRoute()
const id = computed(() => Number(route.params.id))
const { getLesson, updateLesson, checkTravel, durationLabel } = useLessons()
const { listPupils } = usePupils()

const lesson = ref<Lesson | null>(null)
const pupils = ref<PupilListItem[]>([])
const loading = ref(true)
const loadError = ref('')
const pending = ref(false)
const error = ref('')
const travelWarnings = ref<TravelWarning[]>([])

const learnerId = ref('')
const startsAtLocal = ref('')
const durationMinutes = ref('60')
const pickup = ref('')
const scope = ref<'this' | 'this_and_future'>('this')
const durationOptions = [30, 60, 90, 120]

useHead(() => ({
  title: lesson.value ? `Edit lesson · OwnLane` : 'Edit lesson · OwnLane',
}))

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const [lessonData, pupilData] = await Promise.all([
      getLesson(id.value),
      listPupils(),
    ])
    if (lessonData.status !== 'scheduled') {
      await navigateTo(`/lessons/${id.value}`)
      return
    }
    lesson.value = lessonData
    pupils.value = pupilData.items
    learnerId.value = String(lessonData.learner_id)
    startsAtLocal.value = lessonData.starts_at_local
    durationMinutes.value = String(lessonData.duration_minutes)
    pickup.value = lessonData.pickup_address || ''
    void refreshTravelWarnings()
  } catch (e) {
    loadError.value = extractApiError(e, 'Could not load this lesson.')
  } finally {
    loading.value = false
  }
}

let travelTimer: ReturnType<typeof setTimeout> | null = null
async function refreshTravelWarnings() {
  if (travelTimer) clearTimeout(travelTimer)
  travelTimer = setTimeout(async () => {
    if (!learnerId.value || !startsAtLocal.value) {
      travelWarnings.value = []
      return
    }
    try {
      const result = await checkTravel({
        learner_id: Number(learnerId.value),
        starts_at_local: startsAtLocal.value,
        duration_minutes: Number(durationMinutes.value),
        pickup_address: pickup.value.trim() || null,
        exclude_lesson_id: id.value,
      })
      travelWarnings.value = result.warnings
    } catch {
      travelWarnings.value = []
    }
  }, 350)
}

watch([learnerId, startsAtLocal, durationMinutes, pickup], () => {
  void refreshTravelWarnings()
})

async function onSubmit() {
  pending.value = true
  error.value = ''
  try {
    const updated = await updateLesson(id.value, {
      learner_id: Number(learnerId.value),
      starts_at_local: startsAtLocal.value,
      duration_minutes: Number(durationMinutes.value),
      pickup_address: pickup.value.trim() || null,
      ...(lesson.value?.series_id ? { scope: scope.value } : {}),
    })
    const nextId = 'items' in updated ? updated.items[0]?.id : updated.id
    await navigateTo(`/lessons/${nextId ?? id.value}`)
  } catch (e) {
    error.value = extractApiError(e, 'Could not save changes.')
  } finally {
    pending.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.page {
  max-width: 560px;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-20);
}

.back {
  font-size: var(--text-body-sm);
  color: var(--color-ownlane-green);
  align-self: flex-start;
}

.page__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.6;
  margin-bottom: var(--spacing-8);
}

.page__title {
  font-size: var(--text-heading-sm);
}

.form {
  background: var(--surface-card);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-cards);
  box-shadow: var(--shadow-card);
  padding: var(--card-padding);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
}

.field {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.field__label {
  font-size: var(--text-body-sm);
}

.field__hint {
  font-size: 14px;
  opacity: 0.6;
}

.field__input,
.field__textarea {
  width: 100%;
  min-height: 48px;
  padding: 12px 20px;
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-inputs);
  background: var(--surface-canvas);
  font: inherit;
}

.field__textarea {
  border-radius: var(--radius-small);
  resize: vertical;
  min-height: 72px;
}

.scope {
  border: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.radio {
  display: flex;
  align-items: center;
  gap: var(--spacing-8);
  font-size: var(--text-body-sm);
}

.travel-warn {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
  padding: var(--spacing-12) var(--spacing-16);
  border-radius: var(--radius-small);
  background: var(--color-hi-yellow);
  font-size: var(--text-body-sm);
}

.travel-warn__item--impossible {
  color: var(--color-marker-red);
}

.travel-warn__note {
  opacity: 0.7;
  font-size: 14px;
}

.btn {
  display: inline-flex;
  align-items: center;
  min-height: 48px;
  padding: 12px 24px;
  border: none;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  box-shadow: var(--shadow-button);
  cursor: pointer;
  align-self: flex-start;
}

.btn:disabled {
  opacity: 0.6;
}

.error {
  color: var(--color-marker-red);
  font-size: var(--text-body-sm);
}

.muted {
  opacity: 0.65;
}
</style>
