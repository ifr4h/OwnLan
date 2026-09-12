<template>
  <section class="page ol-page">
    <NuxtLink to="/today" class="ol-back">← Today</NuxtLink>

    <p v-if="loading" class="ol-muted">Loading…</p>
    <p v-else-if="error" class="ol-error" role="alert">{{ error }}</p>

    <template v-else-if="lesson">
      <p v-if="syncLabel" class="ol-meta">{{ syncLabel }}</p>

      <header class="hero ol-page-header__row">
        <div class="ol-page-header">
          <p class="ol-eyebrow">{{ statusLabel }}</p>
          <h1 class="ol-page-title">{{ lesson.learner_name }}</h1>
          <p class="ol-meta">{{ lesson.starts_at_display }}</p>
        </div>
        <NuxtLink
          v-if="lesson.status === 'scheduled' && online"
          :to="`/lessons/${lesson.id}/edit`"
          class="ol-btn ol-btn--ghost ol-btn--sm"
        >
          Edit
        </NuxtLink>
      </header>

      <section class="panel ol-panel">
        <dl class="facts">
          <div class="facts__row">
            <dt>Duration</dt>
            <dd>{{ durationLabel(lesson.duration_minutes) }}</dd>
          </div>
          <div class="facts__row">
            <dt>Pickup</dt>
            <dd>{{ lesson.pickup_address || 'Not set' }}</dd>
          </div>
          <div v-if="lesson.series_id" class="facts__row">
            <dt>Series</dt>
            <dd>Weekly recurring</dd>
          </div>
          <div v-if="lesson.status === 'cancelled' && cancellationNoticeDisplay" class="facts__row">
            <dt>Notice</dt>
            <dd>{{ cancellationNoticeDisplay }}</dd>
          </div>
          <div v-if="lesson.status === 'cancelled' && lesson.cancelled_by" class="facts__row">
            <dt>Cancelled by</dt>
            <dd>{{ cancelledByLabel }}</dd>
          </div>
          <div v-if="lesson.status === 'cancelled' && lesson.cancellation_reason" class="facts__row">
            <dt>Reason</dt>
            <dd>{{ lesson.cancellation_reason }}</dd>
          </div>
          <div v-if="contextSummary" class="facts__row">
            <dt>Last summary</dt>
            <dd>{{ contextSummary }}</dd>
          </div>
          <div v-if="contextFocus" class="facts__row">
            <dt>Next focus</dt>
            <dd>{{ contextFocus }}</dd>
          </div>
          <div
            v-if="lesson.status === 'scheduled' && financeSnapshot?.show_on_teaching"
            class="facts__row"
          >
            <dt>Payment</dt>
            <dd>{{ financeSnapshot.teaching_line }}</dd>
          </div>
        </dl>
        <p v-if="!online && lesson.pickup_address" class="maps-note ol-meta">
          Maps unavailable offline — pickup is shown above.
        </p>
      </section>

      <section v-if="lesson.test_journey" class="panel panel--test">
        <h2 class="panel__heading">Practical test</h2>
        <p class="test__countdown">{{ lesson.test_journey.countdown_label }}</p>
        <dl class="facts">
          <div v-if="testDateDisplay" class="facts__row">
            <dt>Date</dt>
            <dd>{{ testDateDisplay }}</dd>
          </div>
          <div v-if="lesson.test_journey.test_centre" class="facts__row">
            <dt>Centre</dt>
            <dd>{{ lesson.test_journey.test_centre }}</dd>
          </div>
          <div class="facts__row">
            <dt>Before test</dt>
            <dd>
              {{ lesson.test_journey.lessons_booked_before_test }}
              {{ lesson.test_journey.lessons_booked_before_test === 1 ? 'lesson' : 'lessons' }}
              booked
            </dd>
          </div>
          <div v-if="testProgress" class="facts__row">
            <dt>Progress</dt>
            <dd>{{ testProgress }}</dd>
          </div>
          <div v-if="lesson.test_journey.next_focus" class="facts__row">
            <dt>Priority</dt>
            <dd>{{ lesson.test_journey.next_focus }}</dd>
          </div>
        </dl>
      </section>

      <p class="pupil-link">
        <NuxtLink :to="`/pupils/${lesson.learner_id}`">View pupil →</NuxtLink>
      </p>

      <section v-if="lesson.status === 'scheduled'" class="panel ol-panel mock-entry">
        <h2 class="panel__heading">Mock test</h2>
        <p class="ol-meta">Record faults during a supervised mock. Only use when it is safe to do so.</p>
        <NuxtLink :to="`/lessons/${lesson.id}/mock`" class="ol-btn ol-btn--sm">
          Start mock test
        </NuxtLink>
      </section>

      <!-- Route recording (scheduled / in-progress) -->
      <section
        v-if="lesson.status === 'scheduled' || lesson.status === 'completed'"
        class="recording"
        aria-labelledby="recording-title"
      >
        <h2 id="recording-title" class="recording__title">{{ tRec('recording.title') }}</h2>

        <template v-if="isRecording">
          <p class="recording__timer" aria-live="polite">
            <span class="recording__dot" aria-hidden="true" />
            {{ tRec('recording.recording') }}
            <span class="recording__time">{{ recordingTimer }}</span>
          </p>
          <p class="recording__points ol-meta">
            {{ recordingPoints.length }} points sampled
          </p>
          <div class="recording__actions">
            <button
              class="btn btn--ghost"
              type="button"
              :disabled="recordingBusy || markingMoment"
              @click="onMarkMoment"
            >
              {{ markingMoment ? '…' : tTeach('moments.mark') }}
            </button>
            <button
              class="btn"
              type="button"
              :disabled="recordingBusy"
              @click="onStopRecording"
            >
              {{ tRec('recording.stop') }}
            </button>
          </div>
        </template>

        <template v-else-if="instructorRoute?.status === 'completed'">
          <p class="recording__saved">
            {{ tRec('recording.completed') }}
            <template v-if="instructorRoute.distance_label">
              · {{ instructorRoute.distance_label }}
            </template>
            <template v-if="instructorRoute.duration_label">
              · {{ instructorRoute.duration_label }}
            </template>
          </p>
          <p v-if="instructorRoute.learner_visible" class="recording__shared">
            {{ tRec('recording.shared') }}
          </p>
          <div class="recording__actions">
            <NuxtLink
              :to="`/lessons/${lesson.id}/replay`"
              class="btn btn--ghost"
            >
              Drive replay
            </NuxtLink>
            <button
              v-if="!instructorRoute.learner_visible"
              class="btn"
              type="button"
              :disabled="recordingBusy || !online"
              @click="onShareRoute"
            >
              {{ tRec('recording.share') }}
            </button>
            <button
              class="btn btn--ghost"
              type="button"
              :disabled="recordingBusy || !online"
              @click="onDiscardRoute"
            >
              {{ tRec('recording.discard') }}
            </button>
          </div>

          <section v-if="moments.length" class="moments" aria-labelledby="moments-title">
            <h3 id="moments-title" class="moments__title">{{ tTeach('moments.list') }}</h3>
            <ul class="moments__list">
              <li v-for="m in moments" :key="m.id" class="moments__item">
                <div class="moments__meta">
                  <span class="moments__offset">{{ m.offset_label || '—' }}</span>
                  <select
                    class="moments__select"
                    :value="m.kind"
                    @change="onMomentKind(m.id, ($event.target as HTMLSelectElement).value)"
                  >
                    <option value="review">{{ tTeach('moments.kinds.review') }}</option>
                    <option value="good">{{ tTeach('moments.kinds.good') }}</option>
                    <option value="explain">{{ tTeach('moments.kinds.explain') }}</option>
                    <option value="hazard">{{ tTeach('moments.kinds.hazard') }}</option>
                    <option value="roundabout">{{ tTeach('moments.kinds.roundabout') }}</option>
                    <option value="custom">{{ tTeach('moments.kinds.custom') }}</option>
                  </select>
                </div>
                <input
                  class="moments__input"
                  type="text"
                  :placeholder="tTeach('moments.label')"
                  :value="m.label || ''"
                  @change="onMomentField(m.id, 'label', ($event.target as HTMLInputElement).value)"
                >
                <textarea
                  class="moments__note"
                  rows="2"
                  :placeholder="tTeach('moments.learnerNote')"
                  :value="m.learner_note || ''"
                  @change="onMomentField(m.id, 'learner_note', ($event.target as HTMLTextAreaElement).value)"
                />
                <label class="moments__check">
                  <input
                    type="checkbox"
                    :checked="m.learner_visible"
                    @change="onMomentVisible(m.id, ($event.target as HTMLInputElement).checked)"
                  >
                  {{ tTeach('moments.learnerVisible') }}
                </label>
                <NuxtLink
                  class="moments__explain"
                  :to="`/teaching/board?lessonId=${lesson.id}&momentId=${m.id}&lat=${m.lat}&lng=${m.lng}&template=blank`"
                >
                  {{ tTeach('moments.explain') }} →
                </NuxtLink>
              </li>
            </ul>
          </section>
        </template>

        <template v-else-if="lesson.status === 'scheduled'">
          <button
            class="btn btn--ghost"
            type="button"
            :disabled="recordingBusy || !online"
            @click="onStartRecording"
          >
            {{ tRec('recording.start') }}
          </button>
        </template>

        <p v-if="recordingError" class="recording__error" role="alert">{{ recordingError }}</p>
      </section>

      <!-- Local-first completion -->
      <form
        v-if="lesson.status === 'scheduled'"
        class="complete"
        @submit.prevent="onComplete"
      >
        <h2 class="complete__title">How did this pupil do?</h2>
        <p class="complete__hint">
          Only capture what matters from this lesson. Nothing waits on the network.
        </p>

        <label class="field">
          <span class="field__label">Progress summary <span class="optional">(learner-visible)</span></span>
          <textarea v-model="learnerSummary" class="field__textarea" rows="2" />
        </label>

        <label class="field">
          <span class="field__label">Next focus</span>
          <textarea v-model="nextFocus" class="field__textarea" rows="2" placeholder="What to work on next" />
        </label>

        <label class="field">
          <span class="field__label">Private note <span class="optional">(optional)</span></span>
          <textarea v-model="instructorNotes" class="field__textarea" rows="2" />
        </label>

        <fieldset v-if="skillCatalogue.length" class="skills">
          <legend class="skills__legend">{{ tRec('skillsComplete.title') }}</legend>
          <p class="skills__hint">{{ tRec('skillsComplete.hint') }}</p>
          <div class="skills__chips">
            <button
              v-for="skill in flatSkills"
              :key="skill.id"
              type="button"
              class="skills__chip"
              :class="{ 'skills__chip--on': selectedSkillIds.includes(skill.id) }"
              :aria-pressed="selectedSkillIds.includes(skill.id)"
              @click="toggleSkill(skill.id)"
            >
              {{ skill.label }}
            </button>
          </div>
          <div v-if="selectedSkillIds.length" class="skills__ratings">
            <p class="skills__hint">{{ tRec('skillsComplete.ratingOptional') }}</p>
            <label
              v-for="sid in selectedSkillIds"
              :key="sid"
              class="skills__rating-row"
            >
              <span>{{ skillLabel(sid) }}</span>
              <select v-model="skillRatings[sid]" class="skills__select">
                <option value="">—</option>
                <option value="introduced">Introduced</option>
                <option value="practising">Practising</option>
                <option value="developing">Developing</option>
                <option value="confident">Feeling confident</option>
              </select>
            </label>
          </div>
        </fieldset>

        <button class="btn" type="submit" :disabled="completing">
          {{ completing ? 'Saving…' : 'Complete lesson' }}
        </button>
      </form>

      <div v-if="lesson.status === 'completed'" class="aftermath">
        <p class="aftermath__check">Lesson complete ✓</p>
        <template v-if="aftermath">
          <p class="aftermath__name">{{ aftermath.learner_first_name }}</p>
          <p class="aftermath__credit">{{ aftermath.credit_line }}</p>
          <p v-if="aftermath.owes_money && !aftermath.has_prepaid_credit" class="aftermath__owe">
            {{ aftermath.money_status_label }}
          </p>
          <div class="aftermath__actions ol-actions">
            <NuxtLink :to="aftermath.primary_cta_path" class="ol-btn ol-btn--sm">
              {{ aftermath.primary_cta_label }}
              <span aria-hidden="true">→</span>
            </NuxtLink>
            <NuxtLink
              v-if="aftermath.secondary_cta_path && aftermath.secondary_cta_label"
              :to="aftermath.secondary_cta_path"
              class="ol-btn ol-btn--ghost ol-btn--sm"
            >
              {{ aftermath.secondary_cta_label }}
            </NuxtLink>
          </div>
        </template>
        <template v-else>
          <p class="aftermath__credit">Saved on this device — credit updates when you’re back online.</p>
          <NuxtLink
            :to="`/lessons/new?learner_id=${lesson.learner_id}&from_lesson=${lesson.id}`"
            class="ol-btn ol-btn--sm"
          >
            Book next lesson
            <span aria-hidden="true">→</span>
          </NuxtLink>
        </template>

        <section v-if="lesson.learner_summary || lesson.next_focus || lesson.instructor_notes" class="panel panel--soft">
          <p v-if="lesson.learner_summary"><strong>Summary:</strong> {{ lesson.learner_summary }}</p>
          <p v-if="lesson.next_focus"><strong>Next focus:</strong> {{ lesson.next_focus }}</p>
          <p v-if="lesson.instructor_notes"><strong>Private:</strong> {{ lesson.instructor_notes }}</p>
        </section>
      </div>

      <section v-if="lesson.status === 'cancelled' && emptySeat?.applicable" class="recovery">
        <header class="recovery__head">
          <p class="recovery__eyebrow">Empty seat</p>
          <h2 class="recovery__title">{{ emptySeat.headline }}</h2>
          <p class="recovery__slot">{{ emptySeat.slot_label }}</p>
          <p class="recovery__summary">{{ emptySeat.match_summary }}</p>
        </header>

        <template v-if="emptySeat.best_match">
          <p class="recovery__best-label">Best match</p>
          <article class="recovery__card">
            <div>
              <p class="recovery__name">{{ emptySeat.best_match.learner_name }}</p>
              <ul class="recovery__reasons">
                <li v-for="reason in emptySeat.best_match.reasons" :key="reason">{{ reason }}</li>
              </ul>
            </div>
            <NuxtLink class="btn" :to="bookEmptySeatHref(emptySeat.best_match)">
              Book {{ emptySeat.best_match.learner_name.split(' ')[0] }}
            </NuxtLink>
          </article>

          <ul v-if="emptySeat.matches.length > 1" class="recovery__others">
            <li v-for="match in emptySeat.matches.slice(1)" :key="match.learner_id">
              <div>
                <p class="recovery__name recovery__name--sm">{{ match.learner_name }}</p>
                <p class="recovery__reason-line">{{ match.reasons.join(' · ') }}</p>
              </div>
              <NuxtLink class="btn btn--ghost" :to="bookEmptySeatHref(match)">Book</NuxtLink>
            </li>
          </ul>
        </template>
        <p v-else class="muted">No strong replacements right now — check the diary gap later.</p>
      </section>

      <!-- Mark no-show (online only — finance settlement) -->
      <section
        v-if="lesson.can_mark_no_show && !confirmNoShow"
        class="noshow"
      >
        <button
          class="btn btn--ghost"
          type="button"
          :disabled="!online || markingNoShow"
          @click="confirmNoShow = true"
        >
          Mark no-show
        </button>
        <p v-if="!online" class="muted">Marking no-show needs a connection.</p>
      </section>

      <section v-if="confirmNoShow" class="noshow-confirm">
        <h2 class="noshow-confirm__title">
          {{ pupilFirstName }} didn't attend?
        </h2>
        <p class="noshow-confirm__hint">How should this lesson be handled?</p>

        <fieldset class="noshow-confirm__options">
          <label class="radio">
            <input v-model="noShowCharge" type="radio" value="outstanding">
            <span>Charge lesson</span>
          </label>
          <label v-if="canUsePackageCredit" class="radio">
            <input v-model="noShowCharge" type="radio" value="package">
            <span>Use {{ packageCreditLabel }} credit</span>
          </label>
          <label class="radio">
            <input v-model="noShowCharge" type="radio" value="waived">
            <span>Don't charge</span>
          </label>
        </fieldset>

        <label class="field">
          <span class="field__label">Private note <span class="optional">(optional)</span></span>
          <textarea v-model="noShowNotes" class="field__textarea" rows="2" />
        </label>

        <div class="noshow-confirm__actions">
          <button class="btn" type="button" :disabled="markingNoShow" @click="onMarkNoShow">
            {{ markingNoShow ? 'Saving…' : 'Confirm no-show' }}
          </button>
          <button class="ghost" type="button" :disabled="markingNoShow" @click="confirmNoShow = false">
            Cancel
          </button>
        </div>
      </section>

      <div v-if="lesson.status === 'no_show'" class="aftermath">
        <p class="aftermath__check">Marked no-show</p>
        <template v-if="noShowAftermath">
          <p class="aftermath__name">{{ noShowAftermath.learner_first_name }}</p>
          <p v-if="noShowAftermath.financial_line" class="aftermath__credit">
            {{ noShowAftermath.financial_line }}
          </p>
          <div v-if="noShowAftermath.primary_cta_path" class="aftermath__actions ol-actions">
            <NuxtLink :to="noShowAftermath.primary_cta_path" class="ol-btn ol-btn--sm">
              {{ noShowAftermath.primary_cta_label }}
              <span aria-hidden="true">→</span>
            </NuxtLink>
          </div>
        </template>
        <p v-if="lesson.instructor_notes" class="ol-meta">
          <strong>Private:</strong> {{ lesson.instructor_notes }}
        </p>
      </div>

      <section v-if="lesson.status === 'scheduled'" class="danger">
        <button
          v-if="!confirmCancel"
          class="danger__btn"
          type="button"
          :disabled="!online"
          @click="confirmCancel = true; cancelNoticeHours = null"
        >
          Cancel lesson
        </button>
        <p v-if="!online" class="muted">Cancel needs a connection.</p>
        <div v-else-if="confirmCancel" class="danger__confirm">
          <p v-if="lesson.series_id">Cancel this weekly series occurrence?</p>
          <p v-else>Cancel this lesson? It stays in history.</p>
          <fieldset v-if="lesson.series_id" class="scope">
            <label class="radio">
              <input v-model="cancelScope" type="radio" value="this">
              <span>This lesson only</span>
            </label>
            <label class="radio">
              <input v-model="cancelScope" type="radio" value="this_and_future">
              <span>This and future lessons</span>
            </label>
          </fieldset>
          <fieldset class="scope">
            <legend class="scope__legend">Notice given</legend>
            <div class="ol-seg" role="radiogroup" aria-label="Notice given">
              <button
                v-for="opt in noticeOptions"
                :key="opt.hours"
                type="button"
                class="ol-chip"
                :data-on="cancelNoticeHours === opt.hours ? 'yes' : 'no'"
                :aria-pressed="cancelNoticeHours === opt.hours"
                :disabled="cancelling"
                @click="cancelNoticeHours = cancelNoticeHours === opt.hours ? null : opt.hours"
              >
                {{ opt.label }}
              </button>
            </div>
            <p class="muted">Optional. How much notice the pupil gave.</p>
          </fieldset>
          <div class="danger__actions">
            <button class="danger__btn" type="button" :disabled="cancelling" @click="onCancel">
              {{ cancelling ? 'Cancelling…' : 'Yes, cancel' }}
            </button>
            <button class="ghost" type="button" :disabled="cancelling" @click="closeCancelConfirm">
              Keep lesson
            </button>
          </div>
        </div>
      </section>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { EmptySeatRecovery, GapMatch, Lesson } from '~/composables/useLessons'
