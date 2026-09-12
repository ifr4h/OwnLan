<template>
  <PortalPage>
    <template #header>
      <h1 class="portal-page__title">{{ t('lessons.title') }}</h1>
    </template>

    <template v-if="loading && !home">
      <PortalSkeleton variant="line" />
      <PortalSkeleton variant="block" />
    </template>

    <p v-else-if="error" class="lessons__error" role="alert">{{ error }}</p>

    <PortalMainGrid v-else-if="home" variant="master-detail">
      <aside class="lessons__list">
        <section v-if="upcoming.length" aria-labelledby="upcoming">
          <h2 id="upcoming" class="lessons__section">{{ t('lessons.upcoming') }}</h2>
          <ul class="lessons__items">
            <li v-for="lesson in upcoming" :key="lesson.id">
              <button
                type="button"
                class="lessons__item"
                :class="{ 'lessons__item--active': selected?.id === lesson.id }"
                @click="selected = lesson"
              >
                <span class="lessons__day">{{ lesson.starts_at_day || lesson.starts_at_display }}</span>
                <span class="lessons__time">{{ lesson.starts_at_time }}–{{ lesson.ends_at_time }}</span>
                <span v-if="lesson.next_focus" class="lessons__focus">{{ lesson.next_focus }}</span>
              </button>
            </li>
          </ul>
        </section>

        <section v-if="previous.length" aria-labelledby="previous">
          <h2 id="previous" class="lessons__section">{{ t('lessons.previous') }}</h2>
          <ul class="lessons__items">
            <li v-for="lesson in previous" :key="lesson.id">
              <button
                type="button"
                class="lessons__item"
                :class="{ 'lessons__item--active': selected?.id === lesson.id }"
                @click="selected = lesson"
              >
                <span class="lessons__day">{{ lesson.starts_at_day || lesson.starts_at_display }}</span>
                <span class="lessons__time">{{ lesson.starts_at_time }}–{{ lesson.ends_at_time }}</span>
                <span v-if="lesson.skills?.length" class="lessons__focus">
                  {{ lesson.skills.slice(0, 2).join(' · ') }}
                </span>
              </button>
            </li>
          </ul>
        </section>

        <p v-if="!upcoming.length && !previous.length" class="lessons__empty">
          {{ t('lessons.empty') }}
        </p>
      </aside>

      <div v-if="selected" class="lessons__detail">
        <h2 class="lessons__detail-title">
          {{ selected.starts_at_day || selected.starts_at_display }}
        </h2>
        <p class="lessons__detail-meta">
          {{ selected.starts_at_time }}–{{ selected.ends_at_time }}
          <span v-if="selected.duration_label"> · {{ selected.duration_label }}</span>
        </p>

        <section class="lessons__block">
          <div class="lessons__block-head">
            <h3 class="lessons__block-title">{{ t('lessons.pickup') }}</h3>
            <button
              v-if="isUpcoming(selected)"
              type="button"
              class="lessons__text-btn"
              @click="pickupEditing = !pickupEditing"
            >
              {{ pickupEditing ? 'Done' : t('lessons.changePickup') }}
            </button>
          </div>
          <p v-if="!pickupEditing" class="lessons__detail-pickup">
            <template v-if="selected.pickup_location">
              {{ selected.pickup_location.label }} · {{ selected.pickup_location.address }}
            </template>
            <template v-else>
              {{ selected.pickup_short || selected.pickup_address || 'Not set' }}
            </template>
          </p>
          <div v-else class="lessons__pickup-edit">
            <LocationPicker
              v-model="pickupSel"
              :locations="places"
              :loading="placesLoading"
              audience="learner"
              :disabled="pickupSaving"
            />
            <button
              class="ol-btn ol-btn--sm"
              type="button"
              :disabled="pickupSaving"
              @click="savePickup"
            >
              {{ pickupSaving ? 'Saving…' : 'Save pickup' }}
            </button>
            <p v-if="pickupError" class="ol-error" role="alert">{{ pickupError }}</p>
          </div>
        </section>

        <p v-if="selected.next_focus" class="lessons__detail-focus">{{ selected.next_focus }}</p>
        <p v-else-if="selected.learner_summary" class="lessons__detail-focus">
          {{ selected.learner_summary }}
        </p>

        <section v-if="isUpcoming(selected)" class="lessons__block">
          <h3 class="lessons__block-title">{{ t('lessons.focusSuggest') }}</h3>
          <p class="lessons__block-hint">{{ t('lessons.focusHint') }}</p>
          <div v-if="focusTags.length" class="lessons__tags">
            <button
              v-for="tag in focusTags"
              :key="tag"
              type="button"
              class="lessons__tag"
              :disabled="focusSaving"
              @click="removeFocusTag(tag)"
            >
              {{ tag }} ×
            </button>
          </div>
          <form class="lessons__tag-form" @submit.prevent="addFocusTag">
            <input
              v-model="focusDraft"
              class="ol-input"
              type="text"
              maxlength="40"
              placeholder="e.g. Parallel park"
              :disabled="focusSaving"
            >
            <button class="ol-btn ol-btn--sm ol-btn--ghost" type="submit" :disabled="focusSaving || !focusDraft.trim()">
              Add
            </button>
          </form>
        </section>

        <section class="lessons__block">
          <h3 class="lessons__block-title">{{ t('lessons.sharedNotes') }}</h3>
          <p class="lessons__block-hint">{{ t('lessons.sharedHint') }}</p>
          <p v-if="messagesLoading" class="ol-muted">Loading…</p>
          <ul v-else-if="messages.length" class="lessons__chat">
            <li
              v-for="msg in messages"
              :key="msg.id"
              class="lessons__chat-item"
              :data-role="msg.author_role"
            >
              <p class="lessons__chat-meta">
                {{ msg.author_role === 'learner' ? 'You' : 'Instructor' }}
              </p>
              <p class="lessons__chat-body">{{ msg.body }}</p>
            </li>
          </ul>
          <p v-else class="ol-muted">No notes on this lesson yet.</p>
          <div class="lessons__compose">
            <textarea
              v-model="chatDraft"
              class="ol-textarea"
              rows="2"
              :placeholder="t('lessons.notePlaceholder')"
              :disabled="chatSending"
            />
            <button
              class="ol-btn ol-btn--sm"
              type="button"
              :disabled="chatSending || !chatDraft.trim()"
              @click="sendChat"
            >
              {{ chatSending ? 'Sending…' : t('lessons.sendNote') }}
            </button>
          </div>
          <p v-if="chatError" class="ol-error" role="alert">{{ chatError }}</p>
        </section>

        <div class="lessons__actions">
          <NuxtLink
            v-if="isUpcoming(selected)"
            to="/portal/prepare"
            class="lessons__cta"
          >
            {{ t('home.prepareCta') }}
          </NuxtLink>
          <NuxtLink
            v-else
            :to="`/portal/recap/${selected.id}`"
            class="lessons__cta"
          >
            {{ t('home.viewRecap') }}
          </NuxtLink>
          <NuxtLink
            v-if="!isUpcoming(selected)"
            :to="`/portal/lessons/${selected.id}/playback`"
            class="lessons__link"
          >
            {{ t('lessons.playback') }}
          </NuxtLink>
        </div>

        <section
          v-if="canCancelSelected"
          class="lessons__cancel"
          aria-label="Cancel lesson"
        >
          <div v-if="!confirmCancel" class="lessons__cancel-idle">
            <button
              type="button"
              class="lessons__cancel-trigger"
              :disabled="cancelling"
              @click="confirmCancel = true"
            >
              {{ t('lessons.cancelLesson') }}
            </button>
          </div>
          <div
            v-else
            class="lessons__confirm"
            role="alertdialog"
            :aria-label="cancelPreview?.title || t('lessons.cancelLesson')"
          >
            <p class="lessons__confirm-title">
              {{ cancelPreview?.title || t('lessons.cancelConfirmTitle') }}
            </p>
            <p class="lessons__confirm-msg">
              {{ cancelPreview?.message || t('lessons.cancelConfirmHint') }}
            </p>
            <p v-if="cancelPreview?.policy_text" class="lessons__confirm-policy">
              {{ cancelPreview.policy_text }}
            </p>
            <label v-if="cancelPreview?.reason_required" class="ol-field">
              <span class="ol-field__label">{{ t('lessons.cancelReason') }}</span>
              <textarea
                v-model="cancelReason"
                class="ol-textarea"
                rows="2"
                maxlength="500"
                :placeholder="t('lessons.cancelReasonPlaceholder')"
                :disabled="cancelling"
              />
            </label>
            <p v-if="cancelError" class="ol-error" role="alert">{{ cancelError }}</p>
            <div class="lessons__confirm-actions">
              <button
                class="ol-btn ol-btn--sm"
                type="button"
                :disabled="cancelling || (cancelPreview?.reason_required && !cancelReason.trim())"
                @click="onCancelConfirm"
              >
                {{ cancelling ? t('lessons.cancelling') : t('lessons.cancelConfirm') }}
              </button>
              <button
                class="ol-btn ol-btn--ghost ol-btn--sm"
                type="button"
                :disabled="cancelling"
                @click="closeCancelConfirm"
              >
                {{ t('lessons.keepLesson') }}
              </button>
            </div>
            <NuxtLink
              v-if="home?.booking?.can_book"
              to="/portal/book"
              class="lessons__link lessons__link--after-cancel"
            >
              {{ t('lessons.findAnotherTime') }}
            </NuxtLink>
          </div>
        </section>
      </div>

      <p v-else-if="upcoming.length || previous.length" class="lessons__pick portal-hide-mobile">
        {{ t('lessons.selectHint') }}
      </p>
    </PortalMainGrid>
  </PortalPage>
