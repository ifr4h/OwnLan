<template>
  <section class="settings ol-page">
    <header class="ol-page-header">
      <p class="ol-eyebrow">Your business</p>
      <h1 class="ol-page-title">Settings</h1>
      <p class="ol-meta settings-lede">
        Only what OwnLane uses when you book, price, and share with pupils.
      </p>
    </header>

    <nav v-if="!loading && !loadError" class="settings-nav ol-panel">
      <NuxtLink to="/settings/profile" class="settings-nav__link">
        Your OwnLane page
        <span aria-hidden="true">→</span>
      </NuxtLink>
      <NuxtLink to="/settings/data" class="settings-nav__link">
        Data &amp; exports
        <span aria-hidden="true">→</span>
      </NuxtLink>
    </nav>

    <section class="ol-panel ol-stack">
      <h2 class="ol-section-title">Appearance</h2>
      <p class="ol-meta">Choose how OwnLane looks on this device. Applies straight away.</p>
      <div class="ol-seg" role="radiogroup" aria-label="Theme">
        <button
          v-for="option in themeOptions"
          :key="option.value"
          class="ol-chip"
          type="button"
          :class="{ 'ol-chip--on': themePreference === option.value }"
          :aria-checked="themePreference === option.value"
          role="radio"
          @click="setThemePreference(option.value)"
        >
          {{ option.label }}
        </button>
      </div>
      <p class="ol-field__hint">{{ themeHint }}</p>
    </section>

    <p v-if="loading" class="ol-muted">Loading…</p>
    <p v-else-if="loadError" class="ol-error" role="alert">{{ loadError }}</p>

    <form v-else class="form ol-stack" @submit.prevent="onSave">
      <section class="ol-panel ol-stack">
        <h2 class="ol-section-title">You &amp; your school</h2>
        <label class="ol-field">
          <span class="ol-field__label">Your display name</span>
          <input v-model="displayName" class="ol-input" type="text" required maxlength="255">
          <span class="ol-field__hint">Shown to pupils on the learner portal.</span>
        </label>
        <label class="ol-field">
          <span class="ol-field__label">Driving school / business name</span>
          <input v-model="businessName" class="ol-input" type="text" required maxlength="255">
        </label>
        <label class="ol-field">
          <span class="ol-field__label">Contact phone</span>
          <input v-model="contactPhone" class="ol-input" type="tel" autocomplete="tel">
        </label>
        <label class="ol-field">
          <span class="ol-field__label">Contact email</span>
          <input v-model="contactEmail" class="ol-input" type="email" autocomplete="email">
        </label>
      </section>

      <section class="ol-panel ol-stack">
        <h2 class="ol-section-title">Lessons &amp; prices</h2>
        <label class="ol-field">
          <span class="ol-field__label">Timezone</span>
          <select v-model="timezone" class="ol-select">
            <option v-for="tz in timezoneOptions" :key="tz" :value="tz">{{ tz }}</option>
          </select>
          <span class="ol-field__hint">All diary times use this timezone.</span>
        </label>
        <label class="ol-field">
          <span class="ol-field__label">Normal lesson length</span>
          <select v-model.number="durationMinutes" class="ol-select">
            <option v-for="mins in durationOptions" :key="mins" :value="mins">
              {{ durationLabel(mins) }}
            </option>
          </select>
          <span class="ol-field__hint">Used when booking a pupil without a clear history.</span>
        </label>
        <label class="ol-field">
          <span class="ol-field__label">Standard hourly rate (£)</span>
          <input
            v-model="hourlyRate"
            class="ol-input"
            inputmode="decimal"
            placeholder="35"
            required
          >
          <span class="ol-field__hint">
            Fallback when a lesson has no matching service price.
            Prefer setting prices in
            <NuxtLink to="/services">Services</NuxtLink>.
          </span>
        </label>
      </section>

      <section class="ol-panel ol-stack">
        <h2 class="ol-section-title">Working hours</h2>
        <p class="ol-meta">We’ll gently warn if you book outside these hours — never block you.</p>
        <div class="ol-seg" role="group" aria-label="Working days">
          <button
            v-for="day in weekdayOptions"
            :key="day.value"
            class="ol-chip"
            type="button"
            :class="{ 'ol-chip--on': workDays.includes(day.value) }"
            :aria-pressed="workDays.includes(day.value)"
            @click="toggleWorkDay(day.value)"
          >
            {{ day.short }}
          </button>
        </div>
        <div class="hours">
          <label class="ol-field">
            <span class="ol-field__label">Start</span>
            <input v-model="workStart" class="ol-input ol-input--time" type="time" required>
          </label>
          <label class="ol-field">
            <span class="ol-field__label">End</span>
            <input v-model="workEnd" class="ol-input ol-input--time" type="time" required>
          </label>
        </div>
        <label class="ol-field">
          <span class="ol-field__label">Diary week starts on</span>
          <select v-model.number="weekStartsOn" class="ol-select">
            <option v-for="day in weekdayOptions" :key="day.value" :value="day.value">
              {{ day.label }}
            </option>
          </select>
          <span class="ol-field__hint">Changes the week and month layout in your diary.</span>
        </label>
      </section>

      <section class="ol-panel ol-stack">
        <h2 class="ol-section-title">Area &amp; cancellations</h2>
        <label class="ol-field">
          <span class="ol-field__label">Service / postcode area</span>
          <textarea
            v-model="serviceArea"
            class="ol-textarea"
            rows="2"
            placeholder="e.g. SW9, Brixton, Peckham"
          />
          <span class="ol-field__hint">Shown to pupils so they know where you cover.</span>
        </label>
        <label class="ol-field">
          <span class="ol-field__label">Cancellation policy</span>
          <textarea
            v-model="cancellationPolicy"
            class="ol-textarea"
            rows="3"
            placeholder="e.g. Please give 48 hours’ notice or the lesson may still be charged."
          />
          <span class="ol-field__hint">Shown on the learner portal.</span>
        </label>
        <label class="ol-check">
          <input v-model="learnerCanCancel" type="checkbox">
          Pupils can cancel lessons from the portal
        </label>
        <template v-if="learnerCanCancel">
          <label class="ol-field">
            <span class="ol-field__label">Notice for a free cancel (hours)</span>
            <input v-model.number="cancellationNoticeHours" class="ol-input" type="number" min="0" max="168">
            <span class="ol-field__hint">If they cancel with less notice, your late-cancel rule applies.</span>
          </label>
          <fieldset class="ol-stack">
            <legend class="ol-field__label">If they cancel with short notice</legend>
            <label class="ol-radio">
              <input v-model="cancellationLatePolicy" type="radio" value="decide">
              I’ll decide whether to charge
            </label>
            <label class="ol-radio">
              <input v-model="cancellationLatePolicy" type="radio" value="charge">
              Charge the lesson automatically
            </label>
          </fieldset>
        </template>
      </section>

      <section class="ol-panel ol-stack">
        <h2 class="ol-section-title">Pupil booking</h2>
        <p class="ol-meta">Control whether pupils can find times in the learner portal.</p>
        <fieldset class="ol-stack">
          <legend class="ol-field__label">How should pupils book?</legend>
          <label class="ol-radio">
            <input v-model="bookingMode" type="radio" value="manual">
            I arrange lessons myself
          </label>
          <label class="ol-radio">
            <input v-model="bookingMode" type="radio" value="request">
            Pupils can request a time
          </label>
          <label class="ol-radio">
            <input v-model="bookingMode" type="radio" value="instant">
            Pupils can book available times
          </label>
        </fieldset>
        <label class="ol-field">
          <span class="ol-field__label">Minimum notice (hours)</span>
          <input v-model.number="bookingNoticeHours" class="ol-input" type="number" min="0" max="168">
        </label>
        <label class="ol-field">
          <span class="ol-field__label">Pupils can book up to (weeks ahead)</span>
          <input v-model.number="bookingAdvanceWeeks" class="ol-input" type="number" min="1" max="52">
        </label>
        <label class="ol-field">
          <span class="ol-field__label">Rescheduling</span>
          <select v-model="rescheduleMode" class="ol-select">
            <option value="manual">I handle changes</option>
            <option value="request">Pupils can request a new time</option>
            <option value="instant">Pupils can move to another available time</option>
          </select>
        </label>
      </section>

      <p v-if="saveError" class="ol-error" role="alert">{{ saveError }}</p>
      <p v-if="saved" class="ok" role="status">Saved.</p>

      <div class="save-bar">
        <button class="ol-btn" type="submit" :disabled="saving">
          {{ saving ? 'Saving…' : 'Save settings' }}
          <span aria-hidden="true">→</span>
        </button>
      </div>
    </form>

    <section v-if="!loading && !loadError" class="ol-panel ol-stack calendar-panel">
      <h2 class="ol-section-title">Calendar</h2>
      <p class="ol-meta">See your OwnLane lessons in your usual calendar app.</p>

      <template v-if="!calendar.connected">
        <p class="ol-meta">Works with Apple Calendar, Google Calendar, Outlook and other apps that support subscriptions.</p>
        <button class="ol-btn" type="button" :disabled="calendarBusy" @click="onConnectCalendar">
          {{ calendarBusy ? 'Connecting…' : 'Connect calendar' }}
        </button>
      </template>

      <template v-else>
        <label class="ol-field">
          <span class="ol-field__label">Calendar link</span>
          <div class="calendar-link-row">
            <input
              :value="calendar.subscription_url ?? ''"
              class="ol-input calendar-link-row__input"
              type="text"
              readonly
            >
            <button class="ol-btn ol-btn--ghost ol-btn--sm" type="button" @click="copyCalendarLink">
              Copy link
            </button>
          </div>
          <span class="ol-field__hint">{{ calendar.refresh_note }}</span>
        </label>

        <fieldset class="ol-stack">
          <legend class="ol-field__label">What appears in your calendar</legend>
          <label
            v-for="option in calendar.privacy_options"
            :key="option.value"
            class="ol-radio"
          >
            <input
              v-model="calendarPrivacy"
              type="radio"
              :value="option.value"
              @change="onPrivacyChange"
            >
            <span>{{ option.label }}</span>
            <span class="ol-field__hint">{{ option.description }}</span>
          </label>
        </fieldset>

        <details class="calendar-instructions">
          <summary>How to add this calendar</summary>
          <div v-for="platform in calendarPlatforms" :key="platform.id" class="calendar-instructions__platform">
            <p class="calendar-instructions__name">{{ platform.name }}</p>
            <ol>
              <li v-for="(step, i) in platform.steps" :key="i">{{ step }}</li>
            </ol>
          </div>
        </details>

        <p v-if="calendarError" class="ol-error" role="alert">{{ calendarError }}</p>
        <p v-if="calendarMessage" class="ok" role="status">{{ calendarMessage }}</p>

        <button class="ol-btn ol-btn--ghost" type="button" :disabled="calendarBusy" @click="onResetCalendarLink">
          Reset calendar link
        </button>
        <p class="ol-field__hint">Your old calendar link will stop working. Use this if the link was shared by mistake.</p>
      </template>
    </section>

    <section v-if="!loading && !loadError" class="ol-panel ol-stack feedback-panel">
      <h2 class="ol-section-title">Beta feedback</h2>
      <p class="ol-meta">
        Something confusing, broken, or missing? Tell us what you were trying to do.
      </p>
      <a class="ol-btn ol-btn--ghost" :href="feedbackHref">Send feedback</a>
      <p class="ol-meta feedback-panel__meta">
        Build {{ appVersion }}
        <span v-if="feedbackRoute"> · {{ feedbackRoute }}</span>
      </p>
    </section>
  </section>
