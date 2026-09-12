<template>
  <section class="today ol-page">
    <header class="today__header">
      <p class="ol-eyebrow">{{ greeting }}</p>
      <h1 class="ol-page-title">{{ data?.date_display || 'Today' }}</h1>
      <p v-if="data?.summary" class="today__summary ol-meta">
        <template v-if="data.summary.lesson_count > 0">
          <span class="ol-time">{{ data.summary.lesson_count }}</span>
          {{ data.summary.lesson_count === 1 ? 'lesson' : 'lessons' }}
          <template v-if="teachingHoursLabel"> · {{ teachingHoursLabel }}</template>
          <template v-if="data.summary.window_label"> · {{ data.summary.window_label }}</template>
        </template>
        <template v-else>{{ data.summary.line }}</template>
      </p>
    </header>

    <div v-if="error" class="ol-card ol-card--flat" style="background: var(--color-danger-wash)">
      <p class="ol-error" role="alert">{{ error }}</p>
    </div>

    <div v-else-if="loading" class="today__skel" aria-hidden="true">
      <div class="ol-skeleton" style="height: 22px; width: 40%" />
      <div class="ol-skeleton" style="height: 140px; width: 100%; margin-top: 16px" />
      <div class="ol-skeleton" style="height: 56px; width: 100%; margin-top: 12px" />
      <div class="ol-skeleton" style="height: 56px; width: 100%; margin-top: 8px" />
    </div>

    <template v-else-if="data">
      <aside v-if="justCompleted" class="ol-card aftermath" aria-label="Lesson complete">
        <p class="ol-badge ol-badge--success">Lesson complete</p>
        <h2 class="aftermath__name">{{ justCompleted.learner_first_name }}</h2>
        <p class="ol-meta">{{ justCompleted.credit_line }}</p>
        <div class="ol-actions">
          <NuxtLink :to="justCompleted.primary_cta_path" class="ol-btn ol-btn--sm">
            {{ justCompleted.primary_cta_label }}
            <span aria-hidden="true">→</span>
          </NuxtLink>
          <NuxtLink
            v-if="justCompleted.secondary_cta_path && justCompleted.secondary_cta_label"
            :to="justCompleted.secondary_cta_path"
            class="ol-btn ol-btn--ghost ol-btn--sm"
          >
            {{ justCompleted.secondary_cta_label }}
          </NuxtLink>
        </div>
      </aside>

      <OnboardingGuide
        v-if="showChecklist && onboarding"
        eyebrow="Getting started"
        :title="checklistTitle"
        :copy="checklistCopy"
        dismiss-label="Hide for now"
        @dismiss="dismiss('checklist')"
      >
        <ul class="checklist">
          <li
            v-for="item in onboarding.checklist"
            :key="item.id"
            class="checklist__item"
            :data-done="item.done ? 'yes' : 'no'"
          >
            <span class="checklist__mark" aria-hidden="true">{{ item.done ? '✓' : '○' }}</span>
            {{ item.label }}
          </li>
        </ul>
      </OnboardingGuide>

      <aside v-if="showBusinessConfirm" class="ol-card biz" aria-label="Confirm your school name">
        <p class="ol-eyebrow">Optional</p>
        <h2 class="ol-section-title">Does this look right?</h2>
        <p class="ol-meta">Sensible defaults — change anytime in Settings.</p>
        <form class="ol-stack" style="margin-top: 12px" @submit.prevent="onSaveBusiness">
          <label class="ol-field">
            <span class="ol-field__label">Your name with pupils</span>
            <input v-model="bizDisplayName" class="ol-input" type="text" required maxlength="255">
          </label>
          <label class="ol-field">
            <span class="ol-field__label">School / business name</span>
            <input v-model="bizBusinessName" class="ol-input" type="text" required maxlength="255">
          </label>
          <p v-if="bizError" class="ol-error" role="alert">{{ bizError }}</p>
          <div class="ol-actions">
            <button class="ol-btn ol-btn--sm" type="submit" :disabled="bizSaving">
              {{ bizSaving ? 'Saving…' : 'Looks good' }}
            </button>
            <button class="ol-btn ol-btn--ghost ol-btn--sm" type="button" :disabled="bizSaving" @click="dismiss('business_confirm')">
              Skip for now
            </button>
          </div>
        </form>
      </aside>

      <div v-if="stage === 'add_pupils'" class="ol-empty">
        <h2 class="ol-empty__title">Add your pupils</h2>
        <p class="ol-empty__copy">
          OwnLane gets useful once your pupils are here. Add one, or import a CSV.
        </p>
        <div class="ol-empty__actions">
          <NuxtLink to="/pupils/new" class="ol-btn">
            Add pupil
            <span aria-hidden="true">→</span>
          </NuxtLink>
          <NuxtLink to="/pupils/import" class="ol-btn ol-btn--ghost">Import CSV</NuxtLink>
        </div>
      </div>

      <div v-else-if="stage === 'book_lesson'" class="ol-empty">
        <h2 class="ol-empty__title">Book your first lesson</h2>
        <p class="ol-empty__copy">
          You’ve got pupils — put one on the diary so Today can brief your day.
        </p>
        <div class="ol-empty__actions">
          <NuxtLink to="/lessons/new" class="ol-btn">
            Book lesson
            <span aria-hidden="true">→</span>
          </NuxtLink>
        </div>
      </div>

      <div v-else-if="data.lesson_count === 0 && !data.focus" class="ol-empty">
        <h2 class="ol-empty__title">No lessons today</h2>
        <p class="ol-empty__copy">
          Your teaching day is clear.
          <template v-if="(data.needs_you?.length ?? 0) > 0">
            Check For you today below if anything needs a decision.
          </template>
        </p>
        <div class="ol-empty__actions">
          <NuxtLink to="/lessons/new" class="ol-btn">Book lesson →</NuxtLink>
          <NuxtLink to="/lessons" class="ol-btn ol-btn--ghost">Open diary</NuxtLink>
        </div>
      </div>

      <template v-else>
        <!-- NEXT / NOW -->
        <article
          v-if="data.focus"
          class="focus ol-card"
          :data-phase="data.focus.is_current ? 'now' : 'next'"
        >
          <div class="focus__top">
            <p class="ol-badge" :class="data.focus.is_current ? 'ol-badge--solid' : 'ol-badge--neutral'">
              {{ data.focus.is_current ? 'Now' : 'Next lesson' }}
            </p>
            <div class="focus__clock-wrap">
              <LessonWeatherChip
                v-if="data.focus && weatherFor(data.focus)"
                :weather="weatherFor(data.focus)!"
              />
              <p class="focus__clock ol-time">
                {{ data.focus.starts_at_time }}
                <span class="focus__clock-end">–{{ data.focus.ends_at_time }}</span>
              </p>
            </div>
          </div>

          <h2 class="focus__name">{{ data.focus.learner_name }}</h2>
          <p class="focus__meta ol-meta">
            {{ durationLabel(data.focus.duration_minutes) }}
            <template v-if="data.focus.pickup_address"> · {{ shortAddress(data.focus.pickup_address) }}</template>
          </p>

          <div
            v-if="data.focus.learner_next_focus"
            class="focus__next"
          >
            <p class="ol-field__label">Next focus</p>
            <p>{{ data.focus.learner_next_focus }}</p>
          </div>

          <p
            v-else-if="data.focus.learner_last_lesson_summary"
            class="focus__last ol-meta"
          >
            Last: {{ data.focus.learner_last_lesson_summary }}
          </p>

          <p
            v-if="data.focus.travel_to_next?.is_warning"
            class="focus__warn"
            :data-severe="data.focus.travel_to_next.severity === 'impossible' ? 'yes' : 'no'"
          >
            <OlIcon name="warning" :size="16" />
            {{ data.focus.travel_to_next.message }}
          </p>

          <p v-if="data.focus.test_journey?.countdown_label" class="focus__test">
            <OlIcon name="test" :size="16" />
            {{ data.focus.test_journey.countdown_label }}
            <template v-if="data.focus.test_journey.test_centre">
              · {{ data.focus.test_journey.test_centre }}
            </template>
          </p>

          <p
            v-if="data.focus.finance?.show_on_teaching && data.focus.finance.teaching_line"
            class="ol-meta"
          >
            {{ data.focus.finance.teaching_line }}
          </p>

          <div class="focus__actions ol-actions">
            <NuxtLink :to="`/lessons/${data.focus.id}`" class="ol-btn">
              {{ data.focus.is_current ? 'Open lesson' : 'Open lesson' }}
              <span aria-hidden="true">→</span>
            </NuxtLink>
            <button
              v-if="data.focus.can_complete"
              class="ol-btn ol-btn--ghost"
              type="button"
              :disabled="completingId === data.focus.id"
              @click="onComplete(data.focus.id)"
            >
              {{ completingId === data.focus.id ? 'Completing…' : 'Complete' }}
            </button>
            <button
              v-if="data.focus.can_mark_no_show && online"
              class="ol-btn ol-btn--ghost"
              type="button"
              @click="navigateTo(`/lessons/${data.focus.id}`)"
            >
              Mark no-show
            </button>
          </div>

          <div v-if="data.focus.pickup_address" class="focus__tools ol-actions">
            <a
              v-if="online"
              class="ol-link-action"
              :href="mapsUrl(data.focus.pickup_address)"
              target="_blank"
              rel="noopener noreferrer"
            >
              <OlIcon name="map" :size="14" />
              Maps
            </a>
            <button class="ol-link-action" type="button" @click="onCopyPickup(data.focus.pickup_address!)">
              {{ copiedId === data.focus.id ? 'Copied' : 'Copy address' }}
            </button>
            <a class="ol-link-action" :href="`tel:${focusMobile}`" v-if="focusMobile">
              <OlIcon name="phone" :size="14" />
              Call
            </a>
          </div>
        </article>

        <div v-else-if="showDayWrap" class="day-end">
          <DayDoneCard
            :title="dayWrap?.celebration?.title || 'Teaching day done'"
            :subtitle="dayWrapSubtitle"
            :complete="true"
            :actions="[{ label: 'View day’s wrap', path: '/day-wrap', primary: true }]"
          />

          <DayWrapStats
            v-if="dayWrap?.stats?.length"
            :stats="dayWrap.stats"
            class="day-end__stats"
          />

          <section
            v-if="dayWrapOpenLessons.length"
            class="day-end__leftover"
            aria-label="Still to finish"
          >
            <h2 class="ol-section-title">Still to finish</h2>
            <div class="ol-panel ol-panel--flush">
              <ul class="ol-list-divide">
                <li v-for="lesson in dayWrapOpenLessons" :key="lesson.id">
                  <div class="day-end__row">
                    <div class="day-end__row-main">
                      <p class="ol-row__title">
                        <span class="ol-time">{{ lesson.starts_at_time }}</span>
                        {{ lesson.learner_name || 'Pupil' }}
                      </p>
                      <p class="ol-meta">{{ lesson.detail_line || lesson.status_label }}</p>
                    </div>
                    <NuxtLink
                      v-if="lesson.cta"
                      :to="lesson.cta.path"
                      class="ol-btn ol-btn--dark ol-btn--sm"
                    >
                      {{ lesson.cta.label }}
                    </NuxtLink>
                  </div>
                </li>
              </ul>
            </div>
          </section>
        </div>

        <div v-else class="ol-empty">
          <h2 class="ol-empty__title">
            {{ incompleteLessonCount > 0 ? 'Lessons still to finish' : 'You’re done for today' }}
          </h2>
          <p class="ol-empty__copy">
            <template v-if="incompleteLessonCount > 0">
              {{ incompleteLessonCount === 1
                ? '1 lesson still needs marking complete.'
                : `${incompleteLessonCount} lessons still need marking complete.` }}
            </template>
            <template v-else>
              {{ data.summary?.line || 'Nothing left to teach.' }}
            </template>
          </p>
        </div>

        <!-- TODAY schedule -->
        <section v-if="scheduleRows.length" class="schedule">
          <div class="schedule__head">
            <h2 class="ol-section-title">Today</h2>
            <NuxtLink to="/lessons" class="ol-back">Diary →</NuxtLink>
          </div>
          <div class="ol-panel ol-panel--flush">
            <ul class="ol-list-divide">
              <li v-for="lesson in scheduleRows" :key="lesson.id" class="schedule__item">
                <div class="schedule__row">
                  <NuxtLink :to="`/lessons/${lesson.id}`" class="ol-row schedule__link">
                    <span
                      class="ol-row__time"
                      :data-done="lesson.status === 'completed' ? 'yes' : 'no'"
                      :data-active="lesson.id === data.focus?.id ? 'yes' : 'no'"
                    >
                      {{ lesson.starts_at_time }}
                    </span>
                    <div class="ol-row__main">
                      <p class="ol-row__title">{{ lesson.learner_name }}</p>
                      <p class="ol-row__meta">
                        {{ durationLabel(lesson.duration_minutes) }}
                        <template v-if="lesson.pickup_address">
                          · {{ shortAddress(lesson.pickup_address) }}
                        </template>
                        <template v-if="lesson.status === 'completed'"> · Done</template>
                        <template v-else-if="lesson.status === 'no_show'"> · No-show</template>
                      </p>
                    </div>
                  </NuxtLink>
                  <LessonWeatherChip
                    v-if="weatherFor(lesson)"
                    :weather="weatherFor(lesson)!"
                    class="schedule__wx"
                  />
                  <NuxtLink :to="`/lessons/${lesson.id}`" class="schedule__chevron" tabindex="-1" aria-hidden="true">
                    <OlIcon name="chevron-right" :size="16" />
                  </NuxtLink>
                </div>
              </li>
            </ul>
          </div>
        </section>
      </template>

      <!-- FOR YOU TODAY -->
      <TodayForYouPanel
        v-if="(data.needs_you?.length ?? 0) > 0"
        class="today__extras"
        :actions="data.needs_you ?? []"
        :show-intro="showNeedsYouIntro"
        @dismiss-intro="dismiss('needs_you_intro')"
      />
    </template>
  </section>