import type { CompletionAftermath, FinanceSnapshot, NoShowAftermath } from '~/composables/useFinance'
import { CANCELLATION_NOTICE_OPTIONS, formatCancellationNoticeLabel } from '~/utils/cancellationNotice'

type SkillCat = {
  code: string
  label: string
  skills: Array<{ id: number; code: string; label: string }>
}

const route = useRoute()
const id = computed(() => Number(route.params.id))
const { cancelLesson, durationLabel, markNoShowLesson } = useLessons()
const { loadLesson, completeLessonLocalFirst, online } = useOfflineTeaching()
const { t: tRec } = usePortalI18n()
const { t: tTeach } = useTeachingI18n()
const {
  route: instructorRoute,
  points: recordingPoints,
  moments,
  recording: isRecording,
  timerLabel: recordingTimer,
  error: routeRecError,
  busy: recordingBusy,
  markingMoment,
  refresh: refreshRoute,
  refreshMoments,
  start: startRecording,
  stop: stopRecording,
  share: shareRoute,
  discard: discardRoute,
  markMoment,
} = useRouteRecording(id)

const lesson = ref<Lesson | null>(null)
const emptySeat = ref<EmptySeatRecovery | null>(null)
const loading = ref(true)
const error = ref('')
const syncLabel = ref<string | null>(null)
const confirmCancel = ref(false)
const confirmNoShow = ref(false)
const noShowCharge = ref<'outstanding' | 'package' | 'waived'>('outstanding')
const noShowNotes = ref('')
const markingNoShow = ref(false)
const cancelScope = ref<'this' | 'this_and_future'>('this')
const cancelNoticeHours = ref<number | null>(null)
const noticeOptions = CANCELLATION_NOTICE_OPTIONS
const cancelling = ref(false)
const completing = ref(false)

