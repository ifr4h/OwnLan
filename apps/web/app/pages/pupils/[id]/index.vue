<template>
  <section class="ol-page">
    <NuxtLink to="/pupils" class="ol-back">← Pupils</NuxtLink>

    <p v-if="loading" class="ol-muted">Loading…</p>
    <p v-else-if="error" class="ol-error" role="alert">{{ error }}</p>

    <template v-else-if="pupil">
      <header class="hero">
        <div>
          <p class="ol-eyebrow">
            {{ pupil.lifecycle === 'waiting' ? 'Waiting list' : 'Pupil' }}
          </p>
          <h1 class="ol-page-title">{{ pupil.full_name }}</h1>
          <p class="hero__mobile">
            <a class="ol-link-action" :href="`tel:${pupil.mobile}`">
              <OlIcon name="phone" :size="14" />
              {{ pupil.mobile }}
            </a>
          </p>
        </div>
        <div class="ol-actions hero__actions">
          <NuxtLink :to="`/lessons/new?learner_id=${pupil.id}`" class="ol-btn ol-btn--sm">Book lesson</NuxtLink>
          <NuxtLink :to="`/pupils/${pupil.id}/edit`" class="ol-btn ol-btn--ghost ol-btn--sm">Edit</NuxtLink>
        </div>
      </header>

      <aside
        v-if="pupil.continuity?.line"
        class="continuity-banner"
        :data-attention="pupil.continuity.needs_attention ? 'yes' : 'no'"
        aria-label="Driving pattern"
      >
        <p class="continuity-banner__line">{{ pupil.continuity.line }}</p>
        <NuxtLink
          v-if="pupil.continuity.needs_attention"
          :to="`/lessons/new?learner_id=${pupil.id}`"
          class="ol-btn ol-btn--sm continuity-banner__action"
        >
          Book lesson
        </NuxtLink>
      </aside>

      <!-- Overview: what the instructor needs right now -->
      <section class="panel">
        <h2 class="panel__title">Overview</h2>
        <div v-if="moneySummary" class="overview-money">
          <div class="overview-money__stat">
            <p class="overview-money__label">Lesson credit</p>
            <p class="overview-money__value">{{ moneySummary.credit_label }}</p>
          </div>
          <div class="overview-money__stat" :data-owes="moneySummary.owes_money ? 'yes' : 'no'">
            <p class="overview-money__label">Balance</p>
            <p class="overview-money__value">{{ moneySummary.money_status_label }}</p>
          </div>
        </div>
        <dl class="facts">
          <div class="facts__row">
            <dt>Email</dt>
            <dd>
              <a v-if="pupil.email" :href="`mailto:${pupil.email}`">{{ pupil.email }}</a>
              <span v-else class="muted">Not set</span>
            </dd>
          </div>
          <div class="facts__row">
            <dt>Usual pickup</dt>
            <dd>{{ pupil.default_pickup_address || 'Not set' }}</dd>
          </div>
        </dl>
      </section>

      <aside v-if="privatePractice" class="practice-blurb" aria-label="Since last lesson">
        <p class="practice-blurb__eyebrow">Since last lesson</p>
        <p class="practice-blurb__text">
          {{ privatePractice.drives }}
          {{ privatePractice.drives === 1 ? 'private drive' : 'private drives' }}
          · {{ privatePractice.duration_label || `${privatePractice.total_minutes} minutes` }}
          <template v-if="privatePractice.skill_codes.length">
            · {{ privatePractice.skill_codes.slice(0, 3).join(', ') }}
          </template>
        </p>
        <p
          v-if="privatePractice.companion_notes"
          class="practice-blurb__note"
        >
          Companion left {{ privatePractice.companion_notes }}
          {{ privatePractice.companion_notes === 1 ? 'practice note' : 'practice notes' }}
        </p>
        <p class="practice-blurb__prov">Learner / companion reports — not your assessment</p>
      </aside>

      <section class="panel" :class="{ 'panel--quiet': !nextLesson }">
        <h2 class="panel__title">Upcoming lesson</h2>
        <NuxtLink v-if="nextLesson" :to="`/lessons/${nextLesson.id}`" class="lesson-row">
          <span class="lesson-row__when">{{ nextLesson.starts_at_display }}</span>
          <span class="lesson-row__meta">
            {{ durationLabel(nextLesson.duration_minutes) }}
            <template v-if="nextLesson.pickup_address"> · {{ nextLesson.pickup_address }}</template>
          </span>
        </NuxtLink>
        <p v-else class="panel__empty">No lessons booked yet.</p>
      </section>

      <section v-if="pastLessons.length" class="panel">
        <h2 class="panel__title">Lesson history</h2>
        <ul class="history">
          <li v-for="item in pastLessons" :key="item.id">
            <NuxtLink :to="`/lessons/${item.id}`" class="lesson-row">
              <span class="lesson-row__when">
                {{ item.starts_at_display }}
                <span class="lesson-row__status">{{ lessonHistoryStatus(item) }}</span>
              </span>
              <span class="lesson-row__meta">
                {{ durationLabel(item.duration_minutes) }}
                <template v-if="item.financial_line"> · {{ item.financial_line }}</template>
              </span>
            </NuxtLink>
          </li>
        </ul>
      </section>

      <section class="panel">
        <div class="panel__head">
          <h2 class="panel__title">Availability</h2>
          <button
            v-if="!editingAvailability"
            class="linkish"
            type="button"
            @click="startAvailabilityEdit"
          >
            {{ availability.length ? 'Edit' : 'Add' }}
          </button>
        </div>
        <p class="panel__hint">
          Optional broad windows — not a detailed calendar. Helps later with gaps and suggestions.
        </p>

        <ul v-if="!editingAvailability && availability.length" class="avail-list">
          <li v-for="item in availability" :key="item.id ?? `${item.weekday}-${item.mode}`">
            <strong>{{ item.weekday_label }}</strong>
            <span>{{ item.label }}</span>
          </li>
        </ul>
        <p v-else-if="!editingAvailability" class="panel__empty">No availability noted yet.</p>

        <div v-else class="avail-edit">
          <div v-for="(row, index) in availabilityDraft" :key="index" class="avail-row">
            <select v-model="row.weekday" class="field__input" aria-label="Day">
              <option v-for="day in weekdays" :key="day.value" :value="day.value">
                {{ day.label }}
              </option>
            </select>
            <select v-model="row.mode" class="field__input" aria-label="When">
              <option value="flexible">Flexible</option>
              <option value="after">After</option>
              <option value="before">Before</option>
              <option value="between">Between</option>
            </select>
            <input
              v-if="row.mode === 'after' || row.mode === 'between'"
              v-model="row.start_time"
              class="field__input"
              type="time"
              aria-label="Start time"
            >
            <input
              v-if="row.mode === 'before' || row.mode === 'between'"
              v-model="row.end_time"
              class="field__input"
              type="time"
              aria-label="End time"
            >
            <button class="ghost" type="button" @click="removeAvailabilityRow(index)">Remove</button>
          </div>
          <div class="avail-actions">
            <button class="btn btn--ghost" type="button" @click="addAvailabilityRow">
              Add day
            </button>
            <button class="btn" type="button" :disabled="savingAvailability" @click="saveAvailability">
              {{ savingAvailability ? 'Saving…' : 'Save' }}
            </button>
            <button class="ghost" type="button" :disabled="savingAvailability" @click="cancelAvailabilityEdit">
              Cancel
            </button>
          </div>
          <p v-if="availabilityError" class="error" role="alert">{{ availabilityError }}</p>
        </div>
      </section>

      <!-- Credit and outstanding — not an accounting dashboard -->
      <section id="money" class="panel money">
        <div class="panel__head">
          <h2 class="panel__title">Credit &amp; balance</h2>
        </div>

        <div v-if="financeLoading" class="muted">Loading money…</div>
        <p v-else-if="financeError" class="error" role="alert">{{ financeError }}</p>
        <template v-else-if="finance">
          <div class="money__answers">
            <div class="money__stat">
              <p class="money__label">Lesson credit</p>
              <p class="money__value">{{ finance.summary.credit_label }}</p>
            </div>
            <div class="money__stat" :data-owes="finance.summary.owes_money ? 'yes' : 'no'">
              <p class="money__label">Balance</p>
              <p class="money__value">{{ finance.summary.money_status_label }}</p>
            </div>
          </div>

          <div class="money__actions">
            <button class="btn" type="button" @click="showPackageForm = !showPackageForm">
              {{ showPackageForm ? 'Cancel package' : 'Add package' }}
            </button>
            <button class="btn btn--ghost" type="button" @click="showPaymentForm = !showPaymentForm">
              {{ showPaymentForm ? 'Cancel' : 'Record payment' }}
            </button>
          </div>

          <form v-if="showPackageForm" class="money-form" @submit.prevent="onCreatePackage">
            <p class="money-form__intro">Bought hours of lesson credit — optional payment in the same step.</p>
            <label class="field">
              <span class="field__label">Hours</span>
              <input v-model="packageHours" class="field__input" inputmode="decimal" placeholder="10" required>
            </label>
            <label class="field">
              <span class="field__label">Price (£)</span>
              <input v-model="packagePrice" class="field__input" inputmode="decimal" placeholder="320" required>
            </label>
            <label class="field">
              <span class="field__label">Label (optional)</span>
              <input v-model="packageLabel" class="field__input" placeholder="10-hour block">
            </label>
            <label class="check">
              <input v-model="packageRecordPayment" type="checkbox">
              <span>Payment received for this package</span>
            </label>
            <label v-if="packageRecordPayment" class="field">
              <span class="field__label">Method</span>
              <select v-model="packageMethod" class="field__input">
                <option value="bank_transfer">Bank transfer</option>
                <option value="cash">Cash</option>
                <option value="other">Other</option>
              </select>
            </label>
            <button class="btn" type="submit" :disabled="savingFinance">
              {{ savingFinance ? 'Saving…' : 'Save package' }}
            </button>
            <p v-if="financeFormError" class="error" role="alert">{{ financeFormError }}</p>
          </form>

          <form v-if="showPaymentForm" class="money-form" @submit.prevent="onRecordPayment">
            <p class="money-form__intro">Cash or bank transfer. Put against what they still owe first.</p>
            <label class="field">
              <span class="field__label">Amount (£)</span>
              <input v-model="paymentAmount" class="field__input" inputmode="decimal" placeholder="35" required>
            </label>
            <label class="field">
              <span class="field__label">Method</span>
              <select v-model="paymentMethod" class="field__input">
                <option value="cash">Cash</option>
                <option value="bank_transfer">Bank transfer</option>
                <option value="other">Other</option>
              </select>
            </label>
            <label class="field">
              <span class="field__label">Notes (optional)</span>
              <input v-model="paymentNotes" class="field__input" placeholder="Paid after lesson">
            </label>
            <button class="btn" type="submit" :disabled="savingFinance">
              {{ savingFinance ? 'Saving…' : 'Save payment' }}
            </button>
            <p v-if="financeFormError" class="error" role="alert">{{ financeFormError }}</p>
          </form>

          <div v-if="finance.packages.length" class="packages">
            <h3 class="packages__title">Packages</h3>
            <ul class="packages__list">
              <li v-for="pkg in finance.packages" :key="pkg.id" class="packages__item">
                <div>
                  <p class="packages__name">{{ pkg.label || 'Package' }}</p>
                  <p class="packages__meta">
                    {{ pkg.remaining_label }}
                    · {{ pkg.used_minutes > 0 ? `${Math.floor(pkg.used_minutes / 60)}h used` : 'unused' }}
                    · {{ pkg.price_label }}
                    <span class="packages__status">{{ pkg.status }}</span>
                  </p>
                </div>
                <button
                  v-if="pkg.status === 'active' && pkg.remaining_minutes > 0"
                  class="ghost"
                  type="button"
                  @click="onVoidPackage(pkg.id)"
                >
                  Void remaining
                </button>
              </li>
            </ul>
          </div>

          <div v-if="finance.history.length" class="tx">
            <h3 class="tx__title">History</h3>
            <ul class="tx__list">
              <li v-for="item in finance.history" :key="item.id" class="tx__item" :data-voided="item.voided ? '1' : '0'">
                <div>
                  <p class="tx__when">{{ item.at_display }}</p>
                  <p class="tx__name">{{ item.title }}</p>
                  <p class="tx__detail">{{ item.detail }}</p>
                </div>
                <button
                  v-if="item.kind === 'payment_received' && !item.voided && item.payment_id"
                  class="ghost"
                  type="button"
                  @click="onVoidPayment(item.payment_id!)"
                >
                  Void
                </button>
              </li>
            </ul>
          </div>
        </template>
      </section>

      <section class="panel">
        <h2 class="panel__title">Practical test</h2>
        <template v-if="pupil.test_journey">
          <p class="test__countdown">{{ pupil.test_journey.countdown_label }}</p>
          <dl class="facts">
            <div class="facts__row">
              <dt>Date</dt>
              <dd>{{ pupil.test_journey.test_date_display }}</dd>
            </div>
            <div class="facts__row">
              <dt>Centre</dt>
              <dd>{{ pupil.test_journey.test_centre || 'Not set' }}</dd>
            </div>
            <div class="facts__row">
              <dt>Before test</dt>
              <dd>
                {{ pupil.test_journey.lessons_booked_before_test }}
                {{ pupil.test_journey.lessons_booked_before_test === 1 ? 'lesson' : 'lessons' }}
                currently booked
              </dd>
            </div>
            <div v-if="pupil.test_journey.progress_summary" class="facts__row">
              <dt>Progress</dt>
              <dd>{{ pupil.test_journey.progress_summary }}</dd>
            </div>
            <div v-if="pupil.test_journey.next_focus" class="facts__row">
              <dt>Priority</dt>
              <dd>{{ pupil.test_journey.next_focus }}</dd>
            </div>
          </dl>
          <p class="test__note">
            Context only — you decide when they’re ready.
          </p>
        </template>
        <dl v-else class="facts">
          <div class="facts__row">
            <dt>Test date</dt>
            <dd>{{ formatDate(pupil.test_date) }}</dd>
          </div>
          <div class="facts__row">
            <dt>Test centre</dt>
            <dd>{{ pupil.test_centre || 'Not set' }}</dd>
          </div>
        </dl>
      </section>

      <section class="panel">
        <h2 class="panel__title">Theory</h2>
        <p v-if="pupil.theory" class="theory" :data-urgency="pupil.theory.urgency">
          {{ pupil.theory.label }}
        </p>
        <p v-else class="panel__empty">Not recorded yet.</p>
      </section>

      <section v-if="learnerSaysSkills.length" class="panel">
        <h2 class="panel__title">They say they’ve covered</h2>
        <p class="panel__hint">As reported by the pupil — not an assessment.</p>
        <ul class="skill-chips">
          <li v-for="skill in learnerSaysSkills" :key="skill" class="skill-chip">
            {{ skill }}
          </li>
        </ul>
      </section>

      <section class="panel">
        <h2 class="panel__title">Private notes</h2>
        <p class="notes">
          {{ pupil.private_notes || 'No private notes yet.' }}
        </p>
        <p class="notes__hint">Only you can see these — never shown to the pupil.</p>
      </section>

      <section class="panel">
        <div class="panel__head">
          <h2 class="panel__title">Mock tests</h2>
          <NuxtLink :to="`/pupils/${pupil.id}/mocks`" class="ol-link-action">View all</NuxtLink>
        </div>
        <p class="panel__hint">Conduct mocks during lessons and track fault patterns over time.</p>
        <NuxtLink :to="`/pupils/${pupil.id}/mocks`" class="ol-btn ol-btn--sm">Mock tests</NuxtLink>
      </section>

      <section class="panel">
        <div class="panel__head">
          <h2 class="panel__title">OwnLane access</h2>
        </div>
        <p class="panel__hint">
          A simple page for {{ pupil.first_name }} — next lesson, progress, and balance. Never shows
          your private notes.
        </p>

        <template v-if="pupil.portal?.status === 'connected'">
          <p class="portal-status portal-status--connected">
            Connected
            <span v-if="pupil.portal.connected_since_display" class="portal-status__meta">
              since {{ pupil.portal.connected_since_display }}
            </span>
          </p>
          <p class="ol-meta">{{ pupil.portal.portal_email || pupil.portal.email }}</p>
        </template>

        <template v-else-if="pupil.portal?.status === 'invite_pending'">
          <p class="portal-status">Invite pending</p>
          <p class="ol-meta">Waiting for {{ pupil.first_name }} to set a password</p>
          <div class="portal-actions">
            <button
              class="btn btn--ghost"
              type="button"
              :disabled="invitingPortal"
              @click="showInviteDialog = true"
            >
              {{ inviteButtonLabel }}
            </button>
          </div>
        </template>

        <template v-else-if="pupil.portal?.status === 'invite_expired'">
          <p class="portal-status">Invite expired</p>
          <div class="portal-actions">
            <button
              class="btn"
              type="button"
              :disabled="invitingPortal"
              @click="showInviteDialog = true"
            >
              {{ inviteButtonLabel }}
            </button>
          </div>
        </template>

        <template v-else>
          <p class="portal-status">
            {{ pupil.first_name }} hasn't set up their learner account.
          </p>
          <p v-if="!pupil.email" class="panel__empty">
            Add an email on Edit before you can invite them.
          </p>
          <button
            v-else
            class="btn"
            type="button"
            :disabled="invitingPortal"
            @click="showInviteDialog = true"
          >
            Invite {{ pupil.first_name }}
          </button>
        </template>

        <div v-if="showInviteDialog" class="invite-dialog" role="dialog" aria-labelledby="invite-title">
          <h3 id="invite-title" class="invite-dialog__title">
            Invite {{ pupil.first_name }} to OwnLane
          </h3>
          <p class="invite-dialog__email">{{ pupil.email }}</p>
          <p class="invite-dialog__copy">
            They'll be able to see their lessons, progress, lesson recaps and anything you've
            shared with them.
          </p>
          <div class="invite-dialog__actions">
            <button class="btn" type="button" :disabled="invitingPortal" @click="onInvitePortal">
              {{ invitingPortal ? 'Working…' : inviteButtonLabel }}
            </button>
            <button class="ghost" type="button" :disabled="invitingPortal" @click="showInviteDialog = false">
              Cancel
            </button>
          </div>
        </div>

        <div v-if="portalInviteUrl" class="invite-result">
          <p class="invite-result__title">Invite link ready</p>
          <p class="ol-meta">{{ portalInviteMessage }}</p>
          <button class="btn btn--ghost" type="button" @click="onCopyPortalLink">
            {{ portalCopied ? 'Copied' : 'Copy link' }}
          </button>
        </div>

        <p v-if="portalInviteError" class="error" role="alert">{{ portalInviteError }}</p>
      </section>

      <section class="danger">
        <button
          v-if="!confirmArchive"
          class="danger__btn"
          type="button"
          @click="confirmArchive = true"
        >
          Archive pupil
        </button>
        <div v-else class="danger__confirm">
          <p>Archive {{ pupil.first_name }}? They’ll leave your active list.</p>
          <div class="danger__actions">
            <button class="danger__btn" type="button" :disabled="archiving" @click="onArchive">
              {{ archiving ? 'Archiving…' : 'Yes, archive' }}
            </button>
            <button class="ghost" type="button" :disabled="archiving" @click="confirmArchive = false">
              Cancel
            </button>
          </div>
        </div>
      </section>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { AvailabilityWindow, AvailabilityWrite, Pupil } from '~/composables/usePupils'