</template>

<script setup lang="ts">
import type { CompletionAftermath } from '~/composables/useFinance'
import type { TodayResponse } from '~/composables/useToday'
import type { DayWrapLesson, DayWrapResponse } from '~/composables/useDayWrap'
import DayDoneCard from '~/components/day-wrap/DayDoneCard.vue'
import DayWrapStats from '~/components/day-wrap/DayWrapStats.vue'
import LessonWeatherChip from '~/components/weather/LessonWeatherChip.vue'

useHead({ title: 'Today · OwnLane' })

const { mapsUrl, copyText } = useToday()
const { durationLabel } = useLessons()
const { loadToday, completeLessonLocalFirst, online } = useOfflineTeaching()
const { fetchDayWrap } = useDayWrap()
const { me } = useAuth()
const { fillMissing: fillWeather, forLesson: weatherFor } = useLessonWeather()
const {
  onboarding,
  stage,
  showBusinessConfirm,
  showChecklist,
  showNeedsYouIntro,
  dismiss,
  refresh,
} = useOnboarding()
const { updateSettings } = useSettings()

const data = ref<TodayResponse | null>(null)
const dayWrap = ref<DayWrapResponse | null>(null)
const loading = ref(true)
const error = ref('')
const completingId = ref<number | null>(null)
const copiedId = ref<number | null>(null)
const justCompleted = ref<(CompletionAftermath & { lesson_id: number }) | null>(null)