</template>

<script setup lang="ts">
import type { PortalHome, PortalLesson } from '~/composables/usePortal'
import type { LearnerLocation, PickupSelection } from '~/composables/useLearnerLocations'
import LocationPicker from '~/components/locations/LocationPicker.vue'

definePageMeta({ layout: 'portal' })
useHead({ title: 'Lessons · OwnLane' })

const { t } = usePortalI18n()
const {
  fetchHome,
  readCachedHome,
  listLessonMessages,
  postLessonMessage,
  updateLessonPickup,
  updateLessonFocusTags,
} = usePortal()
const { listPortalPlaces } = useLearnerLocations()
const { cancelLesson } = usePortalBooking()

const home = ref<PortalHome | null>(null)
const loading = ref(true)
const error = ref('')
const selected = ref<PortalLesson | null>(null)

const confirmCancel = ref(false)
const cancelReason = ref('')
const cancelling = ref(false)
const cancelError = ref('')

const places = ref<LearnerLocation[]>([])
const placesLoading = ref(false)
const pickupEditing = ref(false)
const pickupSaving = ref(false)
const pickupError = ref('')
const pickupSel = ref<PickupSelection>({
  pickup_location_id: null,
  pickup_address: null,
})

const messages = ref<Array<{
  id: number
  lesson_id: number
  author_role: 'instructor' | 'learner'
  body: string
  created_at: string
}>>([])
const messagesLoading = ref(false)
const chatDraft = ref('')
const chatSending = ref(false)
const chatError = ref('')