import type { FinancePanel } from '~/composables/useFinance'
import type { Lesson } from '~/composables/useLessons'
import { INTAKE_SKILL_GROUPS } from '~/composables/useIntake'

const route = useRoute()
const id = computed(() => Number(route.params.id))

const { getPupil, archivePupil, listAvailability, replaceAvailability, invitePortal } = usePupils()
const { listForPupil, durationLabel } = useLessons()
const { fetchPanel, createPackage, recordPayment, voidPayment, voidPackage, poundsInputToPence } = useFinance()
const pupil = ref<Pupil | null>(null)
const lessons = ref<Lesson[]>([])
const availability = ref<AvailabilityWindow[]>([])
const finance = ref<FinancePanel | null>(null)
const privatePractice = ref<{
  drives: number
  total_minutes: number
  duration_label?: string
  skill_codes: string[]
  companion_notes?: number
} | null>(null)
const loading = ref(true)
const error = ref('')
const financeLoading = ref(false)
const financeError = ref('')
const financeFormError = ref('')
const savingFinance = ref(false)
const showPackageForm = ref(false)
const invitingPortal = ref(false)
const portalInviteUrl = ref('')
const portalInviteMessage = ref('')
const portalInviteError = ref('')
const portalCopied = ref(false)
const showInviteDialog = ref(false)
const showPaymentForm = ref(false)
const packageHours = ref('10')
const packagePrice = ref('')
const packageLabel = ref('')
const packageRecordPayment = ref(true)
const packageMethod = ref<'cash' | 'bank_transfer' | 'other'>('bank_transfer')
const paymentAmount = ref('')
const paymentMethod = ref<'cash' | 'bank_transfer' | 'other'>('cash')
const paymentNotes = ref('')
const confirmArchive = ref(false)
const archiving = ref(false)