const instructorNotes = ref('')
const learnerSummary = ref('')
const nextFocus = ref('')

const skillCatalogue = ref<SkillCat[]>([])
const selectedSkillIds = ref<number[]>([])
const skillRatings = reactive<Record<number, string>>({})

const flatSkills = computed(() => skillCatalogue.value.flatMap(c => c.skills))

const recordingError = computed(() => {
  const code = routeRecError.value
  if (!code) return ''
  if (code === 'geo_denied') return tRec('recording.geoDenied')
  if (code === 'geo_unavailable') return tRec('recording.geoUnavailable')
  if (code === 'need_points') return tRec('recording.needPoints')
  return code
})

const statusLabel = computed(() => {
  if (!lesson.value) return 'Lesson'
  if (lesson.value.status_label) return lesson.value.status_label
  if (lesson.value.status === 'cancelled') return 'Cancelled'
  if (lesson.value.status === 'completed') return 'Completed'
  if (lesson.value.status === 'no_show') return 'No-show'
  return 'Scheduled'
})

const cancellationNoticeDisplay = computed(() => {
  const l = lesson.value
  if (!l || l.status !== 'cancelled') return null
  return l.cancellation_notice_label || formatCancellationNoticeLabel(l.cancellation_notice_hours)
})

const cancelledByLabel = computed(() => {
  const by = lesson.value?.cancelled_by
  if (by === 'learner') return 'Pupil'
  if (by === 'instructor') return 'You'
  if (by === 'system') return 'System'
  return by || null
})