const focusTags = ref<string[]>([])
const focusDraft = ref('')
const focusSaving = ref(false)

const upcoming = computed(() => {
  const lessons = home.value?.upcoming_lessons ?? []
  const next = home.value?.next_lesson
  if (!next) return lessons
  if (lessons.some(l => l.id === next.id)) return lessons
  return [next, ...lessons]
})

const previous = computed(() => home.value?.previous_lessons ?? [])

function isUpcoming(lesson: PortalLesson): boolean {
  return upcoming.value.some(l => l.id === lesson.id)
}

const cancelPreview = computed(() => selected.value?.cancellation ?? null)

const canCancelSelected = computed(() => {
  if (!selected.value || !isUpcoming(selected.value)) return false
  if (cancelPreview.value) return cancelPreview.value.allowed
  return !!home.value?.booking?.can_cancel
})

function closeCancelConfirm() {
  confirmCancel.value = false
  cancelReason.value = ''
  cancelError.value = ''
}

async function onCancelConfirm() {
  if (!selected.value) return
  if (cancelPreview.value?.reason_required && !cancelReason.value.trim()) {
    cancelError.value = t('lessons.cancelReasonRequired')
    return
  }
  cancelling.value = true
  cancelError.value = ''
  try {
    await cancelLesson(
      selected.value.id,
      cancelReason.value.trim() || undefined,
    )
    closeCancelConfirm()
    home.value = await fetchHome({ allowStale: false })
    selected.value =
      home.value.next_lesson ??
      home.value.upcoming_lessons[0] ??
      home.value.previous_lessons[0] ??
      null
  } catch (e: unknown) {
    cancelError.value = e instanceof Error ? e.message : t('lessons.cancelFailed')
  } finally {
    cancelling.value = false
  }
}

