<script setup lang="ts">
import type { DiaryLesson } from '~/composables/useLessons'
import type { BookingRequest } from '~/composables/usePortalBooking'
import { initialsFromName } from '~/utils/diary/overviewModel'
import { formatDuration } from '~/utils/calendar/timeGrid'

export type InstructorBookingRequest = BookingRequest & {
  learner_id: number
  learner_name: string
  learner_first_name?: string
}

export type SlotPickState = {
  kind: 'move' | 'suggest'
  pendingStartsAtLocal: string | null
  pendingLabel: string | null
} | null

const props = defineProps<{
  lesson: DiaryLesson | null
  request: InstructorBookingRequest | null
  slotPick: SlotPickState
}>()

const emit = defineEmits<{
  close: []
  changed: [message?: string]
  startPickSlot: [kind: 'move' | 'suggest']
  cancelPickSlot: []
  confirmPickSlot: []
  bookNext: []
}>()

const { updateLesson, cancelLesson } = useLessons()
const { recordPayment } = useFinance()
const { accept, decline, suggest } = useInstructorBooking()

const busy = ref(false)
const error = ref('')
const menuOpen = ref(false)
const confirmCancel = ref(false)
const confirmRemove = ref(false)
const confirmDecline = ref(false)
const payOpen = ref(false)
const payMethod = ref<'cash' | 'bank_transfer' | 'card' | 'other'>('bank_transfer')
const declineReason = ref('')
const notesOpen = ref(false)
const notesDraft = ref('')
const notesSaving = ref(false)

const isRequest = computed(() => !!props.request && !props.lesson)

const name = computed(() =>
  props.lesson?.learner_name
  || props.request?.learner_name
  || 'Pupil',
)

const initials = computed(() => initialsFromName(name.value))

/** Booking state for the panel chrome — always shown. */
const bookingStatus = computed(() => {
  if (isRequest.value) {
    return { label: 'Awaiting confirmation', tone: 'request' as const }
  }
  const l = props.lesson
  if (!l) return { label: 'Lesson', tone: 'muted' as const }
  if (l.status === 'cancelled') return { label: 'Cancelled', tone: 'muted' as const }
  if (l.status === 'completed') return { label: 'Completed', tone: 'muted' as const }
  if (l.status === 'no_show') return { label: 'No-show', tone: 'warn' as const }
  if (l.overlaps) return { label: 'Overlap', tone: 'warn' as const }
  if (l.is_current) return { label: 'Now', tone: 'live' as const }
  return { label: 'Confirmed', tone: 'ok' as const }
})

const whenDay = computed(() => {
  if (props.request) return props.request.starts_at_day
  const local = props.lesson?.starts_at_local
  if (local && local.length >= 10) {
    const d = new Date(`${local.slice(0, 10)}T12:00:00`)
    if (!Number.isNaN(d.getTime())) {
      return d.toLocaleDateString('en-GB', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
      })
    }
  }
  return props.lesson?.starts_at_display || ''
})

const timeRange = computed(() => {
  if (props.request) return `${props.request.starts_at_time}–${props.request.ends_at_time}`
  const l = props.lesson
  if (!l) return ''
  const start = l.starts_at_time || l.starts_at_local?.slice(11, 16) || ''
  const end = l.ends_at_time || ''
  return end ? `${start}–${end}` : start
})

const durationText = computed(() => {
  const mins = props.lesson?.duration_minutes || props.request?.duration_minutes
  if (!mins) return ''
  return formatDuration(mins)
})

const detailsSummary = computed(() => {
  const bits = [timeRange.value, durationText.value].filter(Boolean)
  return bits.join(' · ')
})

const transmissionLabel = computed(() => {
  const t = props.lesson?.learner_transmission
  if (!t || t === 'either') return null
  if (t === 'manual') return 'Manual'
  if (t === 'automatic') return 'Automatic'
  return t.charAt(0).toUpperCase() + t.slice(1)
})

