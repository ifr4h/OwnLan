<script setup lang="ts">
import type { BookingSuggestion } from '~/composables/useLessons'
import type { PupilListItem } from '~/composables/usePupils'
import { durationLabel } from '~/composables/useLessons'

export type DiaryBookPreset = {
  learnerId?: number | null
  startsAtLocal?: string | null
  date?: string | null
  durationMinutes?: number | null
  pickup?: string | null
  /** When set, pupil combobox is locked (e.g. Book next). */
  lockPupil?: boolean
}

const props = defineProps<{
  open: boolean
  preset: DiaryBookPreset | null
}>()

const emit = defineEmits<{
  close: []
  booked: [message?: string]
}>()

const { me } = useAuth()
const { listPupils } = usePupils()
const { createLesson, fetchBookingSuggestion } = useLessons()

const pupils = ref<PupilListItem[]>([])
const pupilsLoading = ref(false)
const learnerId = ref<number | null>(null)
const startsAtLocal = ref('')
const durationMinutes = ref('60')
const durationIsCustom = ref(false)
const pickup = ref('')
const pickupTouched = ref(false)
const durationTouched = ref(false)
const pending = ref(false)
const error = ref('')
const suggestion = ref<BookingSuggestion | null>(null)

const durationPresets = [60, 90, 120] as const

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
const lockPupil = computed(() => !!props.preset?.lockPupil && !!props.preset?.learnerId)

const whenSummary = computed(() => {
  if (!startsDate.value || !startsTime.value) return ''
  const d = new Date(`${startsDate.value}T12:00:00`)
  const day = Number.isNaN(d.getTime())
    ? startsDate.value
    : d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' })
  const mins = Number(durationMinutes.value) || 60
  return `${day} · ${startsTime.value} · ${durationLabel(mins)}`
})

function durationChipLabel(mins: number): string {
  if (mins === 60) return '1 hr'
  if (mins === 90) return '1.5 hr'
  if (mins === 120) return '2 hr'
  return durationLabel(mins)
}

function selectDurationPreset(mins: number) {
  durationIsCustom.value = false
  durationMinutes.value = String(mins)
  durationTouched.value = true
}

function selectDurationCustom() {
  durationIsCustom.value = true
}

async function loadPupils() {
  pupilsLoading.value = true
  try {
    pupils.value = await listPupils()
  } catch {
    pupils.value = []
  } finally {
    pupilsLoading.value = false
  }
}

function applyPreset(preset: DiaryBookPreset | null) {
  error.value = ''
  suggestion.value = null
  pickupTouched.value = false
  durationTouched.value = false
  const defaultDur = me.value?.organisation?.default_lesson_duration_minutes || 60

  learnerId.value = preset?.learnerId ?? null

  if (preset?.startsAtLocal) {
    const s = preset.startsAtLocal
    startsAtLocal.value = s.length === 16 ? `${s}:00` : s.slice(0, 19)
  } else if (preset?.date) {
    startsAtLocal.value = `${preset.date}T09:00`
  } else {
    const n = new Date()
    const ymd = `${n.getFullYear()}-${String(n.getMonth() + 1).padStart(2, '0')}-${String(n.getDate()).padStart(2, '0')}`
    startsAtLocal.value = `${ymd}T09:00`
  }

  const dur = preset?.durationMinutes || defaultDur
  durationMinutes.value = String(dur)
  durationIsCustom.value = !(durationPresets as readonly number[]).includes(dur)
  pickup.value = preset?.pickup || ''
}

watch(
  () => props.open,
  async (open) => {
    if (!open) return
    applyPreset(props.preset)
    if (!pupils.value.length) await loadPupils()
    if (learnerId.value) await loadSuggestion(learnerId.value)
  },
)

watch(learnerId, async (id, prev) => {
  if (!props.open || !id || id === prev) return
  await loadSuggestion(id)
})

async function loadSuggestion(id: number) {
  try {
    suggestion.value = await fetchBookingSuggestion(id)
    if (!durationTouched.value && suggestion.value.duration_minutes) {
      durationMinutes.value = String(suggestion.value.duration_minutes)
      durationIsCustom.value = !(durationPresets as readonly number[])
        .includes(suggestion.value.duration_minutes)
    }
    if (!pickupTouched.value) {
      pickup.value = props.preset?.pickup
        || suggestion.value.pickup_address
        || pupils.value.find(p => p.id === id)?.default_pickup_address
        || ''
    }
  } catch {
    suggestion.value = null
  }
}

async function onSubmit() {
  if (!learnerId.value || !startsAtLocal.value) {
    error.value = 'Choose a pupil and a time.'
    return
  }
  pending.value = true
  error.value = ''
  try {
    const starts = startsAtLocal.value.length === 16
      ? `${startsAtLocal.value}:00`
      : startsAtLocal.value
    await createLesson({
      learner_id: learnerId.value,
      starts_at_local: starts,
      duration_minutes: Number(durationMinutes.value) || 60,
      pickup_address: pickup.value.trim() || undefined,
    })
    emit('booked', 'Lesson booked')
    emit('close')
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not book that lesson.'
  } finally {
    pending.value = false
  }
}

