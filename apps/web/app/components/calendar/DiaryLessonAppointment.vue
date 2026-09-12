<script setup lang="ts">
import type {
  BookingSuggestion,
  DiaryLesson,
  LessonContext,
  LessonContextAction,
  LessonMessage,
} from '~/composables/useLessons'
import type { BookingRequest } from '~/composables/usePortalBooking'
import { formatDuration } from '~/utils/calendar/timeGrid'
import { CANCELLATION_NOTICE_OPTIONS, formatCancellationNoticeLabel } from '~/utils/cancellationNotice'
import PickupMapCard from '~/components/calendar/PickupMapCard.vue'

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

export type BookNextPayload = {
  suggestion?: BookingSuggestion | null
}

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
  bookNext: [payload?: BookNextPayload]
}>()

const {
  updateLesson,
  cancelLesson,
  settleCancellation,
  markNoShowLesson,
  durationLabel,
  fetchLessonContext,
  listLessonMessages,
  postLessonMessage,
  acknowledgePickup,
} = useLessons()
const { recordPayment } = useFinance()
const { accept, decline, suggest } = useInstructorBooking()
const { mapsUrl } = useToday()

const busy = ref(false)
const error = ref('')
const menuOpen = ref(false)
const confirmCancel = ref(false)
const cancelNoticeHours = ref<number | null>(null)
const confirmRemove = ref(false)
const confirmDecline = ref(false)
const confirmSettle = ref(false)
const confirmNoShow = ref(false)
const noShowCharge = ref<'outstanding' | 'package' | 'waived'>('outstanding')
const payOpen = ref(false)
const payMethod = ref<'cash' | 'bank_transfer' | 'card' | 'other'>('bank_transfer')
const declineReason = ref('')
const notesOpen = ref(false)
const notesDraft = ref('')
const notesSaving = ref(false)

const panelView = ref<'lesson' | 'pupil'>('lesson')
const context = ref<LessonContext | null>(null)
const contextLoading = ref(false)
const contextFailed = ref(false)

const messages = ref<LessonMessage[]>([])
const messagesLoading = ref(false)
const chatDraft = ref('')
const chatSending = ref(false)
const ackingPickup = ref(false)
const focusDraft = ref('')
const focusSaving = ref(false)
const openSection = ref<'chat' | 'private' | 'last' | 'focus' | null>(null)

const isRequest = computed(() => !!props.request && !props.lesson)

const name = computed(() =>
  context.value?.pupil.name
  || props.lesson?.learner_name
  || props.request?.learner_name
  || 'Pupil',
)

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

const startTime = computed(() => {
  if (props.request) return props.request.starts_at_time || ''
  return props.lesson?.starts_at_time || props.lesson?.starts_at_local?.slice(11, 16) || ''
})

const endTime = computed(() => {
  if (props.request) return props.request.ends_at_time || ''
  return props.lesson?.ends_at_time || ''
})

const durationText = computed(() => {
  const mins = props.lesson?.duration_minutes || props.request?.duration_minutes
  if (!mins) return ''
  return formatDuration(mins)
})

const detailsSummary = computed(() => {
  const bits = [startTime.value && endTime.value ? `${startTime.value}–${endTime.value}` : startTime.value, durationText.value]
    .filter(Boolean)
  return bits.join(' · ')
})

const pickup = computed(() =>
  context.value?.pickup.address
  || props.lesson?.pickup_address
  || props.request?.pickup_address
  || null,
)

const pickupLocation = computed(() =>
  context.value?.pickup.location
  || props.lesson?.pickup_location
  || null,
)

const pickupChanged = computed(() =>
  !!(context.value?.pickup.changed || props.lesson?.pickup_changed),
)

const focusTags = computed(() =>
  context.value?.teaching.focus_tags
  || props.lesson?.focus_tags
  || [],
)

const travel = computed(() =>
  context.value?.travel_to_next || props.lesson?.travel_to_next || null,
)

const travelNote = computed(() => {
  const t = travel.value
  if (!t?.is_warning) return null
  return t.message || `${t.travel_minutes}m to next — tight`
})

const travelIsSevere = computed(() => travel.value?.severity === 'impossible')

const dateMeta = computed(() => {
  if (props.request) return props.request.starts_at_day || null
  const local = props.lesson?.starts_at_local
  if (!local || local.length < 10) return null
  const ymd = local.slice(0, 10)
  const now = new Date()
  const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
  if (ymd === today) return 'Today'
  const d = new Date(`${ymd}T12:00:00`)
  if (Number.isNaN(d.getTime())) return null
  return d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' })
})

const nextMetaLine = computed(() => {
  const n = context.value?.next_scheduled
  if (!n) return null
  const day = n.day_label || n.starts_at_display || null
  const time = n.starts_at_time || null
  if (!day && !time) return null
  return ['Next', day, time].filter(Boolean).join(' · ')
})

const patternMetaLine = computed(() => {
  const cadence = context.value?.continuity?.usual_cadence?.trim()
  if (!cadence) return null
  return cadence.toLowerCase().startsWith('usually') ? cadence : `Usually ${cadence}`
})

const timeRange = computed(() => {
  if (!startTime.value) return null
  return endTime.value ? `${startTime.value}–${endTime.value}` : startTime.value
})

const transmissionLabel = computed(() => {
  const raw = context.value?.pupil.transmission
    || props.lesson?.learner_transmission
    || null
  if (!raw || raw === 'either') return null
  if (raw === 'manual') return 'Manual'
  if (raw === 'automatic') return 'Automatic'
  return raw.charAt(0).toUpperCase() + raw.slice(1)
})

const lessonsSoFarLine = computed(() => {
  const count = context.value?.history.completed_count
  if (count == null) return null
  if (count <= 0) return 'First lesson'
  return `${ordinal(count)} lesson taken so far`
})

const pupilSubLine = computed(() => {
  const bits = [transmissionLabel.value, lessonsSoFarLine.value].filter(Boolean)
  return bits.length ? bits.join(' · ') : (patternMetaLine.value || null)
})

function ordinal(n: number): string {
  const j = n % 10
  const k = n % 100
  if (k >= 11 && k <= 13) return `${n}th`
  if (j === 1) return `${n}st`
  if (j === 2) return `${n}nd`
  if (j === 3) return `${n}rd`
  return `${n}th`
}

const fullPickupAddress = computed(() =>
  pickupLocation.value?.address || pickup.value || null,
)

const initials = computed(() => {
  const fromContext = context.value?.pupil.initials?.trim()
  if (fromContext) return fromContext.slice(0, 2).toUpperCase()
  const parts = name.value.trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) return '?'
  if (parts.length === 1) return parts[0]!.slice(0, 2).toUpperCase()
  return `${parts[0]![0] || ''}${parts[parts.length - 1]![0] || ''}`.toUpperCase()
})

function toggleSection(id: 'chat' | 'private' | 'last' | 'focus') {
  openSection.value = openSection.value === id ? null : id
}

