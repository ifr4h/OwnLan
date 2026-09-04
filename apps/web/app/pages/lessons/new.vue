<template>
  <section class="ol-page" style="max-width: 560px">
    <NuxtLink :to="backTo" class="ol-back">← Back</NuxtLink>

    <header class="ol-page-header">
      <p class="ol-eyebrow">New lesson</p>
      <h1 class="ol-page-title">Book a lesson</h1>
      <p class="ol-muted" style="max-width: 42ch">{{ headerCopy }}</p>
    </header>

    <div v-if="!pupilsLoading && pupils.length === 0" class="ol-empty">
      <h2 class="ol-empty__title">Add a pupil first</h2>
      <p class="ol-empty__copy">
        Lessons belong to pupils. Add one (or import a CSV), then come back to book.
      </p>
      <div class="ol-empty__actions">
        <NuxtLink to="/pupils/new" class="ol-btn">
          Add pupil
          <span aria-hidden="true">→</span>
        </NuxtLink>
        <NuxtLink to="/pupils/import" class="ol-btn ol-btn--ghost">Import CSV</NuxtLink>
      </div>
    </div>

    <template v-else>
      <p v-if="suggestionHint" class="hint" role="status">{{ suggestionHint }}</p>

      <form class="ol-card ol-stack" @submit.prevent="onSubmit">
        <div class="ol-field">
          <span class="ol-field__label">Pupil</span>
          <OlPupilCombobox
            v-model="learnerId"
            :pupils="pupils"
            :disabled="pupilsLoading"
            :placeholder="pupilsLoading ? 'Loading…' : 'Search pupils…'"
          />
        </div>

        <div class="datetime">
          <label class="ol-field">
            <span class="ol-field__label">{{ repeatWeekly ? 'First date' : 'Date' }}</span>
            <input
              v-model="startsDate"
              class="ol-input ol-input--date"
              type="date"
              required
            >
          </label>
          <label class="ol-field">
            <span class="ol-field__label">Time</span>
            <input
              v-model="startsTime"
              class="ol-input ol-input--time"
              type="time"
              required
            >
          </label>
        </div>
        <p class="ol-field__hint">Times are in {{ timezoneLabel }}</p>

        <div class="ol-field">
          <span class="ol-field__label">Duration</span>
          <div class="ol-seg" role="group" aria-label="Lesson duration">
            <button
              v-for="opt in durationPresets"
              :key="opt"
              class="ol-chip"
              type="button"
              :data-on="!durationIsCustom && Number(durationMinutes) === opt ? 'yes' : 'no'"
              @click="selectDurationPreset(opt)"
            >
              {{ durationChipLabel(opt) }}
            </button>
            <button
              class="ol-chip"
              type="button"
              :data-on="durationIsCustom ? 'yes' : 'no'"
              @click="selectDurationCustom"
            >
              Custom
            </button>
          </div>
          <input
            v-if="durationIsCustom"
            v-model="durationMinutes"
            class="ol-input"
            type="number"
            min="15"
            max="480"
            step="5"
            required
            aria-label="Duration in minutes"
            placeholder="Minutes"
          >
        </div>

        <label class="ol-field">
          <span class="ol-field__label">Pickup</span>
          <textarea
            v-model="pickup"
            class="ol-textarea"
            rows="2"
            placeholder="Uses pupil’s usual pickup if left blank"
          />
        </label>

        <fieldset class="repeat">
          <label class="check">
            <input v-model="repeatWeekly" type="checkbox">
            <span>Repeat weekly</span>
          </label>

          <template v-if="repeatWeekly">
            <div class="repeat__end">
              <label class="radio">
                <input v-model="endMode" type="radio" value="count">
                <span>Number of lessons</span>
              </label>
              <label class="radio">
                <input v-model="endMode" type="radio" value="until">
                <span>End date</span>
              </label>
            </div>

            <label v-if="endMode === 'count'" class="ol-field">
              <span class="ol-field__label">Lessons</span>
              <input
                v-model="occurrenceCount"
                class="ol-input"
                type="number"
                min="2"
                max="52"
                required
              >
            </label>

            <label v-else class="ol-field">
              <span class="ol-field__label">Until</span>
              <input v-model="untilDate" class="ol-input ol-input--date" type="date" required>
            </label>

            <div class="preview">
              <button
                class="ol-btn ol-btn--ghost ol-btn--sm"
                type="button"
                :disabled="previewPending || !canPreview"
                @click="onPreview"
              >
                {{ previewPending ? 'Checking…' : 'Preview dates' }}
              </button>

              <p v-if="preview" class="ol-meta">
                {{ preview.count }} weekly lesson{{ preview.count === 1 ? '' : 's' }}
                <template v-if="preview.conflict_count">
                  · {{ preview.conflict_count }} conflict{{ preview.conflict_count === 1 ? '' : 's' }}
                </template>
              </p>

              <ul v-if="preview" class="preview__list">
                <li
                  v-for="item in preview.occurrences"
                  :key="item.starts_at_local"
                  :class="{ 'preview__item--conflict': item.has_conflict }"
                >
                  {{ item.starts_at_display }}
                  <span v-if="item.has_conflict"> — clashes</span>
                </li>
              </ul>

              <label v-if="preview && preview.conflict_count > 0" class="check">
                <input v-model="allowConflicts" type="checkbox">
                <span>Book anyway (keep conflicting dates)</span>
              </label>
            </div>
          </template>
        </fieldset>

        <p v-if="error" class="ol-error" role="alert">{{ error }}</p>

        <div v-if="travelWarnings.length" class="travel-warn" role="status">
          <p
            v-for="(w, i) in travelWarnings"
            :key="i"
            class="travel-warn__item"
            :class="{ 'travel-warn__item--impossible': w.severity === 'impossible' }"
          >
            {{ w.message }}
          </p>
          <p class="travel-warn__note">You can still book — this is a heads-up, not a block.</p>
        </div>

        <button class="ol-btn" type="submit" :disabled="pending || !learnerId || suggesting">
          {{ submitLabel }}
          <span aria-hidden="true">→</span>
        </button>
      </form>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { BookingSuggestion, RecurringPreview, TravelWarning } from '~/composables/useLessons'