</template>

<script setup lang="ts">
import { calendarPlatforms } from '~/constants/calendarInstructions'
import type { CalendarSettings } from '~/composables/useSettings'

useHead({ title: 'Settings · OwnLane' })

const {
  fetchSettings,
  updateSettings,
  connectCalendar,
  regenerateCalendar,
  updateCalendarPrivacy,
} = useSettings()
const { fetchMe } = useAuth()
const { durationLabel } = useLessons()
const {
  preference: themePreference,
  options: themeOptions,
  setPreference: setThemePreference,
} = useTheme()

const themeHint = computed(() => {
  const match = themeOptions.find(option => option.value === themePreference.value)
  return match?.hint ?? ''
})

const loading = ref(true)
const loadError = ref('')
const saving = ref(false)
const saveError = ref('')
const saved = ref(false)

const displayName = ref('')
const businessName = ref('')
const contactPhone = ref('')
const contactEmail = ref('')
const timezone = ref('Europe/London')
const durationMinutes = ref(60)
const hourlyRate = ref('35')
const workDays = ref<number[]>([1, 2, 3, 4, 5, 6])
const workStart = ref('09:00')
const workEnd = ref('18:00')
const weekStartsOn = ref(1)
const serviceArea = ref('')
const cancellationPolicy = ref('')
const bookingMode = ref('manual')
const rescheduleMode = ref('manual')
const learnerCanCancel = ref(true)
const cancellationNoticeHours = ref(48)
const cancellationLatePolicy = ref<'charge' | 'decide'>('decide')
const bookingNoticeHours = ref(12)
const bookingAdvanceWeeks = ref(4)
const timezoneOptions = ref(['Europe/London', 'Europe/Dublin'])
const durationOptions = ref([30, 45, 60, 90, 120])