const bizDisplayName = ref('')
const bizBusinessName = ref('')
const bizSaving = ref(false)
const bizError = ref('')

const greeting = computed(() => {
  const name = (me.value?.user.name || '').trim().split(/\s+/)[0]
  const hour = new Date().getHours()
  const hi = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening'
  return name ? `${hi}, ${name}` : hi
})

const teachingHoursLabel = computed(() => {
  const lessons = data.value?.lessons ?? []
  const minutes = lessons
    .filter(l => l.status === 'scheduled' || l.status === 'completed')
    .reduce((sum, l) => sum + (l.duration_minutes || 0), 0)
  if (minutes <= 0) return ''
  const hours = minutes / 60
  const label = Number.isInteger(hours) ? String(hours) : hours.toFixed(1)
  return `${label} teaching ${hours === 1 ? 'hour' : 'hours'}`
})

const scheduleRows = computed(() => {
  const lessons = data.value?.lessons ?? []
  return lessons
    .filter(l => l.status !== 'cancelled')
    .slice()
    .sort((a, b) => a.starts_at.localeCompare(b.starts_at))
})

const focusMobile = computed(() => {
  const mobile = data.value?.focus?.learner_mobile?.trim()
  return mobile || null
})

const dayWrapOpenLessons = computed((): DayWrapLesson[] =>
  (dayWrap.value?.lessons ?? []).filter(l => l.cta),
)

