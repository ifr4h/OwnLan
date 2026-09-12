<script setup lang="ts">
import type { BookingSuggestion } from '~/composables/useLessons'
import type { PupilListItem } from '~/composables/usePupils'
import type { LearnerLocation, PickupSelection } from '~/composables/useLearnerLocations'
import { durationLabel } from '~/composables/useLessons'
import LocationPicker from '~/components/locations/LocationPicker.vue'

export type DiaryBookPreset = {
  learnerId?: number | null
  startsAtLocal?: string | null
  date?: string | null
  durationMinutes?: number | null
  pickup?: string | null
  pickupLocationId?: number | null
  /** When set, pupil combobox is locked (e.g. Book next). */
  lockPupil?: boolean
  /** Prefer context suggestion over a fresh fetch when present. */
  suggestion?: BookingSuggestion | null
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
const {
  listForLearner,
  createForLearner,
} = useLearnerLocations()
const {
  services: catalogueServices,
  defaultService,
  ensureLoaded: ensureServicesLoaded,
} = useBookableServices()

const pupils = ref<PupilListItem[]>([])
const pupilsLoading = ref(false)
const learnerId = ref<number | null>(null)
const startsAtLocal = ref('')
const serviceId = ref<number | null>(null)
const durationMinutes = ref('60')
const durationIsCustom = ref(false)
const pickupSel = ref<PickupSelection>({
  pickup_location_id: null,
  pickup_address: null,
})
const pickupTouched = ref(false)
const durationTouched = ref(false)
const pending = ref(false)
const error = ref('')
const suggestion = ref<BookingSuggestion | null>(null)
const locations = ref<LearnerLocation[]>([])
const locationsLoading = ref(false)

const durationPresets = [60, 90, 120] as const
const hasCatalogue = computed(() => catalogueServices.value.length > 0)

const selectedService = computed(() =>
  catalogueServices.value.find(s => s.id === serviceId.value) || null,
)

const isTestDayService = computed(() => selectedService.value?.kind === 'test_day')

const bookingRef = ref('')
const cancelBy = ref('')
const testTimeDetail = ref('')

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
  const serviceBit = selectedService.value ? `${selectedService.value.name} · ` : ''
  return `${day} · ${startsTime.value} · ${serviceBit}${durationLabel(mins)}`
})

function durationChipLabel(mins: number): string {
  if (mins === 60) return '1 hr'
  if (mins === 90) return '1.5 hr'
  if (mins === 120) return '2 hr'
  return durationLabel(mins)
}

function selectService(id: number) {
  const service = catalogueServices.value.find(s => s.id === id)
  if (!service) return
  serviceId.value = service.id
  durationMinutes.value = String(service.duration_minutes)
  durationIsCustom.value = false
  durationTouched.value = true
}

function selectDurationPreset(mins: number) {
  durationIsCustom.value = false
  durationMinutes.value = String(mins)
  durationTouched.value = true
  if (hasCatalogue.value) {
    const match = catalogueServices.value.find(s => s.duration_minutes === mins)
    serviceId.value = match?.id ?? null
  }
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

async function loadLocations(id: number) {
  locationsLoading.value = true
  try {
    locations.value = await listForLearner(id)
  } catch {
    locations.value = []
  } finally {
    locationsLoading.value = false
  }
}

function applyPreset(preset: DiaryBookPreset | null) {
  error.value = ''
  suggestion.value = preset?.suggestion || null
  pickupTouched.value = false
  durationTouched.value = false
  locations.value = []
  const defaultDur = me.value?.organisation?.default_lesson_duration_minutes || 60
  const fallback = defaultService.value

  learnerId.value = preset?.learnerId ?? null

  if (preset?.startsAtLocal) {
    const s = preset.startsAtLocal
    startsAtLocal.value = s.length === 16 ? `${s}:00` : s.slice(0, 19)
  } else if (preset?.suggestion?.suggested_starts_at_local) {
    const s = preset.suggestion.suggested_starts_at_local
    startsAtLocal.value = s.length === 16 ? `${s}:00` : s.slice(0, 19)
  } else if (preset?.date) {
    startsAtLocal.value = `${preset.date}T09:00`
  } else {
    const n = new Date()
    const ymd = `${n.getFullYear()}-${String(n.getMonth() + 1).padStart(2, '0')}-${String(n.getDate()).padStart(2, '0')}`
    startsAtLocal.value = `${ymd}T09:00`
  }

  const dur = preset?.durationMinutes
    || preset?.suggestion?.duration_minutes
    || fallback?.duration_minutes
    || defaultDur
  durationMinutes.value = String(dur)
  durationIsCustom.value = !(durationPresets as readonly number[]).includes(dur)

  const matched = catalogueServices.value.find(s => s.duration_minutes === dur && s.is_default)
    || catalogueServices.value.find(s => s.duration_minutes === dur)
    || fallback
  serviceId.value = matched?.id ?? null
  if (matched && !preset?.durationMinutes && !preset?.suggestion?.duration_minutes) {
    durationMinutes.value = String(matched.duration_minutes)
    durationIsCustom.value = false
  }

  pickupSel.value = {
    pickup_location_id: preset?.pickupLocationId ?? null,
    pickup_address: preset?.pickup
      || preset?.suggestion?.pickup_address
      || null,
  }
}

watch(
  () => props.open,
  async (open) => {
    if (!open) return
    await ensureServicesLoaded()
    applyPreset(props.preset)
    if (!pupils.value.length) await loadPupils()
    if (learnerId.value) {
      await loadLocations(learnerId.value)
      if (!props.preset?.suggestion) {
        await loadSuggestion(learnerId.value)
      }
    }
  },
)

watch(learnerId, async (id, prev) => {
  if (!props.open || !id || id === prev) return
  pickupTouched.value = false
  await loadLocations(id)
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
      const addr = props.preset?.pickup
        || suggestion.value.pickup_address
        || pupils.value.find(p => p.id === id)?.default_pickup_address
        || null
      const match = addr
        ? locations.value.find(l => l.address === addr)
        : locations.value.find(l => l.is_default)
      pickupSel.value = {
        pickup_location_id: match?.id ?? props.preset?.pickupLocationId ?? null,
        pickup_address: match?.address ?? addr,
      }
    }
  } catch {
    suggestion.value = null
  }
}