async function loadPlaces() {
  placesLoading.value = true
  try {
    places.value = await listPortalPlaces()
  } catch {
    places.value = []
  } finally {
    placesLoading.value = false
  }
}

async function loadMessages(id: number) {
  messagesLoading.value = true
  chatError.value = ''
  try {
    messages.value = await listLessonMessages(id)
  } catch {
    messages.value = []
  } finally {
    messagesLoading.value = false
  }
}

function hydrateSelection(lesson: PortalLesson | null) {
  pickupEditing.value = false
  pickupError.value = ''
  chatDraft.value = ''
  focusDraft.value = ''
  closeCancelConfirm()
  focusTags.value = [...(lesson?.focus_tags || [])]
  pickupSel.value = {
    pickup_location_id: lesson?.pickup_location_id ?? lesson?.pickup_location?.id ?? null,
    pickup_address: lesson?.pickup_location?.address || lesson?.pickup_address || null,
  }
  if (lesson) void loadMessages(lesson.id)
  else messages.value = []
}

watch(selected, (lesson) => {
  hydrateSelection(lesson)
})

async function savePickup() {
  if (!selected.value) return
  pickupSaving.value = true
  pickupError.value = ''
  try {
    const body = pickupSel.value.pickup_location_id
      ? { pickup_location_id: pickupSel.value.pickup_location_id }
      : {
          pickup_location_id: null,
          pickup_address: pickupSel.value.pickup_address,
        }
    await updateLessonPickup(selected.value.id, body)
    selected.value = {
      ...selected.value,
      pickup_location_id: pickupSel.value.pickup_location_id,
      pickup_address: pickupSel.value.pickup_address,
      pickup_location: pickupSel.value.pickup_location_id
        ? places.value.find(p => p.id === pickupSel.value.pickup_location_id) || null
        : null,
    }
    pickupEditing.value = false
  } catch (e: unknown) {
    pickupError.value = e instanceof Error ? e.message : 'Could not update pickup.'
  } finally {
    pickupSaving.value = false
  }
}

async function sendChat() {
  if (!selected.value || !chatDraft.value.trim()) return
  chatSending.value = true
  chatError.value = ''
  try {
    const msg = await postLessonMessage(selected.value.id, chatDraft.value.trim())
    messages.value = [...messages.value, msg]
    chatDraft.value = ''
  } catch (e: unknown) {
    chatError.value = e instanceof Error ? e.message : 'Could not send that note.'
  } finally {
    chatSending.value = false
  }
}

async function addFocusTag() {
  if (!selected.value) return
  const tag = focusDraft.value.trim()
  if (!tag) return
  const next = focusTags.value.includes(tag) ? focusTags.value : [...focusTags.value, tag]
  focusSaving.value = true
  try {
    const res = await updateLessonFocusTags(selected.value.id, next)
    focusTags.value = res.focus_tags || next
    focusDraft.value = ''
    selected.value = { ...selected.value, focus_tags: focusTags.value }
  } catch {
    // keep draft
  } finally {
    focusSaving.value = false
  }
}

async function removeFocusTag(tag: string) {
  if (!selected.value) return
  const next = focusTags.value.filter(t => t !== tag)
  focusSaving.value = true
  try {
    const res = await updateLessonFocusTags(selected.value.id, next)
    focusTags.value = res.focus_tags || next
    selected.value = { ...selected.value, focus_tags: focusTags.value }
  } finally {
    focusSaving.value = false
  }
}