const pupilFirstName = computed(() => {
  const name = lesson.value?.learner_name || 'Pupil'
  return name.split(/\s+/)[0] || name
})

const canUsePackageCredit = computed(() => {
  const snap = financeSnapshot.value
  const mins = lesson.value?.duration_minutes ?? 0
  return Boolean(snap && snap.credit_minutes >= mins && mins > 0)
})

const packageCreditLabel = computed(() => {
  const mins = lesson.value?.duration_minutes ?? 0
  return durationLabel(mins)
})

const noShowAftermath = computed<NoShowAftermath | null>(() => {
  const a = lesson.value?.finance?.aftermath
  if (!a || !('financial_line' in a)) return null
  return a as NoShowAftermath
})

const financeSnapshot = computed<FinanceSnapshot | null>(() => {
  const f = lesson.value?.finance
  if (!f) return null
  return f.snapshot ?? null
})

const aftermath = computed<CompletionAftermath | null>(() => {
  return lesson.value?.finance?.aftermath ?? null
})

const contextSummary = computed(
  () => lesson.value?.learner_last_lesson_summary || lesson.value?.learner_summary || null,
)
const contextFocus = computed(
  () => lesson.value?.learner_next_focus || lesson.value?.next_focus || null,
)

const testDateDisplay = computed(() => {
  const j = lesson.value?.test_journey
  if (!j) return null
  return 'test_date_display' in j ? j.test_date_display : null
})