const calendar = ref<CalendarSettings>({
  connected: false,
  subscription_url: null,
  privacy_mode: 'private',
  privacy_options: [],
  feed_created_at: null,
  refresh_note: 'Changes appear when your calendar app refreshes the subscription.',
})
const calendarPrivacy = ref<'full' | 'private'>('private')
const calendarBusy = ref(false)
const calendarError = ref('')
const calendarMessage = ref('')

const config = useRuntimeConfig()
const route = useRoute()
const appVersion = computed(() => String(config.public.appVersion || 'beta'))
const feedbackRoute = computed(() => route.path)
const feedbackHref = computed(() => {
  const email = String(config.public.feedbackEmail || 'feedback@ownlane.co.uk')
  const subject = encodeURIComponent('OwnLane beta feedback')
  const body = encodeURIComponent(
    `What I was trying to do:\n\n\nWhat happened:\n\n\n---\nBuild: ${appVersion.value}\nPage: ${feedbackRoute.value}\n`,
  )
  return `mailto:${email}?subject=${subject}&body=${body}`
})

const weekdayOptions = [
  { value: 1, short: 'Mon', label: 'Monday' },
  { value: 2, short: 'Tue', label: 'Tuesday' },
  { value: 3, short: 'Wed', label: 'Wednesday' },
  { value: 4, short: 'Thu', label: 'Thursday' },
  { value: 5, short: 'Fri', label: 'Friday' },
  { value: 6, short: 'Sat', label: 'Saturday' },
  { value: 7, short: 'Sun', label: 'Sunday' },
]