const editingAvailability = ref(false)
const savingAvailability = ref(false)
const availabilityError = ref('')
const availabilityDraft = ref<AvailabilityWrite[]>([])

const weekdays = [
  { value: 1, label: 'Monday' },
  { value: 2, label: 'Tuesday' },
  { value: 3, label: 'Wednesday' },
  { value: 4, label: 'Thursday' },
  { value: 5, label: 'Friday' },
  { value: 6, label: 'Saturday' },
  { value: 7, label: 'Sunday' },
]

const nextLesson = computed(() =>
  lessons.value.find(l => l.status === 'scheduled' && new Date(l.starts_at).getTime() >= Date.now())
  ?? null,
)

const pastLessons = computed(() =>
  lessons.value.filter(l => l.id !== nextLesson.value?.id).slice(0, 8),
)

const moneySummary = computed(() =>
  finance.value?.summary ?? pupil.value?.finance ?? null,
)

const inviteButtonLabel = computed(() => {
  if (pupil.value?.portal?.delivery_available) {
    return pupil.value.portal.status === 'not_invited' ? 'Send invite' : 'Send new invite'
  }
  return pupil.value?.portal?.status === 'not_invited' ? 'Create invite link' : 'Create new invite link'
})

const learnerSaysSkills = computed(() => {
  const keys = pupil.value?.learner_reported?.skills_practised
  if (!keys?.length) return []
  return keys.map(k => INTAKE_SKILL_GROUPS[k] ?? k)
})