function onBackdrop() {
  if (pending.value) return
  emit('close')
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="book"
      role="dialog"
      aria-modal="true"
      aria-label="Book a lesson"
    >
      <button class="book__backdrop" type="button" aria-label="Close" @click="onBackdrop" />
      <div class="book__panel">
        <header class="book__head">
          <div>
            <p class="book__eyebrow">Book lesson</p>
            <p v-if="whenSummary" class="book__summary">{{ whenSummary }}</p>
          </div>
          <button class="book__x" type="button" aria-label="Close" :disabled="pending" @click="emit('close')">
            <OlIcon name="close" :size="16" />
          </button>
        </header>

        <form class="book__form" @submit.prevent="onSubmit">
          <div class="ol-field">
            <span class="ol-field__label">Pupil</span>
            <OlPupilCombobox
              v-model="learnerId"
              :pupils="pupils"
              :disabled="pupilsLoading || lockPupil || pending"
              :placeholder="pupilsLoading ? 'Loading…' : 'Search pupils…'"
            />
          </div>

          <div class="book__datetime">
            <label class="ol-field">
              <span class="ol-field__label">Date</span>
              <input
                v-model="startsDate"
                class="ol-input ol-input--date"
                type="date"
                required
                :disabled="pending"
              >
            </label>
            <label class="ol-field">
              <span class="ol-field__label">Time</span>
              <input
                v-model="startsTime"
                class="ol-input ol-input--time"
                type="time"
                required
                :disabled="pending"
              >
            </label>
          </div>
          <p class="book__hint">Times are in {{ timezoneLabel }}</p>

          <div class="ol-field">
            <span class="ol-field__label">Duration</span>
            <div class="ol-seg" role="group" aria-label="Lesson duration">
              <button
                v-for="opt in durationPresets"
                :key="opt"
                class="ol-chip"
                type="button"
                :disabled="pending"
                :class="{ 'ol-chip--on': !durationIsCustom && Number(durationMinutes) === opt }"
                @click="selectDurationPreset(opt)"
              >
                {{ durationChipLabel(opt) }}
              </button>
              <button
                class="ol-chip"
                type="button"
                :disabled="pending"
                :class="{ 'ol-chip--on': durationIsCustom }"
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
              :disabled="pending"
              aria-label="Duration in minutes"
              placeholder="Minutes"
              @input="durationTouched = true"
            >
          </div>

          <label class="ol-field">
            <span class="ol-field__label">Pickup</span>
            <textarea
              v-model="pickup"
              class="ol-textarea"
              rows="2"
              :disabled="pending"
              placeholder="Usual pickup if left blank"
              @input="pickupTouched = true"
            />
          </label>

          <p v-if="error" class="book__error" role="alert">{{ error }}</p>

          <div class="book__actions">
            <button class="ol-btn ol-btn--block" type="submit" :disabled="pending || !learnerId">
              {{ pending ? 'Booking…' : 'Book lesson' }}
            </button>
            <button class="ol-btn ol-btn--ghost ol-btn--block" type="button" :disabled="pending" @click="emit('close')">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.book {
  position: fixed;
  inset: 0;
  z-index: 220;
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.book__backdrop {
  position: absolute;
  inset: 0;
  border: none;
  background: color-mix(in srgb, var(--color-ink-black) 36%, transparent);
  cursor: pointer;
}

.book__panel {
  position: relative;
  z-index: 1;
  width: min(100%, 440px);
  max-height: min(92vh, 720px);
  overflow: auto;
  padding: 16px 16px calc(16px + env(safe-area-inset-bottom, 0px));
  border-radius: 20px 20px 0 0;
  background: var(--color-parchment);
  display: flex;
  flex-direction: column;
  gap: 14px;
  box-shadow: 0 -8px 32px color-mix(in srgb, var(--color-ink-black) 12%, transparent);
}

.book__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.book__eyebrow {
  margin: 0;
  font-size: 10px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  font-weight: 600;
  color: var(--color-muted);
}

.book__summary {
  margin: 4px 0 0;
  font-size: var(--text-body);
  font-weight: 600;
  color: var(--color-ink-black);
}

.book__x {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 999px;
  background: color-mix(in srgb, var(--color-border) 70%, transparent);
  color: var(--color-ink-black);
  cursor: pointer;
}

.book__form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.book__datetime {
  display: grid;
  grid-template-columns: 1.2fr 1fr;
  gap: 10px;
}

.book__hint {
  margin: -6px 0 0;
  font-size: 12px;
  color: var(--color-muted);
}

.book__error {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-danger);
}

.book__actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 4px;
}

@media (min-width: 900px) {
  .book {
    align-items: center;
  }

  .book__panel {
    border-radius: 16px;
    margin: 16px;
  }
}
</style>
