<template>
  <section class="ol-page pupil-page">
    <NuxtLink to="/pupils" class="ol-back">← Pupils</NuxtLink>

    <p v-if="loading" class="ol-muted">Loading…</p>
    <p v-else-if="error" class="ol-error" role="alert">{{ error }}</p>

    <template v-else-if="pupil">
      <header class="hero-band">
        <div class="hero-band__body">
          <div class="hero-band__identity">
            <div class="hero-band__avatar" aria-hidden="true">{{ pupilInitials(pupil) }}</div>
            <div class="hero-band__copy">
              <h1 class="hero-band__name">{{ pupil.full_name }}</h1>
              <p class="hero-band__email">{{ pupil.email || pupil.mobile }}</p>
              <p class="hero-band__stats">
                <span><strong>{{ pastLessonCount }}</strong> Past</span>
                <span class="hero-band__stats-sep" aria-hidden="true"></span>
                <span><strong>{{ upcomingLessonCount }}</strong> Upcoming</span>
              </p>
            </div>
          </div>
          <div class="hero-band__actions">
            <a
              v-if="pupil.mobile"
              class="hero-band__cta"
              :href="`sms:${pupil.mobile}`"
            >Send message</a>
            <span v-else class="hero-band__cta hero-band__cta--disabled">Send message</span>
          </div>
        </div>

        <div class="tabs tabs--page" role="tablist" aria-label="Pupil sections">
          <button
            v-for="tab in PUPIL_TABS"
            :key="tab.id"
            class="tabs__btn"
            type="button"
            role="tab"
            :aria-selected="activeTab === tab.id"
            :data-active="activeTab === tab.id ? 'yes' : 'no'"
            @click="setTab(tab.id)"
          >
            {{ tab.label }}
          </button>
        </div>
      </header>

      <div class="profile-body">
        <!-- Overview dashboard -->
        <div v-show="activeTab === 'details'" class="dash" role="tabpanel">
          <section class="dash-card dash-card--details">
            <dl class="details-grid">
              <div class="fact-cell">
                <dt>Gender</dt>
                <dd>{{ pupil.gender_label || '—' }}</dd>
              </div>
              <div class="fact-cell">
                <dt>Birthday</dt>
                <dd>
                  <template v-if="pupil.date_of_birth">{{ formatDate(pupil.date_of_birth) }}</template>
                  <template v-else>—</template>
                </dd>
              </div>
              <div class="fact-cell">
                <dt>Phone number</dt>
                <dd><a :href="`tel:${pupil.mobile}`">{{ pupil.mobile }}</a></dd>
              </div>
              <div class="fact-cell">
                <dt>Transmission</dt>
                <dd>{{ transmissionLabel(pupil.transmission) || '—' }}</dd>
              </div>
              <div class="fact-cell">
                <dt>Reach by</dt>
                <dd class="fact-cell__nowrap">{{ preferredContactLabel(pupil.preferred_contact) || '—' }}</dd>
              </div>
              <div class="fact-cell">
                <dt>Theory</dt>
                <dd>{{ pupil.theory?.label || '—' }}</dd>
              </div>
              <div class="fact-cell fact-cell--wide">
                <dt>Status</dt>
                <dd>
                  <div ref="statusMenuRoot" class="status-field__wrap status-field__wrap--inline">
                    <button
                      class="status-field__trigger status-field__trigger--plain"
                      type="button"
                      :disabled="savingStatus"
                      :aria-expanded="statusMenuOpen"
                      aria-haspopup="listbox"
                      aria-label="Change status"
                      @click="statusMenuOpen = !statusMenuOpen"
                    >
                      {{ statusLabel(currentStatus) }}
                      <OlIcon name="chevron-down" :size="14" />
                    </button>
                    <div
                      v-if="statusMenuOpen"
                      class="status-field__menu"
                      role="listbox"
                      aria-label="Pupil status"
                    >
                      <button
                        v-for="opt in statusChoices"
                        :key="`dash-${opt.value}`"
                        class="status-field__option"
                        type="button"
                        role="option"
                        :aria-selected="currentStatus === opt.value"
                        @click="onStatusPick(opt.value)"
                      >
                        {{ opt.label }}
                      </button>
                    </div>
                  </div>
                </dd>
              </div>
              <div class="fact-cell fact-cell--wide">
                <dt>Address</dt>
                <dd>{{ pupil.default_pickup_address || pupil.places?.[0]?.address || '—' }}</dd>
              </div>
            </dl>
          </section>

          <section class="dash-card dash-card--notes">
            <div class="dash-card__head">
              <h2 class="dash-card__title">Notes</h2>
              <NuxtLink :to="`/pupils/${pupil.id}/edit`" class="dash-card__action">See all</NuxtLink>
            </div>
            <div class="notes-box">
              <p class="notes-box__text">{{ pupil.private_notes || 'No private notes yet.' }}</p>
            </div>
            <p class="notes-box__meta">Private · only you</p>
          </section>

          <section class="dash-card dash-card--places">
            <div class="dash-card__head">
              <h2 class="dash-card__title">Places</h2>
              <NuxtLink :to="`/pupils/${pupil.id}/edit`" class="dash-card__action">Edit</NuxtLink>
            </div>
            <ul v-if="pupil.places?.length" class="file-list">
              <li v-for="place in pupil.places" :key="place.id" class="file-row">
                <span class="file-row__icon" aria-hidden="true">
                  <OlIcon name="pin" :size="16" />
                </span>
                <span class="file-row__body">
                  <span class="file-row__name">
                    {{ place.label }}
                    <template v-if="place.is_default"> · Usual</template>
                  </span>
                  <span class="file-row__meta">{{ place.address }}</span>
                </span>
              </li>
            </ul>
            <p v-else class="dash-empty dash-empty--left">
              {{ pupil.default_pickup_address || 'No places saved.' }}
            </p>
          </section>

          <section class="dash-card dash-card--lessons">
            <div class="dash-card__head dash-card__head--lessons">
              <div class="lesson-tabs" role="tablist" aria-label="Lessons">
                <button
                  type="button"
                  class="lesson-tabs__btn"
                  role="tab"
                  :data-active="lessonDashTab === 'upcoming' ? 'yes' : 'no'"
                  @click="lessonDashTab = 'upcoming'"
                >
                  Upcoming lessons
                </button>
                <button
                  type="button"
                  class="lesson-tabs__btn"
                  role="tab"
                  :data-active="lessonDashTab === 'past' ? 'yes' : 'no'"
                  @click="lessonDashTab = 'past'"
                >
                  Past lessons
                </button>
              </div>
              <NuxtLink
                :to="`/lessons/new?learner_id=${pupil.id}`"
                class="dash-card__cta"
              >
                Book lesson
              </NuxtLink>
            </div>

            <ul v-if="dashLessons.length" class="appt-list">
              <li v-for="item in dashLessons" :key="item.id" class="appt-row">
                <span class="appt-row__rail" aria-hidden="true"></span>
                <NuxtLink :to="`/lessons/${item.id}`" class="appt-row__body">
                  <span class="appt-row__when">
                    <span class="appt-row__date">{{ lessonDateLabel(item) }}</span>
                    <span class="appt-row__time">{{ lessonTimeLabel(item) }}</span>
                  </span>
                  <span class="appt-row__type">{{ durationLabel(item.duration_minutes) }}</span>
                  <span class="appt-row__place">{{ item.pickup_address || 'No pickup set' }}</span>
                  <span class="appt-row__status">{{ item.status_label || item.status }}</span>
                </NuxtLink>
              </li>
            </ul>
            <p v-else class="dash-empty">
              <template v-if="lessonDashTab === 'upcoming'">No upcoming lessons.</template>
              <template v-else>No past lessons yet.</template>
            </p>
          </section>

          <section class="dash-card dash-card--pay">
            <div class="dash-card__head">
              <h2 class="dash-card__title">Payments</h2>
            </div>
            <div v-if="recentPayments.length" class="pay-head">
              <span>Transaction</span>
              <span>Amount</span>
            </div>
            <ul v-if="recentPayments.length" class="pay-list">
              <li v-for="item in recentPayments" :key="item.id" class="pay-list__item">
                <span class="pay-list__dot" :data-kind="item.kind" aria-hidden="true"></span>
                <span class="pay-list__title">{{ item.title }}</span>
                <OlIcon name="chevron-right" :size="14" class="pay-list__chev" />
                <span class="pay-list__amount">{{ item.amount_label || '—' }}</span>
              </li>
            </ul>
            <p v-else class="dash-empty dash-empty--left">No payments yet.</p>
            <div v-if="moneySummary" class="pay-total">
              <span>Balance</span>
              <strong>{{ moneySummary.money_status_label }}</strong>
            </div>
            <button type="button" class="dash-card__action pay-see-all" @click="setTab('payments')">
              See all
            </button>
          </section>
        </div>

          <!-- Lessons -->
          <div v-show="activeTab === 'lessons'" class="tab-panel" role="tabpanel">
            <section class="block">
              <h2 class="block__title">Upcoming</h2>
              <NuxtLink v-if="nextLesson" :to="`/lessons/${nextLesson.id}`" class="lesson-tile">
                <span class="lesson-tile__when">{{ nextLesson.starts_at_display }}</span>
                <span class="lesson-tile__meta">
                  {{ durationLabel(nextLesson.duration_minutes) }}
                  <template v-if="nextLesson.pickup_address"> · {{ nextLesson.pickup_address }}</template>
                </span>
              </NuxtLink>
              <p v-else class="block__empty">No lessons booked yet.</p>
            </section>

            <section v-if="pastLessons.length" class="block">
              <h2 class="block__title">Lesson history</h2>
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

            <section class="block">
              <div class="block__head">
                <h2 class="block__title">Availability</h2>
                <button
                  v-if="!editingAvailability"
                  class="linkish"
                  type="button"
                  @click="startAvailabilityEdit"
                >
                  {{ availability.length ? 'Edit' : 'Add' }}
                </button>
              </div>
              <p class="block__hint">
                Optional broad windows — not a detailed calendar. Helps later with gaps and suggestions.
              </p>

              <ul v-if="!editingAvailability && availability.length" class="avail-list">
                <li v-for="item in availability" :key="item.id ?? `${item.weekday}-${item.mode}`">
                  <strong>{{ item.weekday_label }}</strong>
                  <span>{{ item.label }}</span>
                </li>
              </ul>
              <p v-else-if="!editingAvailability" class="block__empty">No availability noted yet.</p>

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
          </div>

          <!-- Progress -->
          <div v-show="activeTab === 'progress'" class="tab-panel" role="tabpanel">
            <PupilsPupilProgressRoad
              :learner-id="pupil.id"
              :first-name="pupil.first_name"
              :next-focus="pupil.test_journey?.next_focus || null"
              :countdown-label="pupil.test_journey?.countdown_label || null"
            />

            <section v-if="learnerSaysSkills.length" class="block">
              <h2 class="block__title">They say they’ve covered</h2>
              <p class="block__hint">As reported by the pupil — not an assessment.</p>
              <ul class="skill-chips">
                <li v-for="skill in learnerSaysSkills" :key="skill" class="skill-chip">
                  {{ skill }}
                </li>
              </ul>
            </section>

            <section class="block">
              <div class="block__head">
                <h2 class="block__title">Mock tests</h2>
                <NuxtLink :to="`/pupils/${pupil.id}/mocks`" class="ol-link-action">View all</NuxtLink>
              </div>
              <p class="block__hint">Run mocks in lessons and watch fault patterns over time.</p>
              <NuxtLink :to="`/pupils/${pupil.id}/mocks`" class="ol-btn ol-btn--sm">Mock tests</NuxtLink>
            </section>
          </div>

          <!-- Payments -->
          <div
            v-show="activeTab === 'payments'"
            id="money"
            class="tab-panel money"
            role="tabpanel"
          >
            <div class="block__head block__head--flush">
              <h2 class="block__title">Credit &amp; balance</h2>
            </div>

            <div v-if="financeLoading" class="muted">Loading money…</div>
            <p v-else-if="financeError" class="error" role="alert">{{ financeError }}</p>
            <template v-else-if="finance">
              <div class="money-tiles money-tiles--panel">
                <div class="money-tile money-tile--credit">
                  <p class="money-tile__label">Lesson credit</p>
                  <p class="money-tile__value">{{ finance.summary.credit_label }}</p>
                </div>
                <div class="money-tile money-tile--balance" :data-owes="finance.summary.owes_money ? 'yes' : 'no'">
                  <p class="money-tile__label">Balance</p>
                  <p class="money-tile__value">{{ finance.summary.money_status_label }}</p>
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
          </div>
        </div>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { AvailabilityWindow, AvailabilityWrite, Pupil, PupilStatus } from '~/composables/usePupils'