useHead(() => ({
  title: pupil.value ? `${pupil.value.full_name} · OwnLane` : 'Pupil · OwnLane',
}))

function lessonHistoryStatus(item: Lesson): string {
  if (item.status_label) return item.status_label
  if (item.status === 'no_show') return 'No-show'
  if (item.status === 'cancelled') return 'Cancelled'
  if (item.status === 'completed') return 'Completed'
  return item.status
}

function formatDate(value: string | null): string {
  if (!value) return 'Not set'
  const d = new Date(`${value}T00:00:00`)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

async function loadFinance() {
  financeLoading.value = true
  financeError.value = ''
  try {
    finance.value = await fetchPanel(id.value)
    if (route.query.pay === '1') {
      showPaymentForm.value = true
      showPackageForm.value = false
      await nextTick()
      document.getElementById('money')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
  } catch (e) {
    financeError.value = extractApiError(e, 'Could not load money.')
  } finally {
    financeLoading.value = false
  }
}

async function onCreatePackage() {
  financeFormError.value = ''
  const pence = poundsInputToPence(packagePrice.value)
  if (pence === null) {
    financeFormError.value = 'Enter a price like 320 or 320.00'
    return
  }
  savingFinance.value = true
  try {
    await createPackage(id.value, {
      purchased_hours: packageHours.value,
      price_pence: pence,
      label: packageLabel.value || undefined,
      record_payment: packageRecordPayment.value,
      payment_method: packageMethod.value,
    })
    showPackageForm.value = false
    packagePrice.value = ''
    packageLabel.value = ''
    await loadFinance()
  } catch (e) {
    financeFormError.value = extractApiError(e, 'Could not save package.')
  } finally {
    savingFinance.value = false
  }
}

async function onRecordPayment() {
  financeFormError.value = ''
  const pence = poundsInputToPence(paymentAmount.value)
  if (pence === null || pence <= 0) {
    financeFormError.value = 'Enter an amount like 35 or 35.00'
    return
  }
  savingFinance.value = true
  try {
    await recordPayment(id.value, {
      amount_pence: pence,
      method: paymentMethod.value,
      notes: paymentNotes.value || undefined,
    })
    showPaymentForm.value = false
    paymentAmount.value = ''
    paymentNotes.value = ''
    await loadFinance()
  } catch (e) {
    financeFormError.value = extractApiError(e, 'Could not record payment.')
  } finally {
    savingFinance.value = false
  }
}

async function onVoidPayment(paymentId: number) {
  const reason = window.prompt('Why void this payment?')
  if (!reason?.trim()) return
  try {
    await voidPayment(paymentId, reason.trim())
    await loadFinance()
  } catch (e) {
    financeError.value = extractApiError(e, 'Could not void payment.')
  }
}

async function onVoidPackage(packageId: number) {
  const reason = window.prompt('Why void remaining credit on this package?')
  if (!reason?.trim()) return
  try {
    await voidPackage(packageId, reason.trim())
    await loadFinance()
  } catch (e) {
    financeError.value = extractApiError(e, 'Could not void package.')
  }
}

function blankAvailabilityRow(): AvailabilityWrite {
  return {
    weekday: 1,
    mode: 'after',
    start_time: '16:00',
    end_time: null,
  }
}

function startAvailabilityEdit() {
  availabilityDraft.value = availability.value.length
    ? availability.value.map(item => ({
        weekday: item.weekday,
        mode: item.mode,
        start_time: item.start_time,
        end_time: item.end_time,
      }))
    : [blankAvailabilityRow()]
  editingAvailability.value = true
  availabilityError.value = ''
}

function cancelAvailabilityEdit() {
  editingAvailability.value = false
  availabilityError.value = ''
}

function addAvailabilityRow() {
  availabilityDraft.value.push(blankAvailabilityRow())
}

function removeAvailabilityRow(index: number) {
  availabilityDraft.value.splice(index, 1)
}

async function saveAvailability() {
  savingAvailability.value = true
  availabilityError.value = ''
  try {
    availability.value = await replaceAvailability(
      id.value,
      availabilityDraft.value.map(row => ({
        weekday: Number(row.weekday),
        mode: row.mode,
        start_time: row.start_time || null,
        end_time: row.end_time || null,
      })),
    )
    editingAvailability.value = false
  } catch (e) {
    availabilityError.value = extractApiError(e, 'Could not save availability.')
  } finally {
    savingAvailability.value = false
  }
}

async function load() {
  loading.value = true
  error.value = ''
  editingAvailability.value = false
  portalInviteUrl.value = ''
  portalInviteMessage.value = ''
  portalInviteError.value = ''
  portalCopied.value = false
  try {
    const [pupilData, lessonData, availabilityData] = await Promise.all([
      getPupil(id.value),
      listForPupil(id.value),
      listAvailability(id.value),
    ])
    pupil.value = pupilData
    lessons.value = lessonData
    availability.value = availabilityData
    await loadFinance()
    try {
      const pp = await apiFetch<{
        since_last_lesson: {
          drives: number
          total_minutes: number
          skill_codes: string[]
        } | null
      }>(`/learners/${id.value}/private-practice`)
      privatePractice.value = pp.since_last_lesson
    } catch {
      privatePractice.value = null
    }
  } catch (e) {
    pupil.value = null
    lessons.value = []
    availability.value = []
    finance.value = null
    privatePractice.value = null
    error.value = extractApiError(e, 'Could not load this pupil.')
  } finally {
    loading.value = false
  }
}

async function onArchive() {
  archiving.value = true
  try {
    await archivePupil(id.value)
    await navigateTo('/pupils')
  } catch (e) {
    error.value = extractApiError(e, 'Could not archive this pupil.')
    confirmArchive.value = false
  } finally {
    archiving.value = false
  }
}

async function onInvitePortal() {
  invitingPortal.value = true
  portalInviteError.value = ''
    portalInviteMessage.value = ''
    portalCopied.value = false
    try {
      const result = await invitePortal(id.value)
      portalInviteUrl.value = `${window.location.origin}${result.invite_path}`
      portalInviteMessage.value = result.message
      showInviteDialog.value = false
      pupil.value = await getPupil(id.value)
  } catch (e) {
    portalInviteError.value = extractApiError(e, 'Could not send invite.')
  } finally {
    invitingPortal.value = false
  }
}

async function onCopyPortalLink() {
  if (!portalInviteUrl.value) return
  try {
    await navigator.clipboard.writeText(portalInviteUrl.value)
    portalCopied.value = true
    window.setTimeout(() => {
      portalCopied.value = false
    }, 2000)
  } catch {
    portalInviteError.value = 'Could not copy — select and copy the link manually.'
  }
}

onMounted(() => {
  void load()
})

watch(id, () => {
  void load()
})
</script>

<style scoped>
.page {
  max-width: 720px;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
}

.back {
  font-size: var(--text-body-sm);
  color: var(--color-ownlane-green);
  align-self: flex-start;
}

.hero {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--spacing-16);
  margin-bottom: var(--spacing-8);
}

.hero__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.6;
  margin-bottom: var(--spacing-8);
}