const whenSummary = computed(() => {
  const bits = [whenDay.value, detailsSummary.value].filter(Boolean)
  return bits.join(' · ')
})

const pickup = computed(() =>
  props.lesson?.pickup_address || props.request?.pickup_address || null,
)

const travelNote = computed(() => {
  const t = props.lesson?.travel_to_next
  if (!t || t.travel_minutes == null) return null
  if (t.is_warning) return t.message || `${t.travel_minutes}m to next — tight`
  return `${t.travel_minutes} min to next lesson`
})

const travelIsWarning = computed(() => !!props.lesson?.travel_to_next?.is_warning)

const lastFocus = computed(() => props.lesson?.learner_last_lesson_summary || null)
const nextFocus = computed(() =>
  props.lesson?.learner_next_focus || props.lesson?.next_focus || null,
)

const testLine = computed(() => {
  const t = props.lesson?.test_journey
  if (!t) return null
  const label = t.countdown_label
    || ('test_date_display' in t && t.test_date_display ? `Test · ${t.test_date_display}` : null)
  if (!label) return null
  const lessonsBefore = 'lessons_booked_before_test' in t
    && typeof t.lessons_booked_before_test === 'number'
    ? t.lessons_booked_before_test
    : null
  return { label, lessonsBefore }
})

const settlementQuiet = computed(() => {
  const l = props.lesson
  if (!l) return null
  if (l.status === 'no_show' && l.settlement === 'waived') return 'No charge'
  if (l.settlement === 'paid') return 'Paid'
  if (l.settlement === 'package') return 'Package'
  if (l.settlement === 'outstanding' && l.price_pence) {
    return `£${(l.price_pence / 100).toFixed(0)} due`
  }
  if (l.settlement === 'outstanding') return 'Unpaid'
  return null
})