function toggleWorkDay(day: number) {
  const set = new Set(workDays.value)
  if (set.has(day)) set.delete(day)
  else set.add(day)
  workDays.value = [...set].sort((a, b) => a - b)
}

function penceToPoundsInput(pence: number | null): string {
  if (pence === null) return '35'
  const pounds = Math.floor(pence / 100)
  const rem = pence % 100
  return rem === 0 ? String(pounds) : `${pounds}.${String(rem).padStart(2, '0')}`
}

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const data = await fetchSettings()
    displayName.value = data.display_name
    businessName.value = data.business_name
    contactPhone.value = data.contact_phone || ''
    contactEmail.value = data.contact_email || ''
    timezone.value = data.timezone
    durationMinutes.value = data.default_lesson_duration_minutes
    hourlyRate.value = penceToPoundsInput(data.default_hourly_rate_pence)
    workDays.value = [...data.work_days]
    workStart.value = data.work_start_time
    workEnd.value = data.work_end_time
    weekStartsOn.value = data.week_starts_on || 1
    serviceArea.value = data.service_area || ''
    cancellationPolicy.value = data.cancellation_policy || ''
    bookingMode.value = data.booking_mode || 'manual'
    rescheduleMode.value = data.learner_reschedule_mode || 'manual'
    learnerCanCancel.value = data.learner_can_cancel ?? true
    cancellationNoticeHours.value = data.cancellation_notice_hours ?? 48
    cancellationLatePolicy.value = data.cancellation_late_policy === 'charge' ? 'charge' : 'decide'
    bookingNoticeHours.value = data.booking_minimum_notice_hours ?? 12
    bookingAdvanceWeeks.value = data.booking_advance_weeks ?? 4
    timezoneOptions.value = data.timezone_options
    durationOptions.value = data.duration_options.includes(data.default_lesson_duration_minutes)
      ? data.duration_options
      : [...data.duration_options, data.default_lesson_duration_minutes].sort((a, b) => a - b)
    calendar.value = data.calendar
    calendarPrivacy.value = data.calendar.privacy_mode
  } catch (e) {
    loadError.value = extractApiError(e, 'Could not load settings.')
  } finally {
    loading.value = false
  }
}