.hero__title {
  font-size: var(--text-heading-sm);
  line-height: var(--leading-heading-sm);
}

.hero__mobile {
  margin-top: var(--spacing-8);
  font-size: var(--text-body);
}

.continuity-banner {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-12);
  padding: var(--spacing-12) var(--spacing-16);
  border-radius: var(--radius-panel);
  background: var(--color-surface-raised, #f7faf8);
  border: 1px solid var(--color-border);
}

.continuity-banner[data-attention='yes'] {
  background: var(--color-warning-wash);
  border-color: color-mix(in srgb, var(--color-warning) 30%, var(--color-border));
}

.continuity-banner__line {
  margin: 0;
  font-size: var(--text-body-sm);
  font-weight: 500;
  flex: 1;
  min-width: 12rem;
}

.continuity-banner__action {
  flex-shrink: 0;
}

.hero__mobile a {
  color: var(--color-ownlane-green);
}

.hero__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
  justify-content: flex-end;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 44px;
  padding: 10px 20px;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  box-shadow: var(--shadow-button);
  flex-shrink: 0;
}

.btn--ghost {
  background: transparent;
  color: var(--color-ink-black);
  box-shadow: none;
  border: 1px solid var(--color-frost-green);
}

.lesson-row {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4);
  padding: var(--spacing-12) 0;
}