import type { FinancePanel } from '~/composables/useFinance'
import type { Lesson } from '~/composables/useLessons'
import { INTAKE_SKILL_GROUPS } from '~/composables/useIntake'

definePageMeta({ flush: true })

const route = useRoute()
const id = computed(() => Number(route.params.id))

const router = useRouter()

type PupilTab = 'details' | 'lessons' | 'progress' | 'payments'

const PUPIL_TABS: { id: PupilTab; label: string }[] = [
  { id: 'details', label: 'Overview' },
  { id: 'lessons', label: 'Lessons' },
  { id: 'progress', label: 'Progress' },
  { id: 'payments', label: 'Payments' },
]

const TAB_IDS = new Set<string>(PUPIL_TABS.map(t => t.id))

function parseTab(raw: unknown): PupilTab | null {
  const value = Array.isArray(raw) ? raw[0] : raw
  if (typeof value !== 'string') return null
  if (value === 'overview') return 'details'
  if (TAB_IDS.has(value)) return value as PupilTab
  return null
}

const activeTab = computed<PupilTab>(() => {
  const fromQuery = parseTab(route.query.tab)
  if (fromQuery) return fromQuery
  if (route.query.pay === '1') return 'payments'
  return 'details'
})

async function setTab(tab: PupilTab) {
  const query: Record<string, string | string[] | undefined> = { ...route.query }
  if (tab === 'details') delete query.tab
  else query.tab = tab
  if (tab !== 'payments') delete query.pay
  await router.replace({ query })
}