const showDayWrap = computed(() => !!dayWrap.value?.day_complete)

const incompleteLessonCount = computed(() =>
  (data.value?.lessons ?? []).filter(l => l.status === 'scheduled').length,
)

const dayWrapSubtitle = computed(() => {
  if (dayWrap.value?.celebration?.subtitle) return dayWrap.value.celebration.subtitle
  return data.value?.summary?.line || 'Nothing left to teach.'
})

const checklistTitle = computed(() => {
  if (stage.value === 'add_pupils') return 'Three quick steps'
  if (stage.value === 'book_lesson') return 'Next: book a lesson'
  return 'You’re nearly set'
})

const checklistCopy = computed(() => {
  if (stage.value === 'add_pupils') return 'Add pupils, book a lesson, then teach from Today.'
  if (stage.value === 'book_lesson') return 'Today becomes useful once there’s a lesson booked.'
  return 'Needs you only appears when something needs a decision.'
})

watch(onboarding, (value) => {
  if (!value) return
  if (!bizDisplayName.value) bizDisplayName.value = value.display_name || ''
  if (!bizBusinessName.value) bizBusinessName.value = value.business_name || ''
}, { immediate: true })

function shortAddress(address: string): string {
  const part = address.split(',')[0]?.trim()
  return part && part.length <= 36 ? part : `${address.slice(0, 34)}…`
}