.lesson-row__when {
  font-size: var(--text-body-sm);
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
  align-items: baseline;
}

.lesson-row__status {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.55;
}

.lesson-row__meta {
  font-size: 14px;
  opacity: 0.7;
}

.history {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
}

.history li + li {
  border-top: 1px solid var(--color-frost-green);
}

.panel {
  background: var(--surface-card);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-cards);
  box-shadow: var(--shadow-card);
  padding: var(--spacing-20);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.practice-blurb {
  padding: var(--spacing-16);
  background: var(--color-frost-green);
  border-radius: var(--radius-panel);
}

.practice-blurb__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-ownlane-green);
  margin-bottom: 6px;
}

.practice-blurb__text {
  font-size: var(--text-body-sm);
}

.panel--quiet {
  box-shadow: none;
  background: transparent;
  border-style: dashed;
}

.panel__title {
  font-size: var(--text-subheading);
  line-height: var(--leading-subheading);
  letter-spacing: var(--tracking-subheading);
}

.panel__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--spacing-12);
}

.panel__hint {
  font-size: 14px;
  opacity: 0.65;
  max-width: 44ch;
  margin: 0;
}

.panel__empty {
  font-size: var(--text-body-sm);
  opacity: 0.65;
  max-width: 42ch;
}

.theory {
  font-size: var(--text-body-sm);
  padding: 10px 14px;
  border-radius: var(--radius-small);
  background: var(--color-chalk-green);
  align-self: flex-start;
  margin: 0;
}