import type { PupilListItem } from '~/composables/usePupils'

useHead({ title: 'Book lesson · OwnLane' })

const route = useRoute()
const { me } = useAuth()
const { listPupils } = usePupils()
const {
  createLesson,
  createRecurring,
  previewRecurring,
  fetchBookingSuggestion,
  checkTravel,
  durationLabel,
} = useLessons()

const pupils = ref<PupilListItem[]>([])
const pupilsLoading = ref(true)
const learnerId = ref<number | null>(null)
const startsAtLocal = ref('')
const defaultDuration = computed(
  () => me.value?.organisation?.default_lesson_duration_minutes || 60,
)
const durationMinutes = ref(String(60))
const durationIsCustom = ref(false)
const durationPresets = [60, 90, 120] as const
const pickup = ref('')
const pickupTouched = ref(false)
const startsTouched = ref(false)
const durationTouched = ref(false)
const pending = ref(false)
const suggesting = ref(false)
const error = ref('')
const suggestion = ref<BookingSuggestion | null>(null)

const repeatWeekly = ref(false)
const endMode = ref<'count' | 'until'>('count')
const occurrenceCount = ref('8')
const untilDate = ref('')
const preview = ref<RecurringPreview | null>(null)
const previewPending = ref(false)
const allowConflicts = ref(false)
const travelWarnings = ref<TravelWarning[]>([])
const travelCheckPending = ref(false)

const startsDate = computed({
  get: () => startsAtLocal.value.slice(0, 10) || '',
  set: (value: string) => {
    const time = startsAtLocal.value.slice(11, 16) || '09:00'
    startsAtLocal.value = value ? `${value}T${time}` : ''
  },
})

const startsTime = computed({
  get: () => startsAtLocal.value.slice(11, 16) || '',
  set: (value: string) => {
    const date = startsAtLocal.value.slice(0, 10)
    if (!date) return
    startsAtLocal.value = value ? `${date}T${value}` : `${date}T09:00`
  },
})

const timezoneLabel = computed(() => me.value?.organisation?.timezone || 'Europe/London')

const backTo = computed(() => {
  const fromLesson = route.query.from_lesson
  if (typeof fromLesson === 'string' && fromLesson) {
    return `/lessons/${fromLesson}`
  }
  const id = route.query.learner_id
  return id ? `/pupils/${id}` : '/lessons'
})

const headerCopy = computed(() => {
  if (repeatWeekly.value) {
    return 'Book a weekly series. Preview the dates before confirming.'
  }
  if (suggestion.value?.schedule_source === 'pattern') {
    return 'We’ve suggested the next slot from this pupil’s usual pattern. Change anything before booking.'
  }
  if ((suggestion.value?.history_count ?? 0) > 0) {
    return 'We’ve filled what we can from recent lessons. Nothing is booked until you confirm.'
  }
  return 'We’ll use the pupil’s usual pickup and your normal lesson length unless you change them.'
})