const testProgress = computed(() => {
  const j = lesson.value?.test_journey
  if (!j || !('progress_summary' in j)) return null
  return j.progress_summary
})

useHead(() => ({
  title: lesson.value
    ? `${lesson.value.learner_name} · Lesson · OwnLane`
    : 'Lesson · OwnLane',
}))

function toggleSkill(skillId: number) {
  const idx = selectedSkillIds.value.indexOf(skillId)
  if (idx >= 0) {
    selectedSkillIds.value = selectedSkillIds.value.filter(id => id !== skillId)
    delete skillRatings[skillId]
  } else {
    selectedSkillIds.value = [...selectedSkillIds.value, skillId]
  }
}

function skillLabel(skillId: number): string {
  return flatSkills.value.find(s => s.id === skillId)?.label ?? String(skillId)
}

async function loadSkillsCatalogue() {
  if (!online.value) return
  try {
    const res = await apiFetch<{ categories: SkillCat[] }>('/lessons/skills')
    skillCatalogue.value = res.categories ?? []
  } catch {
    skillCatalogue.value = []
  }
}

async function onMarkMoment() {
  try {
    await markMoment()
  } catch {
    // error surfaced via recordingError
  }
}

async function onMomentKind(momentId: number, kind: string) {
  try {
    const updated = await apiFetch<(typeof moments.value)[number]>(`/moments/${momentId}`, {
      method: 'PATCH',
      body: { kind },
    })
    moments.value = moments.value.map(m => (m.id === momentId ? updated : m))
  } catch {
    // keep UI
  }
}