const testBanner = computed(() => {
  const t = context.value?.test_journey || props.lesson?.test_journey
  if (!t?.countdown_label) return null
  const centre = 'test_centre' in t && t.test_centre ? String(t.test_centre) : null
  return centre ? `${t.countdown_label} · ${centre}` : t.countdown_label
})

const teachingFocus = computed(() => {
  const t = context.value?.teaching
  return t?.learner_next_focus || t?.lesson_next_focus || props.lesson?.learner_next_focus || props.lesson?.next_focus || null
})

const teachingLast = computed(() => {
  const t = context.value?.teaching
  const summary = t?.learner_last_lesson_summary
    || t?.previous_completed?.learner_summary
    || props.lesson?.learner_last_lesson_summary
    || null
  if (!summary) return null
  const day = t?.previous_completed?.starts_at_day || null
  return { summary, day }
})

function formatMessageTime(iso: string): string {
  const d = new Date(iso.includes('T') ? iso : `${iso.replace(' ', 'T')}Z`)
  if (Number.isNaN(d.getTime())) return ''
  return d.toLocaleString('en-GB', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const moneyLine = computed(() => {
  const fin = context.value?.finance
  if (!fin) {
    const l = props.lesson
    if (l?.settlement === 'outstanding' && l.price_pence) {
      return `£${(l.price_pence / 100).toFixed(0)} due`
    }
    return null
  }
  if (fin.lesson_settlement === 'outstanding' && props.lesson?.price_pence) {
    return `£${(props.lesson.price_pence / 100).toFixed(0)} due`
  }
  if (fin.credit_covers_duration) {
    const line = fin.snapshot.teaching_line || fin.snapshot.credit_remaining_line
    if (line) return line
    return 'Covered by lesson credit'
  }
  if (fin.snapshot.owes_money && fin.snapshot.amount_owed_label) {
    return fin.snapshot.amount_owed_label
  }
  return null
})

const metaTiles = computed(() => {
  const tiles: {
    id: string
    label: string
    value: string
    sub?: string | null
    icon: 'clock' | 'diary' | 'money' | 'pin'
  }[] = []

  const day = dateMeta.value || (whenDay.value ? whenDay.value.replace(/,.*/, '') : null)
  const whenSubBits = [timeRange.value, durationText.value].filter(Boolean)
  if (day || whenSubBits.length) {
    tiles.push({
      id: 'when',
      label: 'When',
      value: day || whenSubBits.join(' · '),
      sub: day && whenSubBits.length ? whenSubBits.join(' · ') : null,
      icon: 'diary',
    })
  }

  const due =
    props.lesson?.settlement === 'outstanding'
    || context.value?.finance.lesson_settlement === 'outstanding'
  const next = context.value?.next_scheduled
  const hours = context.value?.history.teaching_hours_label?.trim() || null
  const completed = context.value?.history.completed_count
  const syllabus = context.value?.history.syllabus_line?.trim() || null

  if (due && moneyLine.value) {
    tiles.push({ id: 'money', label: 'To collect', value: moneyLine.value, icon: 'money' })
  } else if (hours || (completed != null && completed > 0)) {
    tiles.push({
      id: 'hours',
      label: 'Driven so far',
      value: hours || `${completed} lessons`,
      sub: syllabus,
      icon: 'clock',
    })
  } else if (next && (next.day_label || next.starts_at_time)) {
    tiles.push({
      id: 'next',
      label: 'Next lesson',
      value: next.day_label || next.starts_at_display || next.starts_at_time,
      sub: next.day_label && next.starts_at_time ? next.starts_at_time : null,
      icon: 'clock',
    })
  } else if (patternMetaLine.value) {
    tiles.push({
      id: 'pattern',
      label: 'Pattern',
      value: patternMetaLine.value.replace(/^Usually\s+/i, ''),
      icon: 'diary',
    })
  } else if (pickupLocation.value?.label) {
    tiles.push({ id: 'place', label: 'Place', value: pickupLocation.value.label, icon: 'pin' })
  }

  return tiles.slice(0, 2)
})

const TAG_TONES = ['mint', 'sage', 'amber', 'lilac', 'sky', 'rose', 'peach'] as const

function tagTone(tag: string): (typeof TAG_TONES)[number] {
  let hash = 0
  for (let i = 0; i < tag.length; i++) {
    hash = (hash * 31 + tag.charCodeAt(i)) >>> 0
  }
  return TAG_TONES[hash % TAG_TONES.length]!
}

const directionsHref = computed(() => (pickup.value ? mapsUrl(pickup.value) : null))

const savedNotes = computed(() =>
  context.value?.teaching.instructor_notes?.trim()
  || props.lesson?.instructor_notes?.trim()
  || '',
)

const canEditNotes = computed(() =>
  !!props.lesson && (props.lesson.status === 'scheduled' || props.lesson.status === 'completed'),
)

const canRecordPayment = computed(() => {
  const l = props.lesson
  if (!l) return false
  if (l.status === 'cancelled') return false
  if (l.settlement === 'outstanding' && (l.price_pence ?? 0) > 0) return true
  return !!context.value?.finance.snapshot.owes_money && l.status === 'completed'
})

const pupilFirstName = computed(() => {
  const full = name.value.trim()
  return full.split(/\s+/)[0] || 'Pupil'
})

const canUsePackageCredit = computed(() => {
  const mins = props.lesson?.duration_minutes ?? 0
  const credit = context.value?.finance.snapshot.credit_minutes ?? 0
  return mins > 0 && credit >= mins
})

const packageCreditLabel = computed(() => durationLabel(props.lesson?.duration_minutes ?? 0))

const cancellationNoticeLine = computed(() => {
  const l = props.lesson
  if (!l || l.status !== 'cancelled') return null
  return l.cancellation_notice_label
    || formatCancellationNoticeLabel(l.cancellation_notice_hours)
})

const cancellationStatusLine = computed(() => {
  const l = props.lesson
  if (!l || l.status !== 'cancelled') return null
  const who = l.cancelled_by === 'learner' ? 'Pupil cancelled' : 'Cancelled'
  const notice = l.cancellation_notice_label
  if (notice) return `${who} with ${notice}`
  if (l.cancellation_reason) return `${who} · ${l.cancellation_reason}`
  return who
})

const payAmountLabel = computed(() => {
  const pence = props.lesson?.price_pence ?? 0
  if (pence > 0) return `£${(pence / 100).toFixed(0)}`
  const owed = context.value?.finance.snapshot.amount_owed_pence ?? 0
  return owed > 0 ? `£${(owed / 100).toFixed(0)}` : '£0'
})

const pupilLearning = computed(() => {
  const t = context.value?.teaching
  return {
    focus: t?.learner_next_focus || null,
    last: t?.learner_last_lesson_summary || null,
  }
})

const pupilPattern = computed(() => {
  const c = context.value?.continuity
  const h = context.value?.history
  const parts: string[] = []
  if (c?.usual_cadence) parts.push(`Usually ${c.usual_cadence}`)
  if (h && h.completed_count > 0) {
    let line = `${h.completed_count} lesson${h.completed_count === 1 ? '' : 's'} together`
    if (h.teaching_hours_label) line += ` · ${h.teaching_hours_label}`
    parts.push(line)
  }
  if (c?.detail && c.needs_attention) parts.push(c.detail)
  return parts
})

const pupilTest = computed(() => {
  const t = context.value?.test_journey
  if (!t?.countdown_label) return null
  return t.countdown_label
})

const pupilCredit = computed(() => {
  const s = context.value?.finance.snapshot
  if (!s?.has_prepaid_credit) return null
  return s.credit_remaining_line || s.credit_label || null
})

async function loadContext(id: number) {
  contextLoading.value = true
  contextFailed.value = false
  try {
    context.value = await fetchLessonContext(id)
  } catch {
    context.value = null
    contextFailed.value = true
  } finally {
    contextLoading.value = false
  }
}

async function loadMessages(id: number) {
  messagesLoading.value = true
  try {
    messages.value = await listLessonMessages(id)
  } catch {
    messages.value = []
  } finally {
    messagesLoading.value = false
  }
}

watch(
  () => props.lesson?.id,
  (id) => {
    panelView.value = 'lesson'
    notesOpen.value = false
    notesDraft.value = props.lesson?.instructor_notes || ''
    chatDraft.value = ''
    focusDraft.value = ''
    openSection.value = null
    context.value = null
    contextFailed.value = false
    messages.value = []
    if (id) {
      void loadContext(id)
      void loadMessages(id)
    }
  },
  { immediate: true },
)

watch(
  () => props.request?.id,
  () => {
    panelView.value = 'lesson'
    context.value = null
  },
)

function openPupilView() {
  if (isRequest.value) {
    onOpenPupilFull()
    return
  }
  panelView.value = 'pupil'
}

function backToLesson() {
  panelView.value = 'lesson'
}

function openNotesEditor() {
  notesDraft.value = props.lesson?.instructor_notes || context.value?.teaching.instructor_notes || ''
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
    if (context.value) {
      context.value = {
        ...context.value,
        teaching: { ...context.value.teaching, instructor_notes: notesDraft.value.trim() || null },
      }
    }
    emit('changed', 'Notes saved')
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not save notes.'
  } finally {
    notesSaving.value = false
  }
}

async function sendChat() {
  if (!props.lesson || !chatDraft.value.trim()) return
  chatSending.value = true
  error.value = ''
  try {
    const msg = await postLessonMessage(props.lesson.id, chatDraft.value.trim())
    messages.value = [...messages.value, msg]
    chatDraft.value = ''
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not send that note.'
  } finally {
    chatSending.value = false
  }
}

async function onAcknowledgePickup() {
  if (!props.lesson) return
  ackingPickup.value = true
  error.value = ''
  try {
    await acknowledgePickup(props.lesson.id)
    if (context.value) {
      context.value = {
        ...context.value,
        pickup: { ...context.value.pickup, changed: false },
      }
    }
    emit('changed')
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not clear that alert.'
  } finally {
    ackingPickup.value = false
  }
}

async function commitFocusTag() {
  if (!props.lesson) return
  const tag = focusDraft.value.trim()
  if (!tag) return
  const next = [...focusTags.value]
  if (!next.includes(tag)) next.push(tag)
  focusSaving.value = true
  error.value = ''
  try {
    await updateLesson(props.lesson.id, { focus_tags: next })
    focusDraft.value = ''
    if (context.value) {
      context.value = {
        ...context.value,
        teaching: { ...context.value.teaching, focus_tags: next },
      }
    }
    emit('changed')
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not save that focus.'
  } finally {
    focusSaving.value = false
  }
}

async function removeFocusTag(tag: string) {
  if (!props.lesson) return
  const next = focusTags.value.filter(t => t !== tag)
  focusSaving.value = true
  error.value = ''
  try {
    await updateLesson(props.lesson.id, { focus_tags: next })
    if (context.value) {
      context.value = {
        ...context.value,
        teaching: { ...context.value.teaching, focus_tags: next },
      }
    }
    emit('changed')
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not update focus.'
  } finally {
    focusSaving.value = false
  }
}

type ActionDef = {
  id: string
  label: string
  kind: 'primary' | 'secondary' | 'ghost' | 'danger'
  run: () => void | Promise<void>
}

function runAction(id: string) {
  switch (id) {
    case 'complete':
      return onComplete()
    case 'move':
      return emit('startPickSlot', 'move')
    case 'book_next':
      return onBookNext()
    case 'recap':
    case 'open_full':
      return onOpenFull()
    case 'pay':
      payOpen.value = true
      return
    case 'decide_charge':
      confirmSettle.value = true
      menuOpen.value = false
      return
    case 'no_show':
      openNoShowConfirm()
      return
    case 'cancel':
      cancelNoticeHours.value = null
      confirmCancel.value = true
      menuOpen.value = false
      return
    case 'remove':
      confirmRemove.value = true
      menuOpen.value = false
      return
    case 'open_pupil':
      menuOpen.value = false
      return openPupilView()
    default:
      return
  }
}

function mapActions(items: LessonContextAction[], kind: ActionDef['kind']): ActionDef[] {
  return items.map((a) => ({
    id: a.id,
    label: a.label,
    kind,
    run: () => runAction(a.id),
  }))
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
      { id: 'approve', label: 'Approve', kind: 'primary', run: onApprove },
      {
        id: 'choose-time',
        label: 'Choose another time',
        kind: 'secondary',
        run: () => emit('startPickSlot', 'suggest'),
      },
    ]
  }

  if (panelView.value === 'pupil') {
    return [{
      id: 'open-pupil-full',
      label: 'Open full pupil',
      kind: 'primary',
      run: onOpenPupilFull,
    }]
  }

  const actions = context.value?.actions
  if (actions) {
    return [
      ...mapActions(actions.primary, 'primary'),
      ...mapActions(actions.secondary, 'secondary'),
    ]
  }

  // Fallback when context failed / still loading
  const l = props.lesson
  if (!l) return []
  if (l.status === 'cancelled' || l.status === 'no_show') {
    return [{ id: 'book_next', label: 'Book another', kind: 'primary', run: onBookNext }]
  }
  if (l.status === 'completed') {
    return [
      { id: 'recap', label: 'View recap', kind: 'primary', run: onOpenFull },
      { id: 'book_next', label: 'Book next', kind: 'secondary', run: onBookNext },
    ]
  }
  return [
    { id: 'move', label: 'Move', kind: 'primary', run: () => emit('startPickSlot', 'move') },
    { id: 'book_next', label: 'Book next', kind: 'secondary', run: onBookNext },
  ]
})