function onPickupUpdate(value: PickupSelection) {
  pickupTouched.value = true
  pickupSel.value = value
}

async function onSavePlace(payload: { label: string; icon: string; address: string }) {
  if (!learnerId.value) return
  try {
    const created = await createForLearner(learnerId.value, {
      label: payload.label,
      icon: payload.icon,
      address: payload.address,
    })
    locations.value = await listForLearner(learnerId.value)
    pickupSel.value = {
      pickup_location_id: created.id,
      pickup_address: created.address,
    }
    pickupTouched.value = true
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not save that place.'
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
    const body: Record<string, unknown> = {
      learner_id: learnerId.value,
      starts_at_local: starts,
      duration_minutes: Number(durationMinutes.value) || 60,
    }
    if (serviceId.value) {
      body.service_id = serviceId.value
    }
    if (isTestDayService.value) {
      body.test_details = {
        practical_test_booking_ref: bookingRef.value.trim() || null,
        practical_test_cancel_by: cancelBy.value || null,
        practical_test_time: testTimeDetail.value || startsTime.value || null,
      }
    }
    if (pickupSel.value.pickup_location_id) {
      body.pickup_location_id = pickupSel.value.pickup_location_id
    } else if (pickupSel.value.pickup_address) {
      body.pickup_address = pickupSel.value.pickup_address
      body.pickup_location_id = null
    }
    await createLesson(body as Parameters<typeof createLesson>[0])
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
            <span class="ol-field__label">{{ hasCatalogue ? 'Service' : 'Duration' }}</span>
            <div v-if="hasCatalogue" class="ol-seg book__services" role="group" aria-label="Service">
              <button
                v-for="service in catalogueServices"
                :key="service.id"
                class="ol-chip"
                type="button"
                :disabled="pending"
                :class="{ 'ol-chip--on': !durationIsCustom && serviceId === service.id }"
                @click="selectService(service.id)"
              >
                {{ service.name }}
                <span class="book__chip-meta">{{ service.duration_label }} · {{ service.price_label }}</span>
              </button>
              <button
                class="ol-chip"
                type="button"
                :disabled="pending"
                :class="{ 'ol-chip--on': durationIsCustom }"
                @click="selectDurationCustom"
              >
                Other length
              </button>
            </div>
            <div v-else class="ol-seg" role="group" aria-label="Lesson duration">
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
              @input="durationTouched = true; serviceId = null"
            >
          </div>

          <div v-if="isTestDayService" class="book__test-details">
            <p class="book__test-title">Test details</p>
            <label class="ol-field">
              <span class="ol-field__label">Booking reference</span>
              <input v-model="bookingRef" class="ol-input" type="text" :disabled="pending">
            </label>
            <label class="ol-field">
              <span class="ol-field__label">Last cancellation date</span>
              <input v-model="cancelBy" class="ol-input" type="date" :disabled="pending">
            </label>
            <label class="ol-field">
              <span class="ol-field__label">Test time</span>
              <input v-model="testTimeDetail" class="ol-input ol-input--time" type="time" :disabled="pending">
            </label>
          </div>

          <div v-if="learnerId" class="ol-field">
            <span class="ol-field__label">Pickup</span>
            <LocationPicker
              :model-value="pickupSel"
              :locations="locations"
              :loading="locationsLoading"
              :disabled="pending"
              audience="instructor"
              can-save-place
              @update:model-value="onPickupUpdate"
              @save-place="onSavePlace"
            />
          </div>

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

.book__test-details {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 12px;
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-small);
  background: var(--surface-wash);
}

.book__test-title {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.65;
  margin: 0;
}

.book__services {
  flex-direction: column;
  align-items: stretch;
}

.book__services .ol-chip {
  justify-content: flex-start;
  gap: 8px;
  text-align: left;
}

.book__chip-meta {
  font-weight: 400;
  color: var(--color-muted);
  font-size: 12px;
}

.book__services .ol-chip--on .book__chip-meta {
  color: color-mix(in srgb, var(--color-paper-white) 80%, transparent);
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