async function onSave() {
  saveError.value = ''
  saved.value = false
  if (workDays.value.length === 0) {
    saveError.value = 'Choose at least one working day.'
    return
  }
  saving.value = true
  try {
    const data = await updateSettings({
      display_name: displayName.value.trim(),
      business_name: businessName.value.trim(),
      contact_phone: contactPhone.value.trim() || null,
      contact_email: contactEmail.value.trim() || null,
      timezone: timezone.value,
      default_lesson_duration_minutes: Number(durationMinutes.value),
      default_hourly_rate: hourlyRate.value.trim(),
      work_days: [...workDays.value].map(Number).sort((a, b) => a - b),
      work_start_time: workStart.value,
      work_end_time: workEnd.value,
      week_starts_on: Number(weekStartsOn.value),
      service_area: serviceArea.value.trim() || null,
      cancellation_policy: cancellationPolicy.value.trim() || null,
      booking_mode: bookingMode.value,
      learner_reschedule_mode: rescheduleMode.value,
      learner_can_cancel: learnerCanCancel.value,
      cancellation_notice_hours: Number(cancellationNoticeHours.value),
      cancellation_late_policy: cancellationLatePolicy.value,
      booking_minimum_notice_hours: Number(bookingNoticeHours.value),
      booking_advance_weeks: Number(bookingAdvanceWeeks.value),
    })
    saved.value = true
    await fetchMe()
    hourlyRate.value = penceToPoundsInput(data.default_hourly_rate_pence)
  } catch (e) {
    saveError.value = extractApiError(e, 'Could not save settings.')
  } finally {
    saving.value = false
  }
}

async function onConnectCalendar() {
  calendarBusy.value = true
  calendarError.value = ''
  calendarMessage.value = ''
  try {
    calendar.value = await connectCalendar()
    calendarPrivacy.value = calendar.value.privacy_mode
  } catch (e) {
    calendarError.value = extractApiError(e, 'Could not connect calendar.')
  } finally {
    calendarBusy.value = false
  }
}

async function onResetCalendarLink() {
  if (!confirm('Reset your calendar link? Your old link will stop working.')) return
  calendarBusy.value = true
  calendarError.value = ''
  calendarMessage.value = ''
  try {
    calendar.value = await regenerateCalendar()
    calendarPrivacy.value = calendar.value.privacy_mode
    calendarMessage.value = 'Calendar link reset. Update the subscription in your calendar app.'
  } catch (e) {
    calendarError.value = extractApiError(e, 'Could not reset calendar link.')
  } finally {
    calendarBusy.value = false
  }
}

async function onPrivacyChange() {
  calendarError.value = ''
  calendarMessage.value = ''
  try {
    calendar.value = await updateCalendarPrivacy(calendarPrivacy.value)
  } catch (e) {
    calendarError.value = extractApiError(e, 'Could not update calendar privacy.')
    calendarPrivacy.value = calendar.value.privacy_mode
  }
}

async function copyCalendarLink() {
  const url = calendar.value.subscription_url
  if (!url) return
  try {
    await navigator.clipboard.writeText(url)
    calendarMessage.value = 'Link copied.'
  } catch {
    calendarError.value = 'Could not copy link. Select and copy it manually.'
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.settings-lede {
  max-width: 42ch;
}

.settings-nav {
  margin-bottom: var(--spacing-16);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.settings-nav__link {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-weight: 600;
  color: inherit;
  text-decoration: none;
}

.calendar-panel {
  margin-top: var(--spacing-16);
}

.calendar-link-row {
  display: flex;
  gap: var(--spacing-8);
  align-items: stretch;
}

.calendar-link-row__input {
  flex: 1;
  min-width: 0;
}

.calendar-instructions {
  font-size: var(--text-body-sm);
}

.calendar-instructions__platform {
  margin-top: var(--spacing-12);
}

.calendar-instructions__name {
  font-weight: 600;
  margin: 0 0 4px;
}

.form {
  gap: var(--spacing-12);
  padding-bottom: calc(88px + env(safe-area-inset-bottom));
}

.hours {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--spacing-12);
}

.ok {
  color: var(--color-success);
  font-size: var(--text-body-sm);
  margin: 0;
}

.save-bar {
  position: sticky;
  bottom: calc(72px + env(safe-area-inset-bottom));
  z-index: 5;
  display: flex;
  padding: var(--spacing-12) 0;
  background: linear-gradient(
    to top,
    var(--color-chalk-green) 55%,
    transparent
  );
}

@media (min-width: 900px) {
  .save-bar {
    bottom: 0;
  }
}

@media (max-width: 480px) {
  .hours {
    grid-template-columns: 1fr;
  }
}
</style>