async function load() {
  loading.value = true
  error.value = ''
  const cached = readCachedHome()
  if (cached) home.value = cached
  try {
    home.value = await fetchHome({ allowStale: true })
    selected.value =
      home.value.next_lesson ??
      home.value.upcoming_lessons[0] ??
      home.value.previous_lessons[0] ??
      null
    await loadPlaces()
  } catch (e) {
    error.value = extractApiError(e, t('common.errorGeneric'))
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.lessons__error {
  color: var(--color-danger);
  font-size: var(--text-body-sm);
}

.lessons__section {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
  margin-bottom: var(--spacing-12);
}

.lessons__items {
  list-style: none;
  margin: 0 0 var(--spacing-24);
  padding: 0;
}

.lessons__item {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  min-height: 56px;
  padding: var(--spacing-12) 0;
  border: none;
  border-bottom: 1px solid var(--color-border);
  background: transparent;
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.lessons__item--active {
  color: var(--color-ownlane-green);
}

.lessons__day {
  font-size: var(--text-body-sm);
  font-weight: 500;
}

.lessons__time,
.lessons__focus {
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.lessons__empty,
.lessons__pick {
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.lessons__detail {
  padding: var(--spacing-8) 0;
}

.lessons__detail-title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-md);
  letter-spacing: var(--tracking-heading-md);
}

.lessons__detail-meta,
.lessons__detail-pickup {
  font-size: var(--text-body-sm);
  color: var(--color-muted);
  margin-top: var(--spacing-4);
}

.lessons__detail-focus {
  margin-top: var(--spacing-16);
  font-size: var(--text-body);
  line-height: var(--leading-body);
}

.lessons__block {
  margin-top: var(--spacing-20);
  padding: 12px;
  border-radius: var(--radius-small);
  border: 1px solid var(--color-border);
  background: var(--color-parchment);
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.lessons__block-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.lessons__block-title {
  margin: 0;
  font-size: 11px;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  font-weight: 600;
  color: var(--color-muted);
}

.lessons__block-hint {
  margin: 0;
  font-size: 12px;
  color: var(--color-muted);
}

.lessons__text-btn {
  border: none;
  background: transparent;
  color: var(--color-ownlane-green);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
}

.lessons__pickup-edit {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.lessons__tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.lessons__tag {
  border: none;
  border-radius: 20px;
  padding: 6px 10px;
  background: color-mix(in srgb, var(--color-ownlane-green) 12%, var(--color-paper-white));
  color: var(--color-ownlane-green);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.lessons__tag-form {
  display: flex;
  gap: 8px;
  align-items: center;
}

.lessons__tag-form .ol-input {
  flex: 1;
  min-height: 40px;
  padding: 8px 12px;
}

.lessons__chat {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 200px;
  overflow: auto;
}

.lessons__chat-item {
  padding: 8px 10px;
  border-radius: 8px;
  background: var(--color-paper-white);
  border: 1px solid var(--color-border);
}

.lessons__chat-item[data-role='instructor'] {
  background: color-mix(in srgb, var(--color-ownlane-green) 6%, var(--color-paper-white));
}

.lessons__chat-meta {
  margin: 0;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-muted);
}

.lessons__chat-body {
  margin: 4px 0 0;
  font-size: var(--text-body-sm);
  line-height: 1.4;
  white-space: pre-wrap;
}

.lessons__compose {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.lessons__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-12);
  margin-top: var(--spacing-24);
}

.lessons__cta {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  padding: 10px 18px;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  text-decoration: none;
  font-size: var(--text-body-sm);
  box-shadow: var(--shadow-button);
}

.lessons__link {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  color: var(--color-ownlane-green);
  text-decoration: none;
  font-size: var(--text-body-sm);
}

.lessons__cancel {
  margin-top: var(--spacing-24);
  padding-top: var(--spacing-20);
  border-top: 1px solid var(--color-border);
}

.lessons__cancel-trigger {
  border: none;
  background: transparent;
  padding: 0;
  min-height: 44px;
  font: inherit;
  font-size: var(--text-body-sm);
  color: var(--color-danger, #b42318);
  cursor: pointer;
}

.lessons__cancel-trigger:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.lessons__confirm {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.lessons__confirm-title {
  margin: 0;
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm, 1.1rem);
  letter-spacing: var(--tracking-heading-sm, 0);
}

.lessons__confirm-msg,
.lessons__confirm-policy {
  margin: 0;
  font-size: var(--text-body-sm);
  line-height: 1.45;
  color: var(--color-muted);
}

.lessons__confirm-policy {
  white-space: pre-wrap;
}

.lessons__confirm-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 4px;
}

.lessons__link--after-cancel {
  margin-top: 4px;
}

@media (max-width: 1023px) {
  .lessons__detail {
    margin-top: var(--spacing-24);
    padding-top: var(--spacing-24);
    border-top: 1px solid var(--color-border);
  }
}
</style>