const menuActions = computed<ActionDef[]>(() => {
  if (props.slotPick || panelView.value === 'pupil') return []
  if (isRequest.value) {
    return [{
      id: 'decline',
      label: 'Decline request',
      kind: 'danger',
      run: () => { confirmDecline.value = true; menuOpen.value = false },
    }]
  }
  const overflow = context.value?.actions.overflow
  if (overflow?.length) {
    return overflow
      .filter((a) => a.id !== 'open_pupil' || panelView.value === 'lesson')
      .map((a) => ({
        id: a.id,
        label: a.label,
        kind: (a.id === 'cancel' || a.id === 'remove' || a.id === 'no_show' ? 'danger' : 'ghost') as ActionDef['kind'],
        run: () => runAction(a.id),
      }))
  }
  const l = props.lesson
  if (!l) return []
  const items: ActionDef[] = [
    { id: 'open_pupil', label: 'Open full pupil', kind: 'ghost', run: () => { menuOpen.value = false; onOpenPupilFull() } },
    { id: 'open_full', label: 'Open full lesson', kind: 'ghost', run: () => { menuOpen.value = false; onOpenFull() } },
  ]
  if (l.status === 'scheduled') {
    if (l.can_mark_no_show) {
      items.push({
        id: 'no_show',
        label: 'Mark no-show',
        kind: 'danger',
        run: openNoShowConfirm,
      })
    }
    items.push(
      {
        id: 'cancel',
        label: 'Cancel lesson',
        kind: 'danger',
        run: () => {
          cancelNoticeHours.value = null
          confirmCancel.value = true
          menuOpen.value = false
        },
      },
      { id: 'remove', label: 'Remove mistaken booking', kind: 'danger', run: () => { confirmRemove.value = true; menuOpen.value = false } },
    )
  }
  return items
})