async function onSaveBusiness() {
  bizSaving.value = true
  bizError.value = ''
  try {
    await updateSettings({
      display_name: bizDisplayName.value.trim(),
      business_name: bizBusinessName.value.trim(),
    })
    dismiss('business_confirm')
    await refresh()
  } catch (e) {
    bizError.value = extractApiError(e, 'Could not save.')
  } finally {
    bizSaving.value = false
  }
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [result] = await Promise.all([loadToday(), refresh()])
    data.value = result.data
    if (result.data) {
      void fillWeather({
        date: result.data.date,
        timezone: result.data.timezone,
        lessons: result.data.lessons,
      })
    }
    if (!result.data.focus && (result.data.lesson_count ?? 0) > 0) {
      try {
        dayWrap.value = await fetchDayWrap()
      } catch {
        dayWrap.value = null
      }
    } else {
      dayWrap.value = null
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : extractApiError(e, 'Could not load today.')
  } finally {
    loading.value = false
  }
}

async function onComplete(id: number) {
  completingId.value = id
  const focus = data.value?.focus
  try {
    const done = await completeLessonLocalFirst({ lessonId: id })
    if (done.finance?.aftermath) {
      justCompleted.value = { ...done.finance.aftermath, lesson_id: done.id }
    } else if (focus && focus.id === id) {
      const first = (focus.learner_name || 'Pupil').split(' ')[0]
      justCompleted.value = {
        headline: 'Lesson complete',
        learner_first_name: first,
        learner_name: focus.learner_name || first,
        credit_minutes: 0,
        credit_line: 'Saved on this device — updates when you’re back online.',
        has_prepaid_credit: false,
        owes_money: false,
        amount_owed_pence: 0,
        amount_owed_label: '£0.00',
        money_status_label: 'Nothing owed',
        settlement: null,
        primary_cta: 'book_next',
        primary_cta_label: 'Book next lesson',
        primary_cta_path: `/lessons/new?learner_id=${focus.learner_id}&from_lesson=${focus.id}`,
        secondary_cta: null,
        secondary_cta_label: null,
        secondary_cta_path: null,
        lesson_id: focus.id,
      }
    }
    await load()
  } catch (e) {
    error.value = extractApiError(e, 'Could not complete this lesson.')
  } finally {
    completingId.value = null
  }
}