async function onMomentField(momentId: number, field: 'label' | 'learner_note', value: string) {
  try {
    const updated = await apiFetch<(typeof moments.value)[number]>(`/moments/${momentId}`, {
      method: 'PATCH',
      body: { [field]: value.trim() || null },
    })
    moments.value = moments.value.map(m => (m.id === momentId ? updated : m))
  } catch {
    // keep UI
  }
}

async function onMomentVisible(momentId: number, visible: boolean) {
  try {
    const updated = await apiFetch<(typeof moments.value)[number]>(`/moments/${momentId}`, {
      method: 'PATCH',
      body: { learner_visible: visible },
    })
    moments.value = moments.value.map(m => (m.id === momentId ? updated : m))
  } catch {
    // keep UI
  }
}

async function onStartRecording() {
  try {
    await startRecording()
  } catch {
    // error surfaced via recordingError
  }
}

async function onStopRecording() {
  try {
    await stopRecording()
  } catch {
    // error surfaced via recordingError
  }
}

async function onShareRoute() {
  try {
    await shareRoute()
  } catch {
    // error surfaced via recordingError
  }
}

async function onDiscardRoute() {
  try {
    await discardRoute()
  } catch {
    // error surfaced via recordingError
  }
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const result = await loadLesson(id.value)
    lesson.value = result.data
    emptySeat.value = result.data.empty_seat ?? null
    syncLabel.value = result.syncLabel
    if (lesson.value.status === 'scheduled') {
      nextFocus.value = ''
      learnerSummary.value = ''
      instructorNotes.value = ''
      selectedSkillIds.value = []
      for (const key of Object.keys(skillRatings)) {
        delete skillRatings[Number(key)]
      }
      void loadSkillsCatalogue()
    }
    await refreshRoute()
    await refreshMoments()
  } catch (e) {
    lesson.value = null
    error.value = e instanceof Error ? e.message : extractApiError(e, 'Could not load this lesson.')
  } finally {
    loading.value = false
  }
}

async function onComplete() {
  if (!lesson.value) return
  completing.value = true
  error.value = ''
  try {
    const ratings: Record<string, string> = {}
    for (const sid of selectedSkillIds.value) {
      const r = skillRatings[sid]
      if (r) ratings[String(sid)] = r
    }
    lesson.value = await completeLessonLocalFirst({
      lessonId: lesson.value.id,
      instructor_notes: instructorNotes.value.trim() || undefined,
      learner_summary: learnerSummary.value.trim() || undefined,
      next_focus: nextFocus.value.trim() || undefined,
      base_updated_at: lesson.value.updated_at ?? null,
      skill_ids: selectedSkillIds.value.length ? selectedSkillIds.value : undefined,
      skill_ratings: Object.keys(ratings).length ? ratings : undefined,
    })
    syncLabel.value = lesson.value.finance?.aftermath
      ? null
      : (online.value ? 'Waiting to sync' : 'Saved on this device')
  } catch (e) {
    error.value = extractApiError(e, 'Could not complete this lesson.')
  } finally {
    completing.value = false
  }
}