const { getPupil, archivePupil, listAvailability, replaceAvailability, invitePortal, setPupilStatus } = usePupils()
const { listForPupil, durationLabel } = useLessons()
const { fetchPanel, createPackage, recordPayment, voidPayment, voidPackage, poundsInputToPence } = useFinance()
const pupil = ref<Pupil | null>(null)
const lessons = ref<Lesson[]>([])
const availability = ref<AvailabilityWindow[]>([])
const finance = ref<FinancePanel | null>(null)
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
const savingStatus = ref(false)
const statusMenuOpen = ref(false)
const statusMenuRoot = ref<HTMLElement | null>(null)

const statusChoices: { value: PupilStatus; label: string }[] = [
  { value: 'active', label: 'Active' },
  { value: 'waiting', label: 'Waiting list' },
  { value: 'paused', label: 'Paused' },
  { value: 'passed', label: 'Passed' },
  { value: 'inactive', label: 'Inactive' },
]

const currentStatus = computed<PupilStatus>(() => {
  if (!pupil.value) return 'active'
  if (pupil.value.status) return pupil.value.status
  if (pupil.value.archived_at) return 'inactive'
  if (pupil.value.lifecycle === 'waiting') return 'waiting'
  if (pupil.value.lifecycle === 'paused') return 'paused'
  if (pupil.value.lifecycle === 'passed') return 'passed'
  return 'active'
})