async function onCopyPickup(address: string) {
  const ok = await copyText(address)
  if (ok && data.value?.focus) {
    copiedId.value = data.value.focus.id
    setTimeout(() => { copiedId.value = null }, 1600)
  }
}

onMounted(() => { void load() })
</script>

<style scoped>
.today__extras {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-top: 8px;
}

.today__header {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.today__summary {
  margin-top: 2px;
}

.day-end {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.day-end__stats {
  margin-top: 0;
}

.day-end__leftover {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.day-end__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 16px;
}

.day-end__row-main {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.aftermath {
  display: flex;
  flex-direction: column;
  gap: 8px;
  background: var(--color-success-wash);
  border-color: transparent;
  box-shadow: none;
}

.aftermath__name {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
}

.checklist {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 8px;
}

.checklist__item {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.checklist__item[data-done='yes'] {
  color: var(--color-ink-black);
}

.checklist__mark {
  width: 22px;
  text-align: center;
  color: var(--color-ownlane-green);
}

.focus {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.focus[data-phase='now'] {
  border-color: rgba(22, 139, 85, 0.35);
}

.focus__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.focus__clock-wrap {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.focus__clock {
  font-size: var(--text-heading-sm);
  line-height: 1;
}

.schedule__item {
  position: relative;
}

.schedule__row {
  display: flex;
  align-items: center;
  gap: 2px;
  min-width: 0;
}

.schedule__link {
  flex: 1;
  min-width: 0;
}

.schedule__wx {
  flex-shrink: 0;
}

.schedule__chevron {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 36px;
  margin-right: 8px;
  color: var(--color-muted);
  flex-shrink: 0;
}

.schedule .ol-panel--flush {
  overflow: visible;
}

.focus__clock-end {
  color: var(--color-muted);
  font-size: var(--text-body-sm);
}

.focus__name {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-md);
  line-height: var(--leading-heading-md);
  letter-spacing: var(--tracking-heading-md);
}

.focus__next {
  padding: 10px 12px;
  border-radius: var(--radius-small);
  background: var(--surface-wash);
}

.focus__warn,
.focus__test {
  display: inline-flex;
  align-items: flex-start;
  gap: 8px;
  font-size: var(--text-meta);
  padding: 8px 10px;
  border-radius: var(--radius-small);
  background: var(--color-warning-wash);
  color: var(--color-warning);
}

.focus__warn[data-severe='yes'] {
  background: var(--color-danger-wash);
  color: var(--color-danger);
}

.focus__actions {
  margin-top: 4px;
}

.focus__tools {
  padding-top: 4px;
}

.schedule {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.schedule__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
}

.ol-row__time[data-active='yes'] {
  color: var(--color-ownlane-green);
}

.ol-row__time[data-done='yes'] {
  color: var(--color-muted);
  text-decoration: line-through;
}
</style>