async function onMarkNoShow() {
  if (!lesson.value || !online.value) return
  markingNoShow.value = true
  error.value = ''
  try {
    lesson.value = await markNoShowLesson(lesson.value.id, {
      charge: noShowCharge.value,
      instructor_notes: noShowNotes.value.trim() || undefined,
      client_mutation_id: crypto.randomUUID(),
    })
    confirmNoShow.value = false
  } catch (e) {
    error.value = extractApiError(e, 'Could not mark this lesson as no-show.')
  } finally {
    markingNoShow.value = false
  }
}

async function onCancel() {
  cancelling.value = true
  try {
    const notice = cancelNoticeHours.value
    const result = await cancelLesson(
      id.value,
      lesson.value?.series_id ? cancelScope.value : 'this',
      notice != null ? { cancellation_notice_hours: notice } : {},
    )
    if ('items' in result) {
      lesson.value = result.items[0] ?? lesson.value
      emptySeat.value = result.empty_seat ?? result.items[0]?.empty_seat ?? null
    } else {
      lesson.value = result
      emptySeat.value = result.empty_seat ?? null
    }
    closeCancelConfirm()
  } catch (e) {
    error.value = extractApiError(e, 'Could not cancel this lesson.')
  } finally {
    cancelling.value = false
  }
}

function closeCancelConfirm() {
  confirmCancel.value = false
  cancelNoticeHours.value = null
}

function bookEmptySeatHref(match: GapMatch): string {
  const q = new URLSearchParams({
    learner_id: String(match.learner_id),
    starts_at_local: match.suggested_starts_at_local,
    duration_minutes: String(match.suggested_duration_minutes),
  })
  return `/lessons/new?${q.toString()}`
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.page {
  gap: var(--spacing-16);
}

.hero {
  align-items: flex-start;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-8);
  min-height: 48px;
  padding: 12px 20px;
  border: none;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  box-shadow: var(--shadow-button);
  flex-shrink: 0;
  cursor: pointer;
  font: inherit;
  text-decoration: none;
}

.btn--ghost {
  background: transparent;
  color: var(--color-ink-black);
  box-shadow: none;
  border: 1px solid var(--color-frost-green);
  min-height: 44px;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.panel {
  background: var(--surface-card);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-panel);
  padding: var(--card-padding-sm) var(--card-padding);
}

.panel--test {
  box-shadow: none;
}

.panel__heading {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.6;
  margin-bottom: var(--spacing-12);
}

.test__countdown {
  font-size: var(--text-body-sm);
  color: var(--color-ownlane-green);
  margin-bottom: var(--spacing-12);
}

.panel--soft {
  box-shadow: none;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
  font-size: var(--text-body-sm);
}

.facts {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.facts__row {
  display: grid;
  grid-template-columns: 100px 1fr;
  gap: var(--spacing-8);
  font-size: var(--text-body-sm);
  padding: 2px 0;
}

.facts__row dt {
  opacity: 0.65;
}

.maps-note {
  margin-top: var(--spacing-12);
  font-size: 14px;
  opacity: 0.65;
}

.pupil-link a {
  color: var(--color-ownlane-green);
  font-size: var(--text-body-sm);
}

.recording {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
  padding: var(--spacing-16);
  background: var(--surface-wash);
  border-radius: var(--radius-panel);
}

.recording__title {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.65;
}

.recording__timer {
  display: flex;
  align-items: center;
  gap: var(--spacing-8);
  font-size: var(--text-body-sm);
}

.recording__dot {
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: var(--color-marker-red);
  animation: pulse 1.2s ease-in-out infinite;
}

@media (prefers-reduced-motion: reduce) {
  .recording__dot {
    animation: none;
  }
}

@keyframes pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.35;
  }
}

.recording__time {
  font-family: var(--font-martian-mono);
  font-variant-numeric: tabular-nums;
}

.recording__saved,
.recording__shared {
  font-size: var(--text-body-sm);
}

.recording__shared {
  color: var(--color-ownlane-green);
}

.recording__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
}

.recording__error {
  color: var(--color-danger);
  font-size: var(--text-body-sm);
}

.moments {
  margin-top: var(--spacing-12);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.moments__title {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.65;
}

.moments__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
}

.moments__item {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px 0;
  border-top: 1px solid var(--color-border);
}

.moments__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.moments__offset {
  font-family: var(--font-martian-mono);
  font-size: var(--text-meta);
  min-width: 3rem;
}

.moments__select,
.moments__input,
.moments__note {
  min-height: 44px;
  padding: 8px 12px;
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-small);
  background: var(--surface-canvas);
  font: inherit;
  font-size: var(--text-meta);
}

.moments__note {
  min-height: 64px;
  resize: vertical;
}

.moments__check {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: var(--text-meta);
  min-height: 40px;
}

.moments__explain {
  color: var(--color-ownlane-green);
  font-size: var(--text-body-sm);
  text-decoration: none;
  min-height: 44px;
  display: inline-flex;
  align-items: center;
}