function statusLabel(status: PupilStatus): string {
  return statusChoices.find(o => o.value === status)?.label || 'Active'
}

function onStatusPick(status: PupilStatus) {
  statusMenuOpen.value = false
  void onStatusChange(status)
}

function onStatusDocClick(event: MouseEvent) {
  if (!statusMenuOpen.value) return
  if (statusMenuRoot.value && !statusMenuRoot.value.contains(event.target as Node)) {
    statusMenuOpen.value = false
  }
}

function onStatusDocKeydown(event: KeyboardEvent) {
  if (event.key !== 'Escape') return
  statusMenuOpen.value = false
}

async function onStatusChange(next: string) {
  const status = next as PupilStatus
  if (status === currentStatus.value) return
  savingStatus.value = true
  error.value = ''
  try {
    pupil.value = await setPupilStatus(id.value, status)
  } catch (e) {
    error.value = extractApiError(e, 'Could not update status.')
  } finally {
    savingStatus.value = false
  }
}

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

const lessonDashTab = ref<'upcoming' | 'past'>('upcoming')

const upcomingLessons = computed(() =>
  lessons.value
    .filter(l => l.status === 'scheduled' && new Date(l.starts_at).getTime() >= Date.now())
    .slice(0, 8),
)

const pastLessonsDash = computed(() => {
  const now = Date.now()
  return lessons.value
    .filter((l) => {
      if (l.status === 'cancelled') return false
      if (l.status === 'scheduled' && new Date(l.starts_at).getTime() >= now) return false
      return true
    })
    .slice(0, 8)
})