function openNoShowConfirm() {
  noShowCharge.value = 'outstanding'
  confirmNoShow.value = true
  menuOpen.value = false
}

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
  await navigateTo(`/lessons/${props.lesson.id}`)
}

function onBookNext() {
  emit('bookNext', {
    suggestion: context.value?.booking_suggestion || null,
  })
}

function onOpenPupilFull() {
  const learnerId = context.value?.pupil.id || props.lesson?.learner_id || props.request?.learner_id
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
    const notice = cancelNoticeHours.value
    await cancelLesson(
      props.lesson!.id,
      'this',
      notice != null ? { cancellation_notice_hours: notice } : {},
    )
    confirmCancel.value = false
    cancelNoticeHours.value = null
    emit('changed', 'Lesson cancelled')
  })
}

async function onRemoveConfirm() {
  if (!props.lesson) return
  await withBusy(async () => {
    await cancelLesson(props.lesson!.id, 'this', { charge: 'waived' })
    confirmRemove.value = false
    emit('changed', 'Booking removed')
  })
}

async function onSettleConfirm(charge: 'outstanding' | 'waived') {
  if (!props.lesson) return
  await withBusy(async () => {
    await settleCancellation(props.lesson!.id, charge)
    confirmSettle.value = false
    emit('changed', charge === 'waived' ? 'Cancellation waived' : 'Cancellation charged')
  })
}

async function onNoShowConfirm(charge: 'outstanding' | 'package' | 'waived') {
  if (!props.lesson) return
  await withBusy(async () => {
    await markNoShowLesson(props.lesson!.id, { charge })
    confirmNoShow.value = false
    const message =
      charge === 'waived'
        ? 'Marked no-show · no charge'
        : charge === 'package'
          ? 'Marked no-show · credit used'
          : 'Marked no-show · charged'
    emit('changed', message)
  })
}