const suggestionHint = computed(() => {
  const s = suggestion.value
  if (!s || s.history_count === 0 || repeatWeekly.value) return ''
  const bits: string[] = []
  if (s.duration_source === 'history') {
    bits.push(`${durationLabel(s.duration_minutes)} lessons`)
  }
  if (s.pickup_source === 'history' && s.pickup_address) {
    bits.push(`usual pickup`)
  }
  if (s.schedule_source === 'pattern' && s.schedule_pattern) {
    bits.push(`usually ${s.schedule_pattern.weekday}s at ${s.schedule_pattern.time}`)
  }
  if (!bits.length) return ''
  return `From history: ${bits.join(' · ')}. Edit freely — nothing books itself.`
})

const selectedPupil = computed(() =>
  pupils.value.find(p => p.id === learnerId.value),
)

const canPreview = computed(() => {
  if (!learnerId.value || !startsAtLocal.value) return false
  if (endMode.value === 'count') {
    const n = Number(occurrenceCount.value)
    return n >= 2 && n <= 52
  }
  return Boolean(untilDate.value)
})

const submitLabel = computed(() => {
  if (pending.value) return repeatWeekly.value ? 'Booking series…' : 'Booking…'
  if (repeatWeekly.value) {
    return preview.value ? `Book ${preview.value.count} lessons` : 'Book weekly series'
  }
  return 'Book lesson'
})

function durationChipLabel(mins: number): string {
  if (mins === 60) return '1 hr'
  if (mins === 90) return '1.5 hr'
  if (mins === 120) return '2 hr'
  return durationLabel(mins)
}

function syncDurationCustomFlag(mins: number) {
  durationIsCustom.value = !(durationPresets as readonly number[]).includes(mins)
}

function selectDurationPreset(mins: number) {
  durationIsCustom.value = false
  durationMinutes.value = String(mins)
}

function selectDurationCustom() {
  durationIsCustom.value = true
}

watch(repeatWeekly, () => {
  preview.value = null
  allowConflicts.value = false
})

watch([startsAtLocal, occurrenceCount, untilDate, endMode, durationMinutes, learnerId], () => {
  if (repeatWeekly.value) {
    preview.value = null
    allowConflicts.value = false
  }
})

watch([learnerId, startsAtLocal, durationMinutes, pickup, repeatWeekly], () => {
  if (!repeatWeekly.value) {
    void refreshTravelWarnings()
  } else {
    travelWarnings.value = []
  }
})

let travelTimer: ReturnType<typeof setTimeout> | null = null
async function refreshTravelWarnings() {
  if (travelTimer) clearTimeout(travelTimer)
  travelTimer = setTimeout(async () => {
    if (!learnerId.value || !startsAtLocal.value || repeatWeekly.value) {
      travelWarnings.value = []
      return
    }
    travelCheckPending.value = true
    try {
      const result = await checkTravel({
        learner_id: learnerId.value,
        starts_at_local: startsAtLocal.value,
        duration_minutes: Number(durationMinutes.value),
        pickup_address: pickup.value.trim() || undefined,
      })
      travelWarnings.value = result.warnings
    } catch {
      travelWarnings.value = []
    } finally {
      travelCheckPending.value = false
    }
  }, 350)
}

watch(learnerId, async (id, previous) => {
  if (!id) {
    suggestion.value = null
    return
  }
  if (previous != null && previous !== id) {
    pickupTouched.value = false
    startsTouched.value = false
    durationTouched.value = false
  }
  await applySuggestionFor(id)
})

watch(pickup, (value, oldValue) => {
  if (oldValue === undefined) return
  const baseline = suggestion.value?.pickup_address
    ?? selectedPupil.value?.default_pickup_address
    ?? ''
  if (value !== baseline) {
    pickupTouched.value = true
  }
})

watch(startsAtLocal, (value, oldValue) => {
  if (oldValue === undefined) return
  if (value !== (suggestion.value?.suggested_starts_at_local || '')) {
    startsTouched.value = true
  }
})

watch(durationMinutes, (value, oldValue) => {
  if (oldValue === undefined) return
  syncDurationCustomFlag(Number(value))
  if (value !== String(suggestion.value?.duration_minutes ?? 60)) {
    durationTouched.value = true
  }
})

async function applySuggestionFor(id: number) {
  suggesting.value = true
  error.value = ''
  try {
    const next = await fetchBookingSuggestion(id)
    suggestion.value = next

    if (!durationTouched.value) {
      durationMinutes.value = String(next.duration_minutes)
      syncDurationCustomFlag(next.duration_minutes)
    }
    if (!pickupTouched.value) {
      pickup.value = next.pickup_address
        || selectedPupil.value?.default_pickup_address
        || ''
    }
    if (!startsTouched.value && next.suggested_starts_at_local) {
      startsAtLocal.value = next.suggested_starts_at_local
    } else if (!startsTouched.value && !startsAtLocal.value) {
      startsAtLocal.value = defaultStartsFromQuery()
    }
  } catch (e) {
    suggestion.value = null
    if (!pickupTouched.value) {
      pickup.value = selectedPupil.value?.default_pickup_address || ''
    }
    if (!durationTouched.value) {
      durationMinutes.value = String(defaultDuration.value)
      syncDurationCustomFlag(defaultDuration.value)
    }
    if (!startsTouched.value && !startsAtLocal.value) {
      startsAtLocal.value = defaultStartsFromQuery()
    }
    error.value = extractApiError(e, 'Could not load booking suggestions.')
  } finally {
    suggesting.value = false
  }
}