const dashLessons = computed(() =>
  lessonDashTab.value === 'upcoming' ? upcomingLessons.value : pastLessonsDash.value,
)

const upcomingLessonCount = computed(() =>
  lessons.value.filter(l => l.status === 'scheduled' && new Date(l.starts_at).getTime() >= Date.now()).length,
)

const pastLessonCount = computed(() =>
  lessons.value.filter(l => l.status === 'completed' || l.status === 'no_show').length,
)

const recentPayments = computed(() =>
  (finance.value?.history ?? []).filter(h => !h.voided).slice(0, 5),
)

function lessonDateLabel(lesson: Lesson): string {
  const raw = lesson.starts_at_display || lesson.starts_at_local || ''
  const parts = raw.split(',').map(s => s.trim())
  if (parts.length >= 2) return parts[0]
  return raw.split(' ').slice(0, 3).join(' ') || raw
}

function lessonTimeLabel(lesson: Lesson): string {
  const raw = lesson.starts_at_display || lesson.starts_at_local || ''
  const parts = raw.split(',').map(s => s.trim())
  if (parts.length >= 2) return parts.slice(1).join(', ')
  const match = raw.match(/\d{1,2}:\d{2}/)
  return match ? raw.slice(raw.indexOf(match[0])) : durationLabel(lesson.duration_minutes)
}

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

const theoryFacts = computed(() => {
  const theory = pupil.value?.theory
  if (!theory) return [] as Array<{ label: string; value: string }>
  const facts: Array<{ label: string; value: string }> = []
  if (theory.status === 'booked' && theory.test_date_display) {
    facts.push({ label: 'Test date', value: theory.test_date_display })
    if (theory.days_until_test != null && theory.days_until_test >= 0) {
      facts.push({
        label: 'Time left',
        value: theory.days_until_test === 0
          ? 'Today'
          : theory.days_until_test === 1
            ? '1 day'
            : `${theory.days_until_test} days`,
      })
    }
  }
  if (theory.status === 'passed') {
    if (theory.pass_date) {
      facts.push({ label: 'Passed', value: formatDate(theory.pass_date) })
    }
    if (theory.expires_on_display) {
      facts.push({ label: 'Valid until', value: theory.expires_on_display })
    }
    if (theory.days_until_expiry != null && theory.days_until_expiry >= 0) {
      facts.push({
        label: 'Left for practical',
        value: theory.days_until_expiry === 1
          ? '1 day'
          : `${theory.days_until_expiry} days`,
      })
    }
  }
  return facts
})

const activeServiceRates = computed(() =>
  (pupil.value?.service_rates ?? []).filter(r => r.is_active),
)

function emergencyRank(globalIndex: number): string {
  const list = pupil.value?.contacts ?? []
  let n = 0
  for (let i = 0; i <= globalIndex; i++) {
    if (list[i]?.kind === 'emergency') n++
  }
  if (n <= 0) return ''
  if (n === 1) return '1st'
  if (n === 2) return '2nd'
  if (n === 3) return '3rd'
  return `${n}th`
}

function preferredContactLabel(value: string | null | undefined): string {
  return matchPreferred(value)
}

function matchPreferred(value: string | null | undefined): string {
  switch (value) {
    case 'sms': return 'Text'
    case 'whatsapp': return 'WhatsApp'
    case 'email': return 'Email'
    case 'call': return 'Call'
    default: return ''
  }
}

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
  } catch (e) {
    pupil.value = null
    lessons.value = []
    availability.value = []
    finance.value = null
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
  document.addEventListener('click', onStatusDocClick)
  document.addEventListener('keydown', onStatusDocKeydown)
  void load()
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onStatusDocClick)
  document.removeEventListener('keydown', onStatusDocKeydown)
})