.skills {
  border: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.skills__legend {
  font-size: var(--text-body-sm);
  padding: 0;
}

.skills__hint {
  font-size: var(--text-meta);
  opacity: 0.7;
}

.skills__chips {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
}

.skills__chip {
  min-height: 40px;
  padding: 6px 14px;
  border-radius: var(--radius-tags);
  border: 1px solid var(--color-border);
  background: var(--surface-canvas);
  font: inherit;
  font-size: var(--text-meta);
  cursor: pointer;
}

.skills__chip--on {
  background: var(--color-frost-green);
  border-color: var(--color-ownlane-green);
  color: var(--color-ownlane-green);
}

.skills__ratings {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
  margin-top: var(--spacing-8);
}

.skills__rating-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-8);
  font-size: var(--text-body-sm);
  min-height: 44px;
}

.skills__select {
  min-height: 40px;
  padding: 6px 12px;
  border-radius: var(--radius-small);
  border: 1px solid var(--color-frost-green);
  background: var(--surface-canvas);
  font: inherit;
  font-size: var(--text-meta);
}

.complete {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
  padding: var(--spacing-20);
  background: var(--surface-card);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-cards);
  box-shadow: var(--shadow-card);
}

.complete__title {
  font-size: var(--text-heading-sm);
}

.complete__hint {
  font-size: var(--text-body-sm);
  opacity: 0.75;
  max-width: 40ch;
}

.field {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.field__label {
  font-size: var(--text-body-sm);
}

.optional {
  opacity: 0.55;
}

.field__textarea {
  width: 100%;
  min-height: 72px;
  padding: 12px 16px;
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-small);
  background: var(--surface-canvas);
  font: inherit;
}

.field__textarea:focus {
  outline: 2px solid var(--color-ownlane-green);
  outline-offset: 2px;
}

.aftermath {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--spacing-8);
  padding: var(--spacing-20);
  background: var(--surface-card);
  border: 1px solid var(--color-ownlane-green);
  border-radius: var(--radius-cards);
  box-shadow: 0 4px 0 0 var(--color-ownlane-green);
}

.aftermath__check {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-ownlane-green);
}

.aftermath__name {
  font-size: var(--text-heading-sm);
  line-height: var(--leading-heading-sm);
  letter-spacing: var(--tracking-heading-sm);
}

.aftermath__credit {
  font-size: var(--text-body-sm);
  opacity: 0.85;
}

.aftermath__owe {
  font-size: 14px;
  color: var(--color-marker-red);
}

.aftermath__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-12);
  margin-top: var(--spacing-8);
}

.danger {
  padding: var(--spacing-8) 0 var(--spacing-24);
}

.recovery {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
  padding: var(--spacing-20);
  background: var(--surface-card);
  border: 1px solid var(--color-ownlane-green);
  border-radius: var(--radius-cards);
  box-shadow: 0 4px 0 0 var(--color-ownlane-green);
}

.recovery__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-ownlane-green);
  margin-bottom: var(--spacing-8);
}

.recovery__title {
  font-size: var(--text-heading-sm);
}

.recovery__slot {
  margin-top: var(--spacing-8);
  font-size: var(--text-body);
}

.recovery__summary {
  margin-top: var(--spacing-4);
  font-size: var(--text-body-sm);
  opacity: 0.75;
}

.recovery__best-label {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.6;
}

.recovery__card {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--spacing-16);
  padding: var(--spacing-16);
  border-radius: var(--radius-small);
  background: var(--surface-wash);
}

.recovery__name {
  font-size: var(--text-body);
}

.recovery__name--sm {
  font-size: var(--text-body-sm);
}

.recovery__reasons {
  list-style: none;
  margin: 6px 0 0;
  padding: 0;
  font-size: 14px;
  opacity: 0.75;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.recovery__others {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.recovery__others li {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--spacing-12);
}

.recovery__reason-line {
  margin-top: 4px;
  font-size: 13px;
  opacity: 0.7;
}

.danger__btn {
  border: 1px solid var(--color-frost-green);
  background: transparent;
  border-radius: var(--radius-buttons);
  min-height: 44px;
  padding: 10px 18px;
  cursor: pointer;
  color: var(--color-marker-red);
  font: inherit;
}

.danger__btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.danger__confirm {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
  font-size: var(--text-body-sm);
}

.scope {
  border: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.scope__legend {
  font-size: var(--text-body-sm);
  font-weight: 600;
  color: var(--color-bark);
  margin-bottom: 2px;
}

.radio {
  display: flex;
  align-items: center;
  gap: var(--spacing-8);
}

.danger__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-12);
}

.ghost {
  border: none;
  background: transparent;
  min-height: 44px;
  padding: 10px 12px;
  cursor: pointer;
  font: inherit;
  text-decoration: underline;
}

.error {
  color: var(--color-marker-red);
}

.muted {
  opacity: 0.65;
  font-size: var(--text-body-sm);
}
</style>