function defaultStartsFromQuery(): string {
  const starts = route.query.starts_at_local
  if (typeof starts === 'string' && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(starts)) {
    return starts.slice(0, 16)
  }
  const date = route.query.date
  if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
    return `${date}T09:00`
  }
  return ''
}

function recurringPayload() {
  return {
    learner_id: learnerId.value as number,
    starts_at_local: startsAtLocal.value,
    duration_minutes: Number(durationMinutes.value),
    pickup_address: pickup.value.trim() || undefined,
    ...(endMode.value === 'count'
      ? { occurrence_count: Number(occurrenceCount.value) }
      : { until_date: untilDate.value }),
    ...(allowConflicts.value ? { allow_conflicts: true } : {}),
  }
}

async function onPreview() {
  previewPending.value = true
  error.value = ''
  try {
    preview.value = await previewRecurring(recurringPayload())
  } catch (e) {
    preview.value = null
    error.value = extractApiError(e, 'Could not preview this series.')
  } finally {
    previewPending.value = false
  }
}

onMounted(async () => {
  try {
    pupils.value = (await listPupils()).items
    if (!startsAtLocal.value) {
      startsAtLocal.value = defaultStartsFromQuery()
    }
    const preset = route.query.learner_id
    if (typeof preset === 'string' && /^\d+$/.test(preset)) {
      const id = Number(preset)
      if (pupils.value.some(p => p.id === id)) {
        learnerId.value = id
      }
    }
    const durationPreset = route.query.duration_minutes
    if (typeof durationPreset === 'string' && /^\d+$/.test(durationPreset)) {
      durationMinutes.value = durationPreset
      syncDurationCustomFlag(Number(durationPreset))
      durationTouched.value = true
    } else {
      durationMinutes.value = String(defaultDuration.value)
      syncDurationCustomFlag(defaultDuration.value)
    }
    if (typeof route.query.starts_at_local === 'string') {
      startsTouched.value = true
    }
  } catch (e) {
    error.value = extractApiError(e, 'Could not load pupils.')
  } finally {
    pupilsLoading.value = false
  }
})

async function onSubmit() {
  pending.value = true
  error.value = ''
  try {
    if (!learnerId.value) {
      error.value = 'Choose a pupil.'
      return
    }
    if (repeatWeekly.value) {
      if (!preview.value) {
        await onPreview()
        if (!preview.value) return
      }
      if (preview.value.conflict_count > 0 && !allowConflicts.value) {
        error.value = 'Some dates conflict. Review the preview or allow conflicts.'
        return
      }
      const result = await createRecurring(recurringPayload())
      const first = result.items[0]
      await navigateTo(first ? `/lessons/${first.id}` : '/lessons')
      return
    }

    const wasFirstLesson = (useAuth().me.value?.onboarding?.lesson_count ?? 0) === 0
    const lesson = await createLesson({
      learner_id: learnerId.value,
      starts_at_local: startsAtLocal.value,
      duration_minutes: Number(durationMinutes.value),
      pickup_address: pickup.value.trim() || undefined,
    })
    await useOnboarding().refresh()
    await navigateTo(wasFirstLesson ? '/today' : `/lessons/${lesson.id}`)
  } catch (e) {
    error.value = extractApiError(e, 'Could not book this lesson.')
  } finally {
    pending.value = false
  }
}
</script>

<style scoped>
.hint {
  font-size: var(--text-body-sm);
  padding: var(--spacing-12) var(--spacing-16);
  border-radius: var(--radius-small);
  background: var(--surface-wash);
  max-width: 48ch;
}

.datetime {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--spacing-12);
}

.repeat {
  border: none;
  margin: 0;
  padding: var(--spacing-12) 0 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.repeat__end {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-16);
}

.check,
.radio {
  display: flex;
  align-items: center;
  gap: var(--spacing-8);
  font-size: var(--text-body-sm);
}

.preview {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
  padding-top: var(--spacing-4);
}

.preview__list {
  list-style: none;
  margin: 0;
  padding: 0;
  max-height: 220px;
  overflow: auto;
  font-size: var(--text-body-sm);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4);
}

.preview__item--conflict {
  color: var(--color-danger);
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
  color: var(--color-danger);
}

.travel-warn__note {
  opacity: 0.7;
  font-size: 14px;
}
</style>