watch(id, () => {
  statusMenuOpen.value = false
  void load()
})

function transmissionLabel(value?: string | null) {
  if (!value) return ''
  if (value === 'manual') return 'Manual'
  if (value === 'automatic') return 'Automatic'
  return value
}


function pupilInitials(p: Pupil) {
  const parts = (p.full_name || '').trim().split(/\s+/).filter(Boolean)
  if (!parts.length) return '?'
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

</script>

<style scoped>
.pupil-page {
  width: 100%;
  max-width: none;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0;
  padding: 0 0 28px;
  flex: 1;
  background: transparent;
  border-radius: 0;
}

.pupil-page > .ol-back {
  margin: 12px 16px 0;
  color: var(--color-ownlane-green);
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
}

.pupil-page > .ol-back:hover {
  color: var(--color-jelly-green);
  text-decoration: none;
}

.pupil-page > .ol-muted,
.pupil-page > .ol-error {
  margin: 12px;
}

/* Identity + tabs as one parchment banner */
.hero-band {
  margin: 0;
  padding: 16px 16px 14px;
  background: var(--color-parchment);
  border: none;
  border-bottom: 1px solid var(--color-border);
  display: flex;
  flex-direction: column;
  gap: 0;
}

.hero-band__body {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding-bottom: 16px;
}

.hero-band__identity {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  min-width: 0;
  flex: 1;
}

.hero-band__avatar {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  background: color-mix(in srgb, var(--color-ownlane-green) 14%, var(--color-paper-white));
  color: var(--color-ownlane-green);
  font-family: var(--font-haas-grot-disp);
  font-size: 22px;
  font-weight: 600;
  letter-spacing: -0.02em;
}

.hero-band__copy {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.hero-band__name {
  margin: 0;
  font-family: var(--font-haas-grot-text, inherit);
  font-size: 1.35rem;
  font-weight: 700;
  line-height: 1.2;
  letter-spacing: -0.01em;
  color: var(--color-ink-black);
}

.hero-band__email {
  margin: 0;
  font-size: 13px;
  color: var(--color-muted);
  word-break: break-word;
}

.hero-band__stats {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 6px 0 0;
  font-size: 13px;
  color: var(--color-muted);
}

.hero-band__stats strong {
  color: var(--color-ink-black);
  font-weight: 700;
  font-size: 15px;
}

.hero-band__stats-sep {
  width: 1px;
  height: 22px;
  background: var(--color-border);
}

.hero-band__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.hero-band__cta {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 44px;
  padding: 10px 20px;
  border-radius: var(--radius-buttons, 12px);
  background: var(--color-ownlane-green);
  color: var(--color-on-accent, var(--color-paper-white));
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  white-space: nowrap;
  box-shadow: var(--shadow-button, none);
}

.hero-band__cta:hover { background: var(--color-jelly-green); }

.hero-band__cta--disabled {
  opacity: 0.45;
  pointer-events: none;
}

.tabs--page {
  display: flex;
  gap: 8px;
  margin: 0;
  padding: 4px 0 0;
  border-bottom: none;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}

.tabs--page::-webkit-scrollbar { display: none; }

.tabs--page .tabs__btn {
  flex: 0 0 auto;
  min-height: 36px;
  padding: 8px 16px;
  border: none;
  border-radius: 999px;
  background: transparent;
  color: var(--color-ownlane-green);
  font: inherit;
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
}

.tabs--page .tabs__btn[data-active='yes'] {
  background: var(--color-ownlane-green);
  color: var(--color-on-accent, #fff);
  font-weight: 650;
}

.tabs--page .tabs__btn:hover:not([data-active='yes']) {
  background: color-mix(in srgb, var(--color-ownlane-green) 10%, transparent);
  color: var(--color-ownlane-green);
}

.profile-body {
  display: flex;
  flex-direction: column;
  gap: 20px;
  width: 100%;
  max-width: none;
  padding: 16px 12px 24px;
}

/* Floating white cards on the page surface */
.dash {
  display: grid;
  grid-template-columns:
    minmax(420px, 2.35fr)
    minmax(210px, 0.85fr)
    minmax(250px, 1.15fr);
  gap: 20px;
  align-items: stretch;
  margin: 0;
  padding: 0;
  background: transparent;
  border-radius: 0;
}

.dash-card {
  background: var(--color-paper-white);
  border: none;
  border-radius: 14px;
  padding: 24px;
  min-width: 0;
  box-shadow: 0 1px 3px color-mix(in srgb, var(--color-ink-black) 6%, transparent);
}

.dash-card--details { grid-column: 1; grid-row: 1; }
.dash-card--notes {
  grid-column: 2;
  grid-row: 1;
  display: flex;
  flex-direction: column;
  padding: 20px;
}
.dash-card--places { grid-column: 3; grid-row: 1; }
.dash-card--lessons {
  grid-column: 1 / 3;
  grid-row: 2;
  min-height: 300px;
}
.dash-card--pay { grid-column: 3; grid-row: 2; }

.dash-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.dash-card__head--lessons { margin-bottom: 18px; }

.dash-card__title {
  margin: 0;
  font-size: 17px;
  font-weight: 650;
  color: var(--color-ink-black);
}

.dash-card__action {
  border: none;
  background: transparent;
  color: var(--color-ownlane-green);
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  padding: 0;
}

.dash-card__cta {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 38px;
  padding: 8px 16px;
  border-radius: 10px;
  background: var(--color-ownlane-green);
  color: var(--color-on-accent, #fff);
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
}

.dash-card__cta:hover { background: var(--color-jelly-green); }

.dash-empty {
  margin: 40px 0;
  font-size: 14px;
  color: var(--color-muted);
  text-align: center;
}

.dash-empty--left {
  margin: 8px 0;
  text-align: left;
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px 28px;
  margin: 0;
}

.fact-cell { min-width: 0; }
.fact-cell--wide { grid-column: 1 / -1; }
.fact-cell__nowrap { white-space: nowrap; }

.fact-cell dt {
  margin: 0 0 5px;
  font-size: 12px;
  font-weight: 500;
  color: var(--color-muted);
}

.fact-cell dd {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink-black);
  line-height: 1.4;
  overflow-wrap: break-word;
  word-break: normal;
}

.fact-cell dd a {
  color: var(--color-ink-black);
  text-decoration: none;
}

.fact-cell dd a:hover { color: var(--color-ownlane-green); }

.status-field__wrap--inline {
  position: relative;
  display: inline-flex;
}

.status-field__trigger--plain {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  width: auto;
  min-height: 0;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--color-ink-black);
  font: inherit;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

/* Notes — soft grey inset like reference */
.notes-box {
  flex: 1;
  background: #f4f5f7;
  border-radius: 12px;
  padding: 16px;
  min-height: 150px;
}

.dash-card--notes .notes-box {
  background: #f4f5f7;
}

.notes-box__text {
  margin: 0;
  font-size: 14px;
  line-height: 1.55;
  color: var(--color-ink-black);
  white-space: pre-wrap;
}

.notes-box__meta {
  margin: 12px 0 0;
  font-size: 12px;
  color: var(--color-muted);
}

/* Places as soft file rows */
.file-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.file-list--contacts {
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid var(--color-border);
}

.file-row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px;
  border-radius: 10px;
  background: #f4f5f7;
  min-width: 0;
}

.file-row__icon {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  background: var(--color-paper-white);
  color: var(--color-bark);
}

.file-row__body {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.file-row__name {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink-black);
}

.file-row__meta {
  font-size: 12px;
  color: var(--color-muted);
  line-height: 1.35;
  word-break: break-word;
}

/* Lessons */
.lesson-tabs {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.lesson-tabs__btn {
  border: none;
  background: transparent;
  padding: 8px 0 10px;
  font: inherit;
  font-size: 14px;
  font-weight: 500;
  color: var(--color-muted);
  cursor: pointer;
  border-bottom: 2px solid transparent;
}

.lesson-tabs__btn[data-active='yes'] {
  color: var(--color-ownlane-green);
  font-weight: 650;
  border-bottom-color: var(--color-ownlane-green);
}

.appt-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.appt-row {
  display: grid;
  grid-template-columns: 12px minmax(0, 1fr);
  gap: 12px;
  align-items: stretch;
}

.appt-row__rail {
  width: 8px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--color-ownlane-green) 40%, #e8eaed);
  position: relative;
}

.appt-row__rail::before {
  content: '';
  position: absolute;
  left: 50%;
  top: 20px;
  width: 8px;
  height: 8px;
  margin-left: -4px;
  border-radius: 50%;
  background: var(--color-ownlane-green);
}

.appt-row__body {
  display: grid;
  grid-template-columns: minmax(110px, 1.1fr) minmax(70px, 0.7fr) minmax(0, 1.4fr) auto;
  gap: 10px 16px;
  align-items: center;
  padding: 14px 16px;
  border: none;
  border-radius: 12px;
  text-decoration: none;
  color: inherit;
  background: #f4f5f7;
}

.appt-row__body:hover {
  background: color-mix(in srgb, var(--color-ownlane-green) 6%, #f4f5f7);
}

.appt-row__when {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.appt-row__date {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-ink-black);
}

.appt-row__time {
  font-size: 12px;
  color: var(--color-muted);
}

.appt-row__type,
.appt-row__place,
.appt-row__status {
  font-size: 13px;
  color: var(--color-bark);
}

.appt-row__place {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.appt-row__status {
  font-weight: 600;
  color: var(--color-muted);
  text-align: right;
}

/* Payments */
.pay-head {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 12px;
  margin-bottom: 10px;
  font-size: 12px;
  color: var(--color-muted);
}

.pay-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.pay-list__item {
  display: grid;
  grid-template-columns: 10px minmax(0, 1fr) 16px auto;
  gap: 8px;
  align-items: center;
}

.pay-list__dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--color-ownlane-green);
}

.pay-list__dot[data-kind='lesson_charge'],
.pay-list__dot[data-kind='credit_consumed'] {
  background: var(--color-driftwood);
}

.pay-list__title {
  font-size: 14px;
  color: var(--color-ink-black);
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pay-list__chev {
  color: var(--color-ownlane-green);
  opacity: 0.8;
}

.pay-list__amount {
  font-size: 14px;
  font-weight: 650;
  color: var(--color-ink-black);
}

.pay-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-top: 18px;
  padding-top: 14px;
  border-top: 1px solid var(--color-border);
  font-size: 14px;
  color: var(--color-bark);
}

.pay-total strong {
  color: var(--color-ink-black);
  font-weight: 700;
}

.pay-see-all {
  display: inline-block;
  margin-top: 12px;
}

@media (max-width: 1200px) {
  .dash {
    grid-template-columns:
      minmax(0, 2fr)
      minmax(190px, 0.85fr)
      minmax(0, 1.1fr);
  }

}

@media (max-width: 1100px) {
  .dash {
    grid-template-columns: 1fr 1fr;
    gap: 16px;
  }

  .dash-card--details { grid-column: 1 / -1; grid-row: auto; }
  .dash-card--notes { grid-column: 1; grid-row: auto; }
  .dash-card--places { grid-column: 2; grid-row: auto; }
  .dash-card--lessons,
  .dash-card--pay { grid-column: 1 / -1; grid-row: auto; }
}

@media (max-width: 720px) {
  .pupil-page {
    padding-bottom: 16px;
  }

  .hero-band {
    padding: 12px 12px 0;
  }

  .hero-band__body {
    flex-direction: column;
  }

  .hero-band__actions {
    width: 100%;
  }

  .hero-band__cta {
    width: 100%;
  }

  .profile-body {
    padding: 12px 8px 20px;
  }

  .dash {
    grid-template-columns: 1fr;
    margin: 0;
    padding: 0;
  }

  .dash-card--notes,
  .dash-card--places,
  .dash-card--details,
  .dash-card--lessons,
  .dash-card--pay { grid-column: 1; }

  .appt-row__body { grid-template-columns: 1fr 1fr; }
  .appt-row__type,
  .appt-row__place,
  .appt-row__status { border-left: none; border-top: 1px solid #e6e8ec; }
  .appt-row__place,
  .appt-row__status { grid-column: 1 / -1; }
  .appt-row__status { text-align: left; }
}
</style>