const directionsHref = computed(() => {
  if (!pickup.value) return null
  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(pickup.value)}`
})

const priceLine = computed(() => {
  const pence = props.lesson?.price_pence
  if (pence == null || pence <= 0) return null
  return `£${(pence / 100).toFixed(0)}`
})

const savedNotes = computed(() => props.lesson?.instructor_notes?.trim() || '')

const canEditNotes = computed(() =>
  !!props.lesson && (props.lesson.status === 'scheduled' || props.lesson.status === 'completed'),
)

watch(
  () => props.lesson?.id,
  () => {
    notesOpen.value = false
    notesDraft.value = props.lesson?.instructor_notes || ''
  },
  { immediate: true },
)

const canRecordPayment = computed(() => {
  const l = props.lesson
  if (!l) return false
  if (l.status === 'cancelled') return false
  return l.settlement === 'outstanding' && (l.price_pence ?? 0) > 0
})

const payAmountLabel = computed(() => {
  const pence = props.lesson?.price_pence ?? 0
  return `£${(pence / 100).toFixed(0)}`
})

function openNotesEditor() {
  notesDraft.value = props.lesson?.instructor_notes || ''
  notesOpen.value = true
}

function cancelNotesEditor() {
  notesOpen.value = false
  notesDraft.value = props.lesson?.instructor_notes || ''
}

async function saveNotes() {
  if (!props.lesson || !canEditNotes.value) return
  notesSaving.value = true
  error.value = ''
  try {
    await updateLesson(props.lesson.id, {
      instructor_notes: notesDraft.value.trim() || null,
    })
    notesOpen.value = false
    emit('changed', 'Notes saved')
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not save notes.'
  } finally {
    notesSaving.value = false
  }
}

type ActionDef = {
  id: string
  label: string
  kind: 'primary' | 'secondary' | 'ghost' | 'danger'
  run: () => void | Promise<void>
}

const primaryActions = computed<ActionDef[]>(() => {
  if (props.slotPick?.pendingStartsAtLocal) {
    return [{
      id: 'confirm-slot',
      label: props.slotPick.kind === 'suggest' ? 'Confirm new time' : 'Confirm move',
      kind: 'primary',
      run: () => emit('confirmPickSlot'),
    }]
  }
  if (props.slotPick) {
    return [{
      id: 'cancel-pick',
      label: 'Cancel picking',
      kind: 'ghost',
      run: () => emit('cancelPickSlot'),
    }]
  }

  if (isRequest.value && props.request) {
    return [
      {
        id: 'approve',
        label: 'Approve',
        kind: 'primary',
        run: onApprove,
      },
      {
        id: 'choose-time',
        label: 'Choose another time',
        kind: 'secondary',
        run: () => emit('startPickSlot', 'suggest'),
      },
    ]
  }

  const l = props.lesson
  if (!l) return []

  if (l.status === 'cancelled' || l.status === 'no_show') {
    return [
      {
        id: 'book-another',
        label: 'Book another',
        kind: 'primary',
        run: onBookNext,
      },
    ]
  }

  if (l.status === 'completed') {
    const actions: ActionDef[] = [
      {
        id: 'recap',
        label: 'View recap',
        kind: 'primary',
        run: onOpenFull,
      },
      {
        id: 'book-next',
        label: 'Book next',
        kind: 'secondary',
        run: onBookNext,
      },
    ]
    if (canRecordPayment.value) {
      actions.splice(1, 0, {
        id: 'pay',
        label: 'Record payment',
        kind: 'secondary',
        run: () => { payOpen.value = true },
      })
    }
    return actions
  }

  // scheduled
  if (l.can_complete || l.is_current) {
    const actions: ActionDef[] = [
      {
        id: 'complete',
        label: 'Complete lesson',
        kind: 'primary',
        run: onComplete,
      },
      {
        id: 'book-next',
        label: 'Book next',
        kind: 'secondary',
        run: onBookNext,
      },
    ]
    if (canRecordPayment.value) {
      actions.push({
        id: 'pay',
        label: 'Record payment',
        kind: 'ghost',
        run: () => { payOpen.value = true },
      })
    }
    return actions
  }

  const actions: ActionDef[] = [
    {
      id: 'move',
      label: 'Move',
      kind: 'primary',
      run: () => emit('startPickSlot', 'move'),
    },
    {
      id: 'book-next',
      label: 'Book next',
      kind: 'secondary',
      run: onBookNext,
    },
  ]
  if (canRecordPayment.value) {
    actions.push({
      id: 'pay',
      label: 'Record payment',
      kind: 'secondary',
      run: () => { payOpen.value = true },
    })
  } else {
    actions.push({
      id: 'open-pupil',
      label: 'Open pupil',
      kind: 'ghost',
      run: onOpenPupil,
    })
  }
  return actions
})

const menuActions = computed<ActionDef[]>(() => {
  if (props.slotPick) return []
  if (isRequest.value) {
    return [{
      id: 'decline',
      label: 'Decline request',
      kind: 'danger',
      run: () => { confirmDecline.value = true; menuOpen.value = false },
    }]
  }
  const l = props.lesson
  if (!l) return []
  const items: ActionDef[] = [
    {
      id: 'open-pupil',
      label: 'Open pupil',
      kind: 'ghost',
      run: () => { menuOpen.value = false; onOpenPupil() },
    },
    {
      id: 'open-full',
      label: 'Open full lesson',
      kind: 'ghost',
      run: () => { menuOpen.value = false; onOpenFull() },
    },
  ]
  if (l.status === 'scheduled') {
    items.push({
      id: 'cancel',
      label: 'Cancel lesson',
      kind: 'danger',
      run: () => { confirmCancel.value = true; menuOpen.value = false },
    })
    items.push({
      id: 'remove',
      label: 'Remove mistaken booking',
      kind: 'danger',
      run: () => { confirmRemove.value = true; menuOpen.value = false },
    })
  }
  return items
})

async function withBusy(fn: () => Promise<void>) {
  if (busy.value) return
  busy.value = true
  error.value = ''
  try {
    await fn()
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Something went wrong.'
  } finally {
    busy.value = false
  }
}

async function onApprove() {
  if (!props.request) return
  await withBusy(async () => {
    await accept(props.request!.id)
    emit('changed', 'Lesson approved')
  })
}

async function onDeclineConfirm() {
  if (!props.request) return
  await withBusy(async () => {
    await decline(props.request!.id, declineReason.value.trim() || undefined)
    confirmDecline.value = false
    emit('changed', 'Request declined')
  })
}

async function onComplete() {
  if (!props.lesson) return
  // Full teaching workflow lives on the lesson page
  await navigateTo(`/lessons/${props.lesson.id}`)
}

function onBookNext() {
  emit('bookNext')
}

function onOpenPupil() {
  const learnerId = props.lesson?.learner_id || props.request?.learner_id
  if (!learnerId) return
  void navigateTo(`/pupils/${learnerId}`)
}

function onOpenFull() {
  if (!props.lesson) return
  void navigateTo(`/lessons/${props.lesson.id}`)
}

async function onCancelConfirm() {
  if (!props.lesson) return
  await withBusy(async () => {
    await cancelLesson(props.lesson!.id, 'this')
    confirmCancel.value = false
    emit('changed', 'Lesson cancelled')
  })
}

async function onRemoveConfirm() {
  if (!props.lesson) return
  await withBusy(async () => {
    // No hard-delete API — soft-cancel with no charge for mistaken bookings.
    await cancelLesson(props.lesson!.id, 'this', { charge: 'waived' })
    confirmRemove.value = false
    emit('changed', 'Booking removed')
  })
}

async function onRecordPayment() {
  if (!props.lesson || !canRecordPayment.value) return
  const method = payMethod.value === 'card' ? 'other' : payMethod.value
  await withBusy(async () => {
    await recordPayment(props.lesson!.learner_id, {
      amount_pence: props.lesson!.price_pence!,
      method,
      notes: payMethod.value === 'card' ? 'Card' : undefined,
    })
    payOpen.value = false
    emit('changed', 'Payment recorded')
  })
}

function closeMenus() {
  menuOpen.value = false
}

onMounted(() => {
  document.addEventListener('pointerdown', onDocPointer)
})
onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onDocPointer)
})

function onDocPointer(e: PointerEvent) {
  const t = e.target as HTMLElement | null
  if (!t?.closest?.('.appt__menu')) menuOpen.value = false
}

// Expose suggest confirm for parent after slot pick
async function applySuggest(startsAtLocal: string) {
  if (!props.request) return
  await withBusy(async () => {
    await suggest(props.request!.id, startsAtLocal)
    emit('changed', 'New time sent')
  })
}

async function applyMove(startsAtLocal: string) {
  if (!props.lesson) return
  await withBusy(async () => {
    await updateLesson(props.lesson!.id, {
      learner_id: props.lesson!.learner_id,
      starts_at_local: startsAtLocal,
      duration_minutes: props.lesson!.duration_minutes,
      pickup_address: props.lesson!.pickup_address,
    })
    emit('changed', 'Lesson moved')
  })
}

defineExpose({ applySuggest, applyMove, closeMenus })
</script>

<template>
  <div class="appt" :data-request="isRequest ? 'yes' : 'no'" :data-status="lesson?.status || 'request'">
    <header class="appt__chrome">
      <div class="appt__chrome-start">
        <p class="appt__status" :data-tone="bookingStatus.tone">{{ bookingStatus.label }}</p>
        <div v-if="menuActions.length && !slotPick" class="appt__menu">
          <button
            class="appt__more"
            type="button"
            aria-label="More actions"
            :aria-expanded="menuOpen"
            :disabled="busy"
            @click="menuOpen = !menuOpen"
          >
            <OlIcon name="more" :size="18" />
          </button>
          <div v-if="menuOpen" class="appt__menu-list" role="menu">
            <button
              v-for="item in menuActions"
              :key="item.id"
              type="button"
              class="appt__menu-item"
              :data-danger="item.kind === 'danger' ? 'yes' : 'no'"
              role="menuitem"
              @click="item.run()"
            >
              {{ item.label }}
            </button>
          </div>
        </div>
      </div>
      <button class="appt__close" type="button" aria-label="Close" @click="emit('close')">
        <OlIcon name="close" :size="16" />
      </button>
    </header>

    <div class="appt__scroll">
      <button class="appt__person" type="button" @click="onOpenPupil">
        <span class="appt__avatar" aria-hidden="true">{{ initials }}</span>
        <span class="appt__person-text">
          <span class="appt__name">{{ name }}</span>
          <span v-if="whenSummary" class="appt__when">{{ whenSummary }}</span>
        </span>
        <span class="appt__chev" aria-hidden="true">›</span>
      </button>

      <p v-if="slotPick && !slotPick.pendingStartsAtLocal" class="appt__pick-hint" role="status">
        Tap a free slot on the diary for
        {{ slotPick.kind === 'suggest' ? name : 'the new time' }}.
      </p>

      <div v-if="slotPick?.pendingStartsAtLocal" class="appt__pending" role="status">
        <p class="appt__pending-label">New time</p>
        <p class="appt__pending-value">{{ slotPick.pendingLabel || slotPick.pendingStartsAtLocal }}</p>
        <p v-if="whenDay" class="appt__pending-was">instead of {{ whenDay }} · {{ detailsSummary }}</p>
      </div>

      <template v-if="!slotPick?.pendingStartsAtLocal">
        <p
          v-if="travelNote"
          class="appt__alert"
          :data-tone="travelIsWarning ? 'warn' : 'info'"
          role="status"
        >
          {{ travelNote }}
        </p>

        <p v-if="lesson?.overlaps" class="appt__alert" data-tone="warn" role="status">
          This booking overlaps another lesson.
        </p>

        <dl class="appt__dl">
          <div v-if="nextFocus" class="appt__row">
            <dt>Focus</dt>
            <dd class="appt__row-strong">{{ nextFocus }}</dd>
          </div>
          <div v-if="pickup" class="appt__row">
            <dt>Pickup</dt>
            <dd>
              <span>{{ pickup }}</span>
              <a
                v-if="directionsHref"
                class="appt__directions"
                :href="directionsHref"
                target="_blank"
                rel="noopener noreferrer"
              >Directions</a>
            </dd>
          </div>
          <div v-if="transmissionLabel" class="appt__row">
            <dt>Car</dt>
            <dd>{{ transmissionLabel }}</dd>
          </div>
          <div v-if="priceLine || settlementQuiet" class="appt__row">
            <dt>Fee</dt>
            <dd>
              <template v-if="priceLine">{{ priceLine }}</template>
              <span v-if="settlementQuiet" class="appt__settlement" :data-settlement="lesson?.settlement || ''">
                {{ settlementQuiet }}
              </span>
            </dd>
          </div>
          <div v-if="lastFocus" class="appt__row">
            <dt>Last time</dt>
            <dd>{{ lastFocus }}</dd>
          </div>
          <div v-if="testLine" class="appt__row">
            <dt>Test</dt>
            <dd>
              {{ testLine.label }}
              <template v-if="testLine.lessonsBefore != null">
                · {{ testLine.lessonsBefore }} lesson{{ testLine.lessonsBefore === 1 ? '' : 's' }} booked before
              </template>
            </dd>
          </div>
        </dl>

        <section v-if="canEditNotes || savedNotes" class="appt__notes">
          <div class="appt__notes-head">
            <p class="appt__notes-label">Notes</p>
            <button
              v-if="canEditNotes && !notesOpen"
              class="appt__notes-add"
              type="button"
              @click="openNotesEditor"
            >
              {{ savedNotes ? 'Edit' : 'Add' }}
            </button>
          </div>

          <div v-if="notesOpen" class="appt__notes-edit">
            <textarea
              v-model="notesDraft"
              class="ol-textarea"
              rows="4"
              maxlength="2000"
              placeholder="Private notes for this lesson"
              :disabled="notesSaving"
            />
            <div class="appt__inline-actions">
              <button class="ol-btn ol-btn--sm" type="button" :disabled="notesSaving" @click="saveNotes">
                {{ notesSaving ? 'Saving…' : 'Save notes' }}
              </button>
              <button class="ol-btn ol-btn--ghost ol-btn--sm" type="button" :disabled="notesSaving" @click="cancelNotesEditor">
                Cancel
              </button>
            </div>
          </div>
          <p v-else-if="savedNotes" class="appt__notes-body">{{ savedNotes }}</p>
          <p v-else class="appt__notes-empty">Nothing noted yet.</p>
        </section>
      </template>

      <p v-if="error" class="appt__error" role="alert">{{ error }}</p>

      <div v-if="payOpen && lesson" class="appt__sheet" role="group" aria-label="Record payment">
        <p class="appt__sheet-title">Record payment</p>
        <p class="appt__sheet-amount">{{ payAmountLabel }}</p>
        <p class="appt__sheet-meta">{{ formatDuration(lesson.duration_minutes) }} lesson</p>
        <div class="appt__pay-methods" role="radiogroup" aria-label="Payment method">
          <button
            v-for="m in [
              { id: 'bank_transfer', label: 'Bank transfer' },
              { id: 'cash', label: 'Cash' },
              { id: 'card', label: 'Card' },
              { id: 'other', label: 'Other' },
            ]"
            :key="m.id"
            type="button"
            class="appt__pay-method"
            :class="{ 'appt__pay-method--on': payMethod === m.id }"
            :aria-pressed="payMethod === m.id"
            @click="payMethod = m.id as typeof payMethod"
          >
            {{ m.label }}
          </button>
        </div>
        <div class="appt__inline-actions">
          <button class="ol-btn ol-btn--sm" type="button" :disabled="busy" @click="onRecordPayment">
            Record {{ payAmountLabel }}
          </button>
          <button class="ol-btn ol-btn--ghost ol-btn--sm" type="button" :disabled="busy" @click="payOpen = false">
            Back
          </button>
        </div>
      </div>

      <div v-else-if="confirmCancel" class="appt__sheet" role="alertdialog" aria-label="Cancel lesson">
        <p class="appt__sheet-title">Cancel this lesson?</p>
        <p class="appt__sheet-meta">Keeps a cancelled record on the diary.</p>
        <div class="appt__inline-actions">
          <button class="ol-btn ol-btn--sm" type="button" :disabled="busy" @click="onCancelConfirm">
            Cancel lesson
          </button>
          <button class="ol-btn ol-btn--ghost ol-btn--sm" type="button" :disabled="busy" @click="confirmCancel = false">
            Keep it
          </button>
        </div>
      </div>

      <div v-else-if="confirmRemove" class="appt__sheet" role="alertdialog" aria-label="Remove booking">
        <p class="appt__sheet-title">Remove this booking?</p>
        <p class="appt__sheet-meta">Use only if it was created by mistake. This cancels it with no charge.</p>
        <div class="appt__inline-actions">
          <button class="ol-btn ol-btn--sm" type="button" :disabled="busy" @click="onRemoveConfirm">
            Remove booking
          </button>
          <button class="ol-btn ol-btn--ghost ol-btn--sm" type="button" :disabled="busy" @click="confirmRemove = false">
            Keep it
          </button>
        </div>
      </div>

      <div v-else-if="confirmDecline" class="appt__sheet" role="alertdialog" aria-label="Decline request">
        <p class="appt__sheet-title">Decline this request?</p>
        <label class="ol-field">
          <span class="ol-field__label">Reason (optional)</span>
          <input v-model="declineReason" class="ol-input" type="text" maxlength="120" placeholder="Optional note">
        </label>
        <div class="appt__inline-actions">
          <button class="ol-btn ol-btn--sm" type="button" :disabled="busy" @click="onDeclineConfirm">
            Decline
          </button>
          <button class="ol-btn ol-btn--ghost ol-btn--sm" type="button" :disabled="busy" @click="confirmDecline = false">
            Back
          </button>
        </div>
      </div>
    </div>

    <footer
      v-if="!payOpen && !confirmCancel && !confirmRemove && !confirmDecline"
      class="appt__foot"
    >
      <button
        v-for="action in primaryActions"
        :key="action.id"
        type="button"
        class="ol-btn ol-btn--sm"
        :class="{
          'ol-btn--ghost': action.kind === 'ghost' || action.kind === 'secondary',
          'appt__cta': action.kind === 'primary',
        }"
        :disabled="busy"
        @click="action.run()"
      >
        {{ action.label }}
      </button>
    </footer>
  </div>
</template>

<style scoped>
.appt {
  display: flex;
  flex-direction: column;
  min-height: 0;
  height: 100%;
  flex: 1;
}

.appt__chrome {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  flex-shrink: 0;
  min-height: 32px;
  margin-bottom: 10px;
}

.appt__chrome-start {
  display: flex;
  align-items: center;
  gap: 2px;
  min-width: 0;
}

.appt__status {
  margin: 0;
  font-size: var(--text-body-sm);
  font-weight: 600;
  color: var(--color-bark);
  white-space: nowrap;
}

.appt__status[data-tone='request'] {
  color: var(--color-diary-offer-ink);
}

.appt__status[data-tone='live'] {
  color: var(--color-ownlane-green);
}

.appt__status[data-tone='warn'] {
  color: var(--color-warning);
}

.appt__status[data-tone='muted'] {
  color: var(--color-muted);
}

.appt__close,
.appt__more {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: var(--color-muted);
  cursor: pointer;
  flex-shrink: 0;
}

.appt__close:hover,
.appt__more:hover {
  background: color-mix(in srgb, var(--color-border) 70%, transparent);
  color: var(--color-ink-black);
}

.appt__menu {
  position: relative;
  flex-shrink: 0;
}

.appt__menu-list {
  position: absolute;
  left: 0;
  top: calc(100% + 4px);
  min-width: 180px;
  padding: 6px;
  border-radius: 12px;
  border: 1px solid var(--color-border);
  background: var(--color-paper-white);
  box-shadow: 0 8px 24px color-mix(in srgb, var(--color-ink-black) 10%, transparent);
  display: flex;
  flex-direction: column;
  z-index: 6;
}

.appt__menu-item {
  text-align: left;
  border: none;
  background: transparent;
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 13px;
  color: var(--color-ink-black);
  cursor: pointer;
}

.appt__menu-item:hover {
  background: var(--color-parchment);
}

.appt__menu-item[data-danger='yes'] {
  color: var(--color-danger);
}

.appt__scroll {
  flex: 1;
  min-height: 0;
  overflow: auto;
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding-bottom: 8px;
}

.appt__person {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 0;
  border: none;
  background: transparent;
  text-align: left;
  cursor: pointer;
  color: inherit;
  font: inherit;
}

.appt__avatar {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
  background: color-mix(in srgb, var(--color-bubblegum-pink) 40%, white);
  color: #6b3a45;
}

.appt[data-request='yes'] .appt__avatar {
  background: color-mix(in srgb, var(--color-diary-offer) 65%, white);
  color: var(--color-diary-offer-ink);
}

.appt__person-text {
  min-width: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.appt__name {
  font-size: var(--text-body);
  font-weight: 600;
  color: var(--color-ink-black);
  line-height: 1.25;
}

.appt__when {
  font-size: var(--text-body-sm);
  color: var(--color-muted);
  font-variant-numeric: tabular-nums;
  line-height: 1.35;
}

.appt__chev {
  color: var(--color-ash-mist);
  font-size: 1.25rem;
  line-height: 1;
}

.appt__alert {
  margin: 0;
  padding: 10px 12px;
  border-radius: var(--radius-panel);
  border: 1px solid var(--color-border);
  font-size: var(--text-body-sm);
  line-height: 1.4;
  background: var(--color-paper-white);
  color: var(--color-bark);
}

.appt__alert[data-tone='warn'] {
  background: var(--color-warning-wash);
  border-color: color-mix(in srgb, var(--color-hi-yellow) 40%, var(--color-border));
  color: var(--color-warning);
}

.appt__alert[data-tone='info'] {
  background: color-mix(in srgb, var(--color-diary-block) 35%, white);
  color: var(--color-diary-block-ink);
}

.appt__dl {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.appt__row {
  display: grid;
  grid-template-columns: 5.5rem minmax(0, 1fr);
  gap: 8px;
  font-size: var(--text-body-sm);
  line-height: 1.4;
}

.appt__row dt {
  margin: 0;
  color: var(--color-muted);
}

.appt__row dd {
  margin: 0;
  color: var(--color-ink-black);
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 6px 10px;
}

.appt__row-strong {
  font-weight: 600;
}

.appt__directions {
  font-size: var(--text-caption);
  font-weight: 600;
  color: var(--color-ownlane-green);
  text-decoration: none;
}

.appt__directions:hover {
  text-decoration: underline;
}

.appt__settlement {
  font-weight: 600;
  color: var(--color-muted);
}

.appt__settlement[data-settlement='outstanding'] {
  color: var(--color-diary-unpaid-ink);
}

.appt__settlement[data-settlement='paid'] {
  color: var(--color-diary-paid-ink);
}

.appt__settlement[data-settlement='package'] {
  color: var(--color-diary-break-ink);
}

.appt__notes {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 12px;
  border-top: 1px solid var(--color-border);
}

.appt__notes-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.appt__notes-label {
  margin: 0;
  font-size: 10px;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  font-weight: 600;
  color: var(--color-muted);
}

.appt__notes-add {
  border: none;
  background: transparent;
  color: var(--color-ownlane-green);
  font-size: var(--text-body-sm);
  font-weight: 600;
  cursor: pointer;
  padding: 0;
}

.appt__notes-body {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-bark);
  line-height: 1.45;
  white-space: pre-wrap;
}

.appt__notes-empty {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.appt__notes-edit {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.appt__error {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-danger);
}

.appt__pick-hint,
.appt__pending {
  padding: 12px;
  border-radius: var(--radius-panel);
  background: color-mix(in srgb, var(--color-ownlane-green) 8%, var(--color-paper-white));
  border: 1px dashed color-mix(in srgb, var(--color-ownlane-green) 35%, var(--color-border));
}

.appt__pick-hint {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-bark);
}

.appt__pending-label {
  margin: 0;
  font-size: 10px;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  font-weight: 600;
  color: var(--color-muted);
}

.appt__pending-value {
  margin: 4px 0 0;
  font-size: var(--text-body);
  font-weight: 600;
  color: var(--color-ink-black);
}

.appt__pending-was {
  margin: 6px 0 0;
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.appt__sheet {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  border-radius: var(--radius-panel);
  border: 1px solid var(--color-border);
  background: var(--color-paper-white);
}

.appt__sheet-title {
  margin: 0;
  font-weight: 600;
  color: var(--color-ink-black);
}

.appt__sheet-amount {
  margin: 0;
  font-size: 1.35rem;
  font-weight: 650;
  letter-spacing: -0.02em;
}

.appt__sheet-meta {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.appt__pay-methods {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px;
}

.appt__pay-method {
  min-height: 36px;
  border-radius: 20px;
  border: 1px solid var(--color-border);
  background: var(--color-paper-white);
  font-size: 13px;
  cursor: pointer;
}

.appt__pay-method--on {
  border-color: var(--color-ink-black);
  background: var(--color-parchment);
  font-weight: 600;
}

.appt__inline-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.appt__foot {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 12px 0 calc(14px + env(safe-area-inset-bottom, 0px));
  border-top: 1px solid var(--color-border);
  background: var(--color-parchment);
}

.appt__cta {
  flex: 1 1 100%;
}

.appt__foot .ol-btn:not(.appt__cta) {
  flex: 1 1 auto;
}
</style>