.theory[data-urgency='soon'],
.theory[data-urgency='approaching'] {
  background: var(--color-hi-yellow);
}

.theory[data-urgency='expired'] {
  background: var(--color-bubblegum-pink);
}

.skill-chips {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
}

.skill-chip {
  padding: 8px 14px;
  border-radius: 999px;
  background: var(--color-chalk-green);
  font-size: var(--text-body-sm);
}

.linkish {
  border: none;
  background: transparent;
  color: var(--color-ownlane-green);
  font: inherit;
  font-size: var(--text-body-sm);
  cursor: pointer;
  padding: 0;
}

.avail-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
  font-size: var(--text-body-sm);
}

.avail-list li {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
}

.avail-edit {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.avail-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--spacing-8);
}

.avail-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
  align-items: center;
}

.field__input {
  width: 100%;
  min-height: 44px;
  padding: 10px 14px;
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-inputs);
  background: var(--surface-canvas);
  font: inherit;
}

.facts {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.facts__row {
  display: grid;
  grid-template-columns: 120px 1fr;
  gap: var(--spacing-12);
  font-size: var(--text-body-sm);
}

.facts__row dt {
  opacity: 0.65;
}

.notes {
  font-size: var(--text-body-sm);
  white-space: pre-wrap;
}

.notes__hint {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.5;
}

.portal-status {
  font-size: var(--text-body-sm);
}

.portal-status--connected {
  font-weight: 600;
}

.portal-status__meta {
  font-weight: 400;
  opacity: 0.7;
}

.portal-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
}