async function onRecordPayment() {
  if (!props.lesson || !canRecordPayment.value) return
  const method = payMethod.value === 'card' ? 'other' : payMethod.value
  const amount = props.lesson.price_pence
    || context.value?.finance.snapshot.amount_owed_pence
    || 0
  if (amount <= 0) return
  await withBusy(async () => {
    await recordPayment(props.lesson!.learner_id, {
      amount_pence: amount,
      method,
      notes: payMethod.value === 'card' ? 'Card' : undefined,
    })
    payOpen.value = false
    emit('changed', 'Payment recorded')
    if (props.lesson?.id) await loadContext(props.lesson.id)
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
  <div
    class="appt"
    :data-request="isRequest ? 'yes' : 'no'"
    :data-status="lesson?.status || 'request'"
    :data-view="panelView"
  >
    <header class="appt__top">
      <div v-if="panelView === 'pupil'" class="appt__top-start">
        <button
          class="appt__back"
          type="button"
          @click="backToLesson"
        >
          ← Lesson
        </button>
      </div>

      <div v-else class="appt__identity appt__identity--top">
        <button class="appt__avatar appt__avatar--btn" type="button" :aria-label="`Open ${name}`" @click="openPupilView">
          {{ initials }}
        </button>
        <div class="appt__identity-text">
          <button class="appt__name-btn" type="button" @click="openPupilView">
            <span class="appt__name">{{ name }}</span>
            <OlIcon name="chevron-right" :size="16" class="appt__name-chev" />
          </button>
          <p v-if="pupilSubLine" class="appt__quiet">{{ pupilSubLine }}</p>
        </div>
      </div>

      <div class="appt__top-actions">
        <div v-if="panelView === 'lesson' && menuActions.length && !slotPick" class="appt__menu">
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
        <button class="appt__close" type="button" aria-label="Close" @click="emit('close')">
          <OlIcon name="close" :size="16" />
        </button>
      </div>
    </header>

    <div class="appt__scroll">
      <!-- Pupil view -->
      <div v-if="panelView === 'pupil'" class="appt__stack">
        <div class="appt__identity">
          <span class="appt__avatar" aria-hidden="true">{{ initials }}</span>
          <div class="appt__identity-text">
            <h2 class="appt__name">{{ name }}</h2>
            <p
              v-if="context?.pupil.transmission && context.pupil.transmission !== 'either'"
              class="appt__quiet"
            >
              {{ context.pupil.transmission === 'manual' ? 'Manual' : context.pupil.transmission === 'automatic' ? 'Automatic' : context.pupil.transmission }}
            </p>
          </div>
        </div>

        <div v-if="pupilLearning.focus" class="appt__inset">
          <p class="appt__inset-label">Learning now</p>
          <p class="appt__inset-body">{{ pupilLearning.focus }}</p>
        </div>
        <p v-else-if="pupilLearning.last" class="appt__quiet">Last: {{ pupilLearning.last }}</p>

        <div v-if="pupilPattern.length" class="appt__group">
          <p class="appt__group-title">Pattern</p>
          <div class="appt__sheet-card">
            <p v-for="(line, i) in pupilPattern" :key="i" class="appt__row-body">{{ line }}</p>
          </div>
        </div>

        <div v-if="context?.history.coming_up?.length" class="appt__group">
          <p class="appt__group-title">Coming up</p>
          <div class="appt__sheet-card">
            <p
              v-for="item in context.history.coming_up"
              :key="item.id"
              class="appt__row-body"
            >
              {{ item.day_label }} · {{ item.starts_at_time }} · {{ formatDuration(item.duration_minutes) }}
            </p>
          </div>
        </div>

        <div v-if="context?.history.recent_completed?.length" class="appt__group">
          <p class="appt__group-title">Recent</p>
          <div class="appt__sheet-card">
            <p
              v-for="item in context.history.recent_completed"
              :key="item.id"
              class="appt__row-body"
            >
              {{ item.day_label }} · {{ formatDuration(item.duration_minutes) }}
            </p>
          </div>
        </div>

        <p v-if="pupilTest" class="appt__quiet">{{ pupilTest }}</p>
        <p v-if="pupilCredit" class="appt__quiet">{{ pupilCredit }}</p>
      </div>

      <!-- Lesson view -->
      <div v-else class="appt__stack">
        <div v-if="metaTiles.length" class="appt__tiles">
          <div v-for="tile in metaTiles" :key="tile.id" class="appt__tile">
            <p class="appt__tile-label">
              <OlIcon :name="tile.icon" :size="12" />
              {{ tile.label }}
            </p>
            <p class="appt__tile-value">{{ tile.value }}</p>
            <p v-if="tile.sub" class="appt__tile-sub">{{ tile.sub }}</p>
          </div>
        </div>

        <div v-if="contextLoading && !context" class="appt__skeleton" aria-hidden="true">
          <span class="appt__skel" />
          <span class="appt__skel appt__skel--short" />
        </div>

        <p v-if="slotPick && !slotPick.pendingStartsAtLocal" class="appt__pick-hint" role="status">
          Tap a free slot on the diary for
          {{ slotPick.kind === 'suggest' ? name : 'the new time' }}.
        </p>

        <div v-if="slotPick?.pendingStartsAtLocal" class="appt__pending" role="status">
          <p class="appt__inset-label">New time</p>
          <p class="appt__inset-body">{{ slotPick.pendingLabel || slotPick.pendingStartsAtLocal }}</p>
          <p v-if="whenDay" class="appt__quiet">instead of {{ whenDay }} · {{ detailsSummary }}</p>
        </div>

        <template v-if="!slotPick?.pendingStartsAtLocal">
          <p
            v-if="travelNote"
            class="appt__banner"
            :data-severe="travelIsSevere ? 'yes' : 'no'"
            role="status"
          >
            <OlIcon name="warning" :size="16" />
            <span>{{ travelNote }}</span>
          </p>

          <p v-if="lesson?.overlaps" class="appt__banner" data-severe="no" role="status">
            <OlIcon name="warning" :size="16" />
            <span>This booking overlaps another lesson.</span>
          </p>

          <p v-if="testBanner" class="appt__banner appt__banner--test" role="status">
            <OlIcon name="test" :size="16" />
            <span>{{ testBanner }}</span>
          </p>

          <p v-if="cancellationStatusLine" class="appt__banner" data-severe="no" role="status">
            <OlIcon name="warning" :size="16" />
            <span>{{ cancellationStatusLine }}</span>
          </p>

          <!-- Pickup map card -->
          <section class="appt__pickup-section">
            <div
              v-if="pickupChanged"
              class="appt__pickup-alert"
              role="status"
            >
              <p>Pupil changed pickup for this lesson</p>
              <button
                class="appt__pickup-ack"
                type="button"
                :disabled="ackingPickup || busy"
                @click="onAcknowledgePickup"
              >
                {{ ackingPickup ? 'Clearing…' : 'Got it' }}
              </button>
            </div>
            <PickupMapCard
              v-if="fullPickupAddress"
              :address="fullPickupAddress"
              :label="pickupLocation?.label || null"
              :maps-href="directionsHref || mapsUrl(fullPickupAddress)"
            />
            <p v-else class="appt__quiet appt__quiet--pad">No pickup set</p>
          </section>

          <!-- Expandable sections list -->
          <section v-if="lesson" class="appt__group">
            <p class="appt__group-title">Lesson details</p>
            <div class="appt__sheet-card">
              <!-- Shared notes -->
              <button
                class="appt__nav"
                type="button"
                :aria-expanded="openSection === 'chat'"
                @click="toggleSection('chat')"
              >
                <span class="appt__nav-icon" data-tone="green">
                  <OlIcon name="message" :size="15" />
                </span>
                <span class="appt__nav-label">Shared notes</span>
                <span v-if="messages.length" class="appt__nav-badge">{{ messages.length }}</span>
                <OlIcon name="chevron-right" :size="16" class="appt__nav-chev" :data-open="openSection === 'chat' ? 'yes' : 'no'" />
              </button>
              <div v-if="openSection === 'chat'" class="appt__nav-panel">
                <p class="appt__quiet">Visible to this pupil</p>
                <p v-if="messagesLoading && !messages.length" class="appt__quiet">Loading…</p>
                <ul v-else-if="messages.length" class="appt__chat-list">
                  <li
                    v-for="msg in messages"
                    :key="msg.id"
                    class="appt__chat-item"
                    :data-role="msg.author_role"
                  >
                    <p class="appt__chat-meta">
                      {{ msg.author_role === 'instructor' ? 'You' : 'Pupil' }}
                      <span v-if="formatMessageTime(msg.created_at)"> · {{ formatMessageTime(msg.created_at) }}</span>
                    </p>
                    <p class="appt__chat-body">{{ msg.body }}</p>
                  </li>
                </ul>
                <p v-else class="appt__quiet">Nothing shared on this lesson yet.</p>
                <div class="appt__compose">
                  <textarea
                    v-model="chatDraft"
                    class="ol-textarea"
                    rows="2"
                    maxlength="2000"
                    placeholder="Note for this pupil about the lesson"
                    :disabled="chatSending || busy"
                    @keydown.meta.enter.prevent="sendChat"
                  />
                  <button
                    class="ol-btn ol-btn--sm"
                    type="button"
                    :disabled="chatSending || busy || !chatDraft.trim()"
                    @click="sendChat"
                  >
                    {{ chatSending ? 'Sending…' : 'Send' }}
                  </button>
                </div>
              </div>

              <!-- Private notes -->
              <button
                v-if="canEditNotes || savedNotes"
                class="appt__nav"
                type="button"
                :aria-expanded="openSection === 'private'"
                @click="toggleSection('private')"
              >
                <span class="appt__nav-icon" data-tone="purple">
                  <OlIcon name="accounts" :size="15" />
                </span>
                <span class="appt__nav-label">Private notes</span>
                <span v-if="savedNotes" class="appt__nav-dot" aria-hidden="true" />
                <OlIcon name="chevron-right" :size="16" class="appt__nav-chev" :data-open="openSection === 'private' ? 'yes' : 'no'" />
              </button>
              <div v-if="openSection === 'private' && (canEditNotes || savedNotes)" class="appt__nav-panel">
                <p class="appt__quiet">Only you see these</p>
                <div v-if="notesOpen" class="appt__compose">
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
                <template v-else>
                  <p v-if="savedNotes" class="appt__row-body">{{ savedNotes }}</p>
                  <button
                    v-if="canEditNotes"
                    class="appt__text-btn"
                    type="button"
                    @click="openNotesEditor"
                  >
                    {{ savedNotes ? 'Edit notes' : 'Add notes' }}
                  </button>
                </template>
              </div>

              <!-- Last lesson -->
              <button
                v-if="teachingLast"
                class="appt__nav"
                type="button"
                :aria-expanded="openSection === 'last'"
                @click="toggleSection('last')"
              >
                <span class="appt__nav-icon" data-tone="amber">
                  <OlIcon name="lesson" :size="15" />
                </span>
                <span class="appt__nav-label">Last lesson</span>
                <OlIcon name="chevron-right" :size="16" class="appt__nav-chev" :data-open="openSection === 'last' ? 'yes' : 'no'" />
              </button>
              <div v-if="openSection === 'last' && teachingLast" class="appt__nav-panel">
                <p class="appt__row-body">
                  <span v-if="teachingLast.day">{{ teachingLast.day }} · </span>{{ teachingLast.summary }}
                </p>
                <p v-if="teachingFocus" class="appt__quiet">Next focus: {{ teachingFocus }}</p>
              </div>

              <!-- This lesson focus -->
              <button
                v-if="lesson.status === 'scheduled' || lesson.status === 'completed' || focusTags.length"
                class="appt__nav"
                type="button"
                :aria-expanded="openSection === 'focus'"
                @click="toggleSection('focus')"
              >
                <span class="appt__nav-icon" data-tone="blue">
                  <OlIcon name="lightning" :size="15" />
                </span>
                <span class="appt__nav-label">Suggested focus</span>
                <span v-if="focusTags.length" class="appt__nav-badge">{{ focusTags.length }}</span>
                <OlIcon name="chevron-right" :size="16" class="appt__nav-chev" :data-open="openSection === 'focus' ? 'yes' : 'no'" />
              </button>
              <div
                v-if="openSection === 'focus' && (lesson.status === 'scheduled' || lesson.status === 'completed' || focusTags.length)"
                class="appt__nav-panel"
              >
                <p class="appt__quiet">
                  Ideas for this lesson, not a fixed plan. Your pupil can add theirs too.
                </p>
                <div v-if="focusTags.length" class="appt__tags">
                  <button
                    v-for="tag in focusTags"
                    :key="tag"
                    type="button"
                    class="appt__tag"
                    :data-tone="tagTone(tag)"
                    :disabled="focusSaving || busy"
                    :title="`Remove ${tag}`"
                    @click="removeFocusTag(tag)"
                  >
                    {{ tag }}
                    <span aria-hidden="true">×</span>
                  </button>
                </div>
                <form class="appt__tag-form" @submit.prevent="commitFocusTag">
                  <input
                    v-model="focusDraft"
                    class="ol-input"
                    type="text"
                    maxlength="40"
                    placeholder="Add a suggestion"
                    :disabled="focusSaving || busy"
                  >
                  <button
                    class="ol-btn ol-btn--sm ol-btn--ghost"
                    type="submit"
                    :disabled="focusSaving || busy || !focusDraft.trim()"
                  >
                    Add
                  </button>
                </form>
              </div>
            </div>
          </section>
        </template>
      </div>

      <p v-if="error" class="appt__error" role="alert">{{ error }}</p>

      <div v-if="payOpen && lesson" class="appt__confirm" role="group" aria-label="Record payment">
        <p class="appt__confirm-title">Record payment</p>
        <p class="appt__confirm-amount">{{ payAmountLabel }}</p>
        <p class="appt__quiet">{{ formatDuration(lesson.duration_minutes) }} lesson</p>
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

      <div v-else-if="confirmCancel" class="appt__confirm" role="alertdialog" aria-label="Cancel lesson">
        <p class="appt__confirm-title">Cancel this lesson?</p>
        <p class="appt__quiet">Keeps a cancelled record on the diary.</p>
        <p class="appt__inset-label">Notice given</p>
        <div class="appt__pay-methods" role="radiogroup" aria-label="Notice given">
          <button
            v-for="opt in CANCELLATION_NOTICE_OPTIONS"
            :key="opt.hours"
            type="button"
            class="appt__pay-method"
            :class="{ 'appt__pay-method--on': cancelNoticeHours === opt.hours }"
            :aria-pressed="cancelNoticeHours === opt.hours"
            :disabled="busy"
            @click="cancelNoticeHours = cancelNoticeHours === opt.hours ? null : opt.hours"
          >
            {{ opt.label }}
          </button>
        </div>
        <p class="appt__quiet">Optional. Tap again to clear.</p>
        <div class="appt__inline-actions">
          <button class="ol-btn ol-btn--sm" type="button" :disabled="busy" @click="onCancelConfirm">Cancel lesson</button>
          <button
            class="ol-btn ol-btn--ghost ol-btn--sm"
            type="button"
            :disabled="busy"
            @click="confirmCancel = false; cancelNoticeHours = null"
          >
            Keep it
          </button>
        </div>
      </div>

      <div v-else-if="confirmSettle" class="appt__confirm" role="alertdialog" aria-label="Decide cancellation charge">
        <p class="appt__confirm-title">Charge for this cancellation?</p>
        <p class="appt__quiet">
          <template v-if="lesson?.cancelled_by === 'learner'">
            Pupil cancelled
            <template v-if="cancellationNoticeLine"> with {{ cancellationNoticeLine }}</template>
            <template v-else-if="lesson?.cancellation_reason"> · {{ lesson.cancellation_reason }}</template>
          </template>
          <template v-else>
            Decide whether this lesson should still be charged.
            <template v-if="cancellationNoticeLine"> · {{ cancellationNoticeLine }}</template>
          </template>
        </p>
        <div class="appt__inline-actions">
          <button class="ol-btn ol-btn--sm" type="button" :disabled="busy" @click="onSettleConfirm('outstanding')">
            Charge lesson
          </button>
          <button class="ol-btn ol-btn--ghost ol-btn--sm" type="button" :disabled="busy" @click="onSettleConfirm('waived')">
            Don’t charge
          </button>
          <button class="ol-btn ol-btn--ghost ol-btn--sm" type="button" :disabled="busy" @click="confirmSettle = false">
            Back
          </button>
        </div>
      </div>

      <div v-else-if="confirmNoShow" class="appt__confirm" role="alertdialog" aria-label="Mark no-show">
        <p class="appt__confirm-title">{{ pupilFirstName }} didn’t attend?</p>
        <p class="appt__quiet">How should this lesson be handled?</p>
        <div class="appt__pay-methods" role="radiogroup" aria-label="No-show charge">
          <button
            type="button"
            class="appt__pay-method"
            :class="{ 'appt__pay-method--on': noShowCharge === 'outstanding' }"
            :aria-pressed="noShowCharge === 'outstanding'"
            :disabled="busy"
            @click="noShowCharge = 'outstanding'"
          >
            Charge lesson
          </button>
          <button
            v-if="canUsePackageCredit"
            type="button"
            class="appt__pay-method"
            :class="{ 'appt__pay-method--on': noShowCharge === 'package' }"
            :aria-pressed="noShowCharge === 'package'"
            :disabled="busy"
            @click="noShowCharge = 'package'"
          >
            Use {{ packageCreditLabel }} credit
          </button>
          <button
            type="button"
            class="appt__pay-method"
            :class="{ 'appt__pay-method--on': noShowCharge === 'waived' }"
            :aria-pressed="noShowCharge === 'waived'"
            :disabled="busy"
            @click="noShowCharge = 'waived'"
          >
            Don’t charge
          </button>
        </div>
        <div class="appt__inline-actions">
          <button
            class="ol-btn ol-btn--sm"
            type="button"
            :disabled="busy"
            @click="onNoShowConfirm(noShowCharge)"
          >
            Confirm no-show
          </button>
          <button
            class="ol-btn ol-btn--ghost ol-btn--sm"
            type="button"
            :disabled="busy"
            @click="confirmNoShow = false"
          >
            Back
          </button>
        </div>
      </div>

      <div v-else-if="confirmRemove" class="appt__confirm" role="alertdialog" aria-label="Remove booking">
        <p class="appt__confirm-title">Remove this booking?</p>
        <p class="appt__quiet">Use only if it was created by mistake. This cancels it with no charge.</p>
        <div class="appt__inline-actions">
          <button class="ol-btn ol-btn--sm" type="button" :disabled="busy" @click="onRemoveConfirm">Remove booking</button>
          <button class="ol-btn ol-btn--ghost ol-btn--sm" type="button" :disabled="busy" @click="confirmRemove = false">Keep it</button>
        </div>
      </div>

      <div v-else-if="confirmDecline" class="appt__confirm" role="alertdialog" aria-label="Decline request">
        <p class="appt__confirm-title">Decline this request?</p>
        <label class="ol-field">
          <span class="ol-field__label">Reason (optional)</span>
          <input v-model="declineReason" class="ol-input" type="text" maxlength="120" placeholder="Optional note">
        </label>
        <div class="appt__inline-actions">
          <button class="ol-btn ol-btn--sm" type="button" :disabled="busy" @click="onDeclineConfirm">Decline</button>
          <button class="ol-btn ol-btn--ghost ol-btn--sm" type="button" :disabled="busy" @click="confirmDecline = false">Back</button>
        </div>
      </div>
    </div>

    <footer
      v-if="!payOpen && !confirmCancel && !confirmRemove && !confirmDecline && !confirmSettle && !confirmNoShow"
      class="appt__foot"
    >
      <div class="appt__foot-actions">
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
      </div>
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

.appt__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  flex-shrink: 0;
  margin: -2px 0 12px;
}

.appt__top-start {
  display: flex;
  align-items: center;
  min-width: 0;
  margin-right: auto;
}

.appt__top-actions {
  display: flex;
  align-items: flex-start;
  gap: 0;
  flex-shrink: 0;
  margin-left: auto;
}

.appt__identity--top {
  flex: 1;
  min-width: 0;
  padding-right: 4px;
}

.appt__back {
  border: none;
  background: transparent;
  padding: 4px 0;
  font-size: var(--text-body-sm);
  font-weight: 600;
  color: var(--color-ownlane-green);
  cursor: pointer;
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
  right: 0;
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

.appt__stack {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.appt__identity {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.appt__avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 14px;
  background: var(--color-paper-white);
  border: 1px solid var(--color-border);
  color: var(--color-ink-black);
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.02em;
  flex-shrink: 0;
}

.appt__avatar--btn {
  padding: 0;
  cursor: pointer;
  font: inherit;
}

.appt__avatar--btn:hover {
  border-color: var(--color-driftwood);
}

.appt__identity-text {
  min-width: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.appt__name-btn {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  max-width: 100%;
  margin: 0;
  padding: 0;
  border: none;
  background: transparent;
  text-align: left;
  cursor: pointer;
  color: inherit;
  font: inherit;
}

.appt__name {
  margin: 0;
  font-family: var(--font-haas-grot-text);
  font-size: 1.2rem;
  line-height: 1.2;
  letter-spacing: -0.02em;
  font-weight: 700;
  color: var(--color-ink-black);
}

.appt__name-chev {
  color: var(--color-ash-mist);
  flex-shrink: 0;
}

.appt__time-line {
  margin: 0;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-bark);
}

.appt__quiet {
  margin: 0;
  font-size: 12px;
  line-height: 1.4;
  color: var(--color-muted);
}

.appt__tiles {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.appt__tile {
  padding: 12px;
  border-radius: 14px;
  background: color-mix(in srgb, var(--color-soft-sage, #dfe8d8) 35%, var(--color-paper-white));
  border: 1px solid transparent;
}

.appt__tile-label {
  margin: 0;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  color: var(--color-muted);
}

.appt__tile-value {
  margin: 6px 0 0;
  font-size: 14px;
  font-weight: 700;
  color: var(--color-ink-black);
  line-height: 1.2;
  word-break: break-word;
}

.appt__tile-sub {
  margin: 4px 0 0;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-bark);
  line-height: 1.2;
}

.appt__pickup-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.appt__quiet--pad {
  padding: 14px;
  border-radius: 14px;
  background: var(--color-paper-white);
  border: 1px solid var(--color-border);
}

.appt__pickup-alert {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 10px;
  background: var(--color-warning-wash);
  color: var(--color-warning);
  font-size: var(--text-meta);
}

.appt__pickup-alert p {
  margin: 0;
}

.appt__pickup-ack {
  border: none;
  background: transparent;
  color: var(--color-warning);
  font-weight: 700;
  font-size: 12px;
  cursor: pointer;
  padding: 0;
}

.appt__group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.appt__group-title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: var(--color-ink-black);
  letter-spacing: -0.01em;
}

.appt__sheet-card {
  display: flex;
  flex-direction: column;
  border-radius: 18px;
  background: color-mix(in srgb, var(--color-border) 45%, var(--color-paper-white));
  overflow: hidden;
}

.appt__nav {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  margin: 0;
  padding: 14px 14px;
  border: none;
  background: transparent;
  text-align: left;
  cursor: pointer;
  color: inherit;
  font: inherit;
}

.appt__nav + .appt__nav,
.appt__nav-panel + .appt__nav {
  border-top: 1px solid color-mix(in srgb, var(--color-border) 80%, transparent);
}

.appt__nav-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 999px;
  flex-shrink: 0;
  color: var(--color-bark);
}

.appt__nav-icon[data-tone='green'] {
  background: color-mix(in srgb, var(--color-ownlane-green) 16%, white);
  color: var(--color-ownlane-green);
}

.appt__nav-icon[data-tone='sage'] {
  background: color-mix(in srgb, var(--color-soft-sage, #dfe8d8) 70%, white);
}

.appt__nav-icon[data-tone='purple'] {
  background: color-mix(in srgb, var(--color-diary-break) 45%, white);
  color: var(--color-diary-break-ink);
}

.appt__nav-icon[data-tone='amber'] {
  background: color-mix(in srgb, var(--color-warning-wash) 80%, white);
  color: var(--color-warning);
}

.appt__nav-icon[data-tone='blue'] {
  background: color-mix(in srgb, var(--color-diary-block, #b3d4ff) 55%, white);
  color: var(--color-diary-block-ink, #1a3a5c);
}

.appt__nav-label {
  flex: 1;
  min-width: 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink-black);
}

.appt__nav-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  border-radius: 999px;
  background: var(--color-ink-black);
  color: var(--color-paper-white);
  font-size: 11px;
  font-weight: 700;
}

.appt__nav-dot {
  width: 7px;
  height: 7px;
  border-radius: 999px;
  background: var(--color-ownlane-green);
  flex-shrink: 0;
}

.appt__nav-chev {
  color: var(--color-ash-mist);
  transition: transform 160ms ease;
}

.appt__nav-chev[data-open='yes'] {
  transform: rotate(90deg);
}

.appt__nav-panel {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 0 14px 14px 58px;
}

.appt__row-body {
  margin: 0;
  font-size: 13px;
  line-height: 1.45;
  color: var(--color-ink-black);
  white-space: pre-wrap;
}

.appt__inset {
  padding: 12px 14px;
  border-radius: 14px;
  background: var(--color-paper-white);
  border: 1px solid var(--color-border);
}

.appt__inset-label {
  margin: 0;
  font-size: 10px;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  font-weight: 600;
  color: var(--color-muted);
}

.appt__inset-body {
  margin: 4px 0 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink-black);
  line-height: 1.35;
}

.appt__banner {
  display: inline-flex;
  align-items: flex-start;
  gap: 8px;
  margin: 0;
  width: 100%;
  box-sizing: border-box;
  font-size: var(--text-meta);
  padding: 8px 10px;
  border-radius: 10px;
  background: var(--color-warning-wash);
  color: var(--color-warning);
  line-height: 1.4;
}

.appt__banner[data-severe='yes'] {
  background: var(--color-danger-wash);
  color: var(--color-danger);
}

.appt__banner--test {
  background: var(--color-warning-wash);
  color: var(--color-warning);
}

.appt__chat-list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 180px;
  overflow: auto;
}

.appt__chat-item {
  padding: 8px 10px;
  border-radius: 10px;
  background: var(--color-paper-white);
}

.appt__chat-item[data-role='learner'] {
  background: color-mix(in srgb, var(--color-ownlane-green) 8%, var(--color-paper-white));
}

.appt__chat-meta {
  margin: 0;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-muted);
}

.appt__chat-body {
  margin: 4px 0 0;
  font-size: var(--text-body-sm);
  color: var(--color-ink-black);
  line-height: 1.4;
  white-space: pre-wrap;
}

.appt__compose {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.appt__text-btn {
  align-self: flex-start;
  border: none;
  background: transparent;
  padding: 0;
  color: var(--color-ownlane-green);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.appt__tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.appt__tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: none;
  border-radius: 20px;
  padding: 6px 10px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.appt__tag[data-tone='mint'] {
  background: color-mix(in srgb, var(--color-ownlane-green) 14%, white);
  color: #146b43;
}

.appt__tag[data-tone='sage'] {
  background: color-mix(in srgb, var(--color-soft-sage) 55%, white);
  color: #3d5a45;
}

.appt__tag[data-tone='amber'] {
  background: color-mix(in srgb, var(--color-warning-wash) 90%, white);
  color: #8a5a12;
}

.appt__tag[data-tone='lilac'] {
  background: color-mix(in srgb, var(--color-diary-break) 40%, white);
  color: var(--color-diary-break-ink);
}

.appt__tag[data-tone='sky'] {
  background: color-mix(in srgb, var(--color-diary-block) 45%, white);
  color: var(--color-diary-block-ink);
}

.appt__tag[data-tone='rose'] {
  background: color-mix(in srgb, var(--color-diary-unpaid) 35%, white);
  color: var(--color-diary-unpaid-ink);
}

.appt__tag[data-tone='peach'] {
  background: color-mix(in srgb, #f0c9a8 45%, white);
  color: #7a4a28;
}

.appt__tag-form {
  display: flex;
  gap: 8px;
  align-items: center;
}

.appt__tag-form .ol-input {
  flex: 1;
  min-height: 40px;
  padding: 8px 12px;
}

.appt__skeleton {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.appt__skel {
  display: block;
  height: 12px;
  border-radius: 6px;
  background: color-mix(in srgb, var(--color-border) 80%, transparent);
  animation: appt-pulse 1.2s ease-in-out infinite;
}

.appt__skel--short {
  width: 55%;
}

@keyframes appt-pulse {
  0%, 100% { opacity: 0.55; }
  50% { opacity: 1; }
}

@media (prefers-reduced-motion: reduce) {
  .appt__skel,
  .appt__nav-chev {
    animation: none;
    transition: none;
  }
}

.appt__error {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-danger);
}

.appt__pick-hint,
.appt__pending {
  padding: 12px 14px;
  border-radius: 14px;
  background: color-mix(in srgb, var(--color-ownlane-green) 8%, var(--color-paper-white));
  border: 1px dashed color-mix(in srgb, var(--color-ownlane-green) 35%, var(--color-border));
}

.appt__pick-hint {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-bark);
}

.appt__confirm {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  border-radius: 16px;
  border: 1px solid var(--color-border);
  background: var(--color-paper-white);
}

.appt__confirm-title {
  margin: 0;
  font-weight: 600;
  color: var(--color-ink-black);
}

.appt__confirm-amount {
  margin: 0;
  font-size: var(--text-heading-sm);
  font-weight: 700;
}

.appt__pay-methods {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.appt__pay-method {
  border: 1px solid var(--color-border);
  background: var(--color-parchment);
  border-radius: 999px;
  padding: 8px 12px;
  font-size: 12px;
  cursor: pointer;
  color: var(--color-bark);
}

.appt__pay-method--on {
  background: color-mix(in srgb, var(--color-ownlane-green) 12%, white);
  border-color: var(--color-ownlane-green);
  color: var(--color-ownlane-green);
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
  flex-direction: column;
  gap: 10px;
  padding: 12px 0 14px;
  margin-top: auto;
  border-top: 1px solid var(--color-border);
}

.appt__foot-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.appt__foot-actions :deep(.ol-btn) {
  border-radius: 999px;
  min-height: 40px;
  padding: 8px 16px;
  font-weight: 600;
}

.appt__foot-actions :deep(.ol-btn--ghost) {
  background: var(--color-paper-white);
  border-color: var(--color-ink-black);
  color: var(--color-ink-black);
}

.appt__cta {
  flex: 1 1 auto;
}
</style>