.invite-dialog {
  margin-top: var(--spacing-16);
  padding: var(--spacing-16);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-small);
  background: var(--color-frost-green);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.invite-dialog__title {
  font-size: var(--text-body-lg);
  font-weight: 600;
}

.invite-dialog__email {
  font-family: var(--font-martian-mono);
  font-size: var(--text-body-sm);
}

.invite-dialog__copy {
  font-size: var(--text-body-sm);
  opacity: 0.85;
}

.invite-dialog__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
}

.invite-result {
  margin-top: var(--spacing-16);
  padding: var(--spacing-16);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-small);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.invite-result__title {
  font-weight: 600;
  font-size: var(--text-body-sm);
}

.portal-msg {
  font-size: var(--text-body-sm);
  opacity: 0.8;
}

.test__countdown {
  font-size: var(--text-body-sm);
  color: var(--color-ownlane-green);
  margin-bottom: var(--spacing-12);
}

.test__note {
  margin-top: var(--spacing-12);
  font-size: 14px;
  opacity: 0.6;
  max-width: 40ch;
}

.overview-money {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--spacing-12);
  margin-bottom: var(--spacing-8);
}

.overview-money__stat {
  padding: var(--spacing-16);
  border-radius: var(--radius-small);
  background: var(--color-frost-green);
}

.overview-money__stat[data-owes='yes'] {
  background: #ffe8e8;
}

.overview-money__label {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.6;
  margin-bottom: var(--spacing-4);
}

.overview-money__value {
  font-size: var(--text-body-sm);
}

.money__answers {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--spacing-12);
}

.money__stat {
  padding: var(--spacing-16);
  border-radius: var(--radius-small);
  background: var(--color-frost-green);
}

.money__stat[data-owes='yes'] {
  background: #ffe8e8;
}

.money__label {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.6;
  margin-bottom: var(--spacing-4);
}

.money__value {
  font-size: var(--text-body-sm);
}

.money__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-12);
}

.money-form {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
  padding: var(--spacing-16);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-small);
}

.money-form__intro {
  font-size: 14px;
  opacity: 0.7;
  max-width: 42ch;
  margin: 0;
}

.field {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4);
}

.field__label {
  font-size: 14px;
  opacity: 0.7;
}

.field__input {
  min-height: 48px;
  padding: 12px 14px;
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-small);
  background: var(--color-paper-white);
  font: inherit;
}

.check {
  display: flex;
  align-items: center;
  gap: var(--spacing-8);
  font-size: var(--text-body-sm);
}

.packages__title,
.tx__title {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.55;
}

.packages__list,
.tx__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.packages__item,
.tx__item {
  display: flex;
  justify-content: space-between;
  gap: var(--spacing-12);
  align-items: flex-start;
  padding: var(--spacing-12) 0;
  border-top: 1px solid var(--color-frost-green);
}

.packages__name,
.tx__name {
  font-size: var(--text-body-sm);
}

.packages__meta,
.tx__detail,
.tx__when {
  font-size: 14px;
  opacity: 0.7;
  margin-top: var(--spacing-4);
}

.packages__status {
  text-transform: uppercase;
  font-family: var(--font-martian-mono);
  font-size: 11px;
  letter-spacing: 0.04em;
  margin-left: var(--spacing-4);
}

.tx__item[data-voided='1'] {
  opacity: 0.55;
}

.danger {
  margin-top: var(--spacing-8);
  padding: var(--spacing-8) 0 var(--spacing-24);
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

.danger__confirm {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
  font-size: var(--text-body-sm);
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
}

@media (max-width: 480px) {
  .facts__row {
    grid-template-columns: 1fr;
    gap: var(--spacing-4);
  }
}
</style>
