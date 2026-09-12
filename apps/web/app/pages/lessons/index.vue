<template>
  <section class="diary ol-page">
    <div class="toolbar" role="toolbar" aria-label="Diary">
      <div class="toolbar__left">
        <div class="toolbar__nav">
          <button class="ol-chip" type="button" :disabled="loading" aria-label="Previous" @click="shift(-1)">
            ←
          </button>
          <p class="toolbar__label" aria-live="polite">{{ diary?.label || '…' }}</p>
          <button class="ol-chip" type="button" :disabled="loading" aria-label="Next" @click="shift(1)">
            →
          </button>
        </div>

        <button
          class="ol-chip toolbar__today"
          type="button"
          :class="{ 'ol-chip--on': isViewingToday }"
          :disabled="loading || isViewingToday"
          @click="goToday"
        >
          Today
        </button>

        <div class="ol-seg" role="group" aria-label="View">
          <button
            v-for="opt in viewOptions"
            :key="opt"
            class="ol-chip"
            type="button"
            :class="{ 'ol-chip--on': view === opt }"
            @click="setView(opt)"
          >
            {{ opt[0]!.toUpperCase() + opt.slice(1) }}
          </button>
        </div>
      </div>

      <div class="toolbar__right">
        <button
          class="ol-chip"
          type="button"
          :class="{ 'ol-chip--on': overviewActive }"
          :aria-pressed="overviewActive"
          @click="toggleOverviewPanel"
        >
          Overview
        </button>

        <div v-if="view !== 'month'" class="type-filter" ref="typeFilterRoot">
          <button
            class="type-filter__trigger"
            type="button"
            :aria-expanded="typeFilterOpen"
            aria-haspopup="listbox"
            aria-controls="diary-type-filter"
            @click="typeFilterOpen = !typeFilterOpen"
          >
            <span class="type-filter__dots" aria-hidden="true">
              <i
                v-for="(dot, i) in filterTriggerDots"
                :key="`${dot.kind}-${dot.tone}-${i}`"
                class="type-filter__dot"
                :data-tone="dot.kind === 'payment' ? dot.tone : undefined"
                :data-area-slot="dot.kind === 'area' ? dot.tone : undefined"
              />
            </span>
            <span class="type-filter__label">{{ filterTriggerLabel }}</span>
          </button>

          <div
            v-if="typeFilterOpen"
            id="diary-type-filter"
            class="type-filter__menu"
            role="listbox"
            :aria-label="colourMode === 'area' ? 'Colour and filter by area' : 'Colour and filter by payment'"
          >
            <div class="type-filter__mode" role="group" aria-label="Colour diary by">
              <span class="type-filter__mode-label">Colour by</span>
              <div class="type-filter__mode-seg">
                <button
                  type="button"
                  class="type-filter__mode-btn"
                  :class="{ 'type-filter__mode-btn--on': colourMode === 'payment' }"
                  :aria-pressed="colourMode === 'payment'"
                  @click="setColourMode('payment')"
                >
                  Payment
                </button>
                <button
                  type="button"
                  class="type-filter__mode-btn"
                  :class="{ 'type-filter__mode-btn--on': colourMode === 'area' }"
                  :aria-pressed="colourMode === 'area'"
                  @click="setColourMode('area')"
                >
                  Area
                </button>
              </div>
            </div>

            <template v-if="colourMode === 'payment'">
              <button
                v-for="opt in filterOptions"
                :key="opt.value"
                class="type-filter__option"
                type="button"
                role="option"
                :aria-selected="typeFilter === opt.value"
                @click="selectTypeFilter(opt.value)"
              >
                <i class="type-filter__swatch" :data-tone="opt.swatch" aria-hidden="true" />
                <span>{{ opt.label }}</span>
              </button>
            </template>
            <template v-else>
              <button
                class="type-filter__option"
                type="button"
                role="option"
                :aria-selected="areaFilter === 'all'"
                @click="selectAreaFilter('all')"
              >
                <i class="type-filter__swatch" data-tone="all-areas" aria-hidden="true" />
                <span>All areas</span>
              </button>
              <button
                v-for="area in diaryAreas"
                :key="area.key"
                class="type-filter__option"
                type="button"
                role="option"
                :aria-selected="areaFilter === area.key"
                @click="selectAreaFilter(area.key)"
              >
                <i
                  class="type-filter__swatch"
                  :data-area-slot="String(area.slot)"
                  aria-hidden="true"
                />
                <span>{{ area.label }}</span>
              </button>
              <p v-if="!diaryAreas.length" class="type-filter__empty">
                Book lessons with a pickup to see areas here.
              </p>
            </template>
          </div>
        </div>

        <button class="ol-btn ol-btn--sm toolbar__book" type="button" @click="openBookPopup({ date: date })">
          Book
        </button>
      </div>
    </div>

    <!-- Mobile date strip (day navigation) -->
    <div v-if="view !== 'month' && weekStrip.length && !isDesktop" class="strip" aria-label="Days this week">
      <button
        v-for="d in weekStrip"
        :key="d.date"
        class="strip__day"
        type="button"
        :data-on="d.date === date ? 'yes' : 'no'"
        :data-today="d.isToday ? 'yes' : 'no'"
        @click="setDate(d.date)"
      >
        <span class="strip__dow">{{ d.dow }}</span>
        <span
          class="strip__dom"
          :class="{ 'strip__dom--today': d.isToday }"
        >{{ d.dom }}</span>
      </button>
    </div>

    <p v-if="error" class="ol-error" role="alert">{{ error }}</p>
    <p
      v-if="slotPick && !slotPick.pendingStartsAtLocal && !error"
      class="diary__pick-banner"
      role="status"
    >
      Tap a free slot to
      {{ slotPick.kind === 'suggest' ? 'offer a new time' : 'move this lesson' }}.
      <button type="button" class="diary__pick-cancel" @click="onCancelPickSlot">Cancel</button>
    </p>
    <div v-if="loading && !diary && !error" class="diary__skel" aria-hidden="true">
      <div class="ol-skeleton" style="height: 520px; width: 100%" />
    </div>

    <template v-if="diary">
      <!-- MONTH -->
      <div
        v-if="view === 'month'"
        class="grid-wrap__main"
        :class="{ 'grid-wrap__main--panel': showSidePanel }"
      >
        <div class="month">
          <div class="month__dows" aria-hidden="true">
            <span v-for="d in weekDayLabels" :key="d">{{ d }}</span>
          </div>
          <div class="month__grid">
            <button
              v-for="day in diary.days"
              :key="day.date"
              class="month__cell"
              type="button"
              :data-today="day.is_today ? 'yes' : 'no'"
              :data-out="day.in_month === false ? 'yes' : 'no'"
              @click="openDay(day.date)"
            >
              <span class="month__num">{{ day.date_display }}</span>
              <span v-if="day.lesson_count" class="month__count">
                {{ day.lesson_count }}
                <template v-if="(day.teaching_minutes ?? 0) > 0">
                  · {{ hoursLabel(day.teaching_minutes!) }}
                </template>
              </span>
              <span class="month__dots" aria-hidden="true">
                <i
                  v-for="m in (day.markers ?? []).slice(0, 4)"
                  :key="m.id"
                  class="month__dot"
                  :data-status="m.status"
                  :data-overlap="m.overlaps ? 'yes' : 'no'"
                />
              </span>
              <span v-if="day.has_test" class="month__tag">Test</span>
              <span v-else-if="day.has_overlap" class="month__tag month__tag--warn">Overlap</span>
              <span v-else-if="day.has_cancellation" class="month__tag month__tag--muted">Cancel</span>
            </button>
          </div>
        </div>

        <Transition name="diary-side">
          <div v-if="showSidePanel" class="grid-wrap__side">
              <CalendarDiarySidePanel
              ref="sidePanelRef"
              :mode="panelMode"
              :lesson="selectedLesson"
              :request="selectedRequest"
              :block="selectedBlock"
              :overview="overview"
              :slot-pick="slotPick"
              :flash="panelFlash"
              @close="closePanel"
              @select-day="onOverviewSelectDay"
              @changed="onAppointmentChanged"
              @start-pick-slot="onStartPickSlot"
              @cancel-pick-slot="onCancelPickSlot"
              @confirm-pick-slot="onConfirmPickSlot"
              @book-next="onBookNextFromLesson"
              @block-changed="onBlockChanged"
              @block-removed="onBlockRemoved"
            />
          </div>
        </Transition>
      </div>

      <!-- DAY / WEEK TIME GRID -->
      <div v-else class="grid-wrap">
        <div v-if="!hasAnyPupils" class="ol-empty ol-empty--banner">
          <h2 class="ol-empty__title">No pupils yet</h2>
          <p class="ol-empty__copy">Add or import pupils before booking lessons.</p>
          <div class="ol-empty__actions">
            <NuxtLink to="/pupils/new" class="ol-btn ol-btn--sm">Add pupil →</NuxtLink>
          </div>
        </div>

        <div
          class="grid-wrap__main"
          :class="{ 'grid-wrap__main--panel': showSidePanel }"
        >
          <div class="grid-wrap__calendar">
            <CalendarDayGrid
              :days="gridDays"
              :bounds="bounds"
              :now-minutes="nowMinutes"
              :show-now="true"
              :compact="view === 'week'"
              :interactive="hasAnyPupils"
              :work-start-time="workStart"
              :work-end-time="workEnd"
              :work-days="workDays"
              :breaks="visibleBreaks"
              :colour-mode="colourMode"
              :focus-type="typeFilter"
              :focus-area="areaFilter"
              :select-lessons="true"
              :booking-requests="bookingRequests"
              :slot-pick-active="!!slotPick"
              @book="onSlotPick"
              @open-day="onOpenDay"
              @gap-open="onGapOpen"
              @select-break="onSelectBlock"
              @select-lesson="onSelectLesson"
              @select-request="onSelectRequest"
            />
          </div>

          <Transition name="diary-side">
            <div v-if="showSidePanel" class="grid-wrap__side">
              <CalendarDiarySidePanel
              ref="sidePanelRef"
              :mode="panelMode"
              :lesson="selectedLesson"
              :request="selectedRequest"
              :block="selectedBlock"
              :overview="overview"
              :slot-pick="slotPick"
              :flash="panelFlash"
              @close="closePanel"
              @select-day="onOverviewSelectDay"
              @changed="onAppointmentChanged"
              @start-pick-slot="onStartPickSlot"
              @cancel-pick-slot="onCancelPickSlot"
              @confirm-pick-slot="onConfirmPickSlot"
              @book-next="onBookNextFromLesson"
              @block-changed="onBlockChanged"
              @block-removed="onBlockRemoved"
            />
            </div>
          </Transition>
        </div>

        <CalendarDiaryGapSheet
          :gap="selectedGap"
          :open="gapSheetOpen"
          @close="gapSheetOpen = false"
          @book="openBookPopup"
        />

        <DiaryBookPopup
          :open="bookPopupOpen"
          :preset="bookPreset"
          @close="closeBookPopup"
          @booked="onBooked"
        />

        <Teleport to="body">
          <div
            v-if="slotChoice"
            class="slot-choice"
            role="dialog"
            aria-modal="true"
            aria-label="Add to diary"
          >
            <button class="slot-choice__backdrop" type="button" aria-label="Close" @click="slotChoice = null" />
            <div class="slot-choice__panel">
              <p class="slot-choice__title">Add to this slot</p>
              <p class="slot-choice__meta">
                {{ slotChoice.starts_at_local.slice(11, 16) }}
                ·
                {{ slotChoice.duration_minutes || 60 }} minutes
              </p>
              <div class="slot-choice__actions">
                <button class="ol-btn ol-btn--block" type="button" @click="confirmBookLesson">
                  Book lesson
                </button>
              </div>
              <label class="ol-field slot-choice__label">
                <span class="ol-field__label">Or block private time</span>
                <input v-model="breakLabel" class="ol-input" type="text" maxlength="40" placeholder="Admin, Holiday, School run">
              </label>
              <button class="ol-btn ol-btn--ghost ol-btn--block" type="button" :disabled="blockPending" @click="confirmAddBreak">
                {{ blockPending ? 'Saving…' : 'Block time' }}
              </button>
            </div>
          </div>
        </Teleport>
      </div>
    </template>

    <Teleport to="body">
      <Transition name="overview-sheet">
        <div
          v-if="diary && mobileOverviewOpen && overview"
          class="overview-sheet"
          role="dialog"
          aria-modal="true"
          aria-label="Diary overview"
        >
          <button
            class="overview-sheet__backdrop"
            type="button"
            aria-label="Close overview"
            @click="mobileOverviewOpen = false"
          />
          <div class="overview-sheet__panel">
            <div class="overview-sheet__head">
              <p class="overview-sheet__eyebrow">Overview</p>
              <button
                class="overview-sheet__x"
                type="button"
                aria-label="Close overview"
                @click="mobileOverviewOpen = false"
              >
                <OlIcon name="close" :size="16" />
              </button>
            </div>
            <div class="overview-sheet__body">
              <DiaryOverview
                :model="overview"
                @select-day="onOverviewSelectDay"
              />
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
    <Teleport to="body">
      <Transition name="overview-sheet">
        <div
          v-if="diary && mobileLessonOpen && (selectedLesson || selectedRequest || selectedBlock)"
          class="overview-sheet"
          role="dialog"
          aria-modal="true"
          :aria-label="selectedBlock ? 'Private time' : (selectedRequest ? 'Lesson request' : 'Lesson')"
        >
          <button
            class="overview-sheet__backdrop"
            type="button"
            aria-label="Close"
            @click="closePanel"
          />
          <div class="overview-sheet__panel overview-sheet__panel--lesson">
            <p v-if="panelFlash" class="overview-sheet__flash" role="status">{{ panelFlash }}</p>
            <div class="overview-sheet__body overview-sheet__body--lesson">
              <DiaryBlockPanel
                v-if="selectedBlock && panelMode === 'block'"
                :block="selectedBlock"
                @close="closePanel"
                @saved="onBlockChanged"
                @removed="onBlockRemoved"
              />
              <DiaryLessonAppointment
                v-else
                ref="mobileApptRef"
                :lesson="selectedLesson"
                :request="selectedRequest"
                :slot-pick="slotPick"
                @close="closePanel"
                @changed="onAppointmentChanged"
                @start-pick-slot="onStartPickSlot"
                @cancel-pick-slot="onCancelPickSlot"
                @confirm-pick-slot="onConfirmPickSlot"
                @book-next="onBookNextFromLesson"
              />
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </section>
</template>

<script setup lang="ts">
import type { DiaryGap, DiaryLesson, DiaryResponse } from '~/composables/useLessons'
import {
  parseHm,
  fullDayGridBounds,
  type GridBounds,
} from '~/utils/calendar/timeGrid'
import CalendarDiarySidePanel from '~/components/calendar/DiarySidePanel.vue'
import CalendarDiaryGapSheet from '~/components/calendar/DiaryGapSheet.vue'
import DiaryOverview from '~/components/calendar/overview/DiaryOverview.vue'
import type {
  BookNextPayload,
  InstructorBookingRequest,
  SlotPickState,
} from '~/components/calendar/DiaryLessonAppointment.vue'
import DiaryLessonAppointment from '~/components/calendar/DiaryLessonAppointment.vue'
import DiaryBlockPanel from '~/components/calendar/DiaryBlockPanel.vue'
import DiaryBookPopup, { type DiaryBookPreset } from '~/components/calendar/DiaryBookPopup.vue'
import {
  collectDiaryAreas,
  readDiaryColourMode,
  writeDiaryColourMode,
  type DiaryColourMode,
} from '~/utils/calendar/diaryAreaColour'

useHead({ title: 'Diary · OwnLane' })

type DiaryView = 'day' | 'week' | 'month'
type DiarySidePanelMode = 'welcome' | 'lesson' | 'request' | 'block'
const VIEW_KEY = 'ownlane.diary.view'

const route = useRoute()
const router = useRouter()
const { fetchDiary } = useLessons()
const { me } = useAuth()
const { onboarding, refresh } = useOnboarding()
const { breaksForDates, createBlock } = useDiaryBreaks()
const { listPending } = useInstructorBooking()

const diary = ref<DiaryResponse | null>(null)
const { overview } = useDiaryOverview(diary)
const loading = ref(true)
const error = ref('')
const isDesktop = ref(true)
const gapSheetOpen = ref(false)
const selectedGap = ref<DiaryGap | null>(null)
const slotChoice = ref<{
  date: string
  starts_at_local: string
  duration_minutes: number
} | null>(null)
const breakLabel = ref('Private')
const blockPending = ref(false)

const panelOpen = ref(true)
const panelMode = ref<DiarySidePanelMode>('welcome')
const selectedLesson = ref<DiaryLesson | null>(null)
const selectedRequest = ref<InstructorBookingRequest | null>(null)
const selectedBlock = ref<import('~/composables/useDiaryBreaks').DiaryBreak | null>(null)
const panelDismissed = ref(false)
const mobileOverviewOpen = ref(false)
const mobileLessonOpen = ref(false)
const bookingRequests = ref<InstructorBookingRequest[]>([])
const slotPick = ref<SlotPickState>(null)
const panelFlash = ref<string | null>(null)
const sidePanelRef = ref<InstanceType<typeof CalendarDiarySidePanel> | null>(null)
const mobileApptRef = ref<InstanceType<typeof DiaryLessonAppointment> | null>(null)
const bookPopupOpen = ref(false)
const bookPreset = ref<DiaryBookPreset | null>(null)
let flashTimer: ReturnType<typeof setTimeout> | null = null

type DiaryFocusType = 'all' | 'paid' | 'unpaid' | 'package' | 'break' | 'offer'
const typeFilter = ref<DiaryFocusType>('all')
const areaFilter = ref<string>('all')
const colourMode = ref<DiaryColourMode>(readDiaryColourMode())
const typeFilterOpen = ref(false)
const typeFilterRoot = ref<HTMLElement | null>(null)

const filterOptions: Array<{ value: DiaryFocusType; label: string; swatch: string }> = [
  { value: 'all', label: 'All types', swatch: 'all' },
  { value: 'paid', label: 'Paid', swatch: 'paid' },
  { value: 'unpaid', label: 'Unpaid', swatch: 'unpaid' },
  { value: 'package', label: 'Block', swatch: 'package' },
  { value: 'break', label: 'Private', swatch: 'break' },
  { value: 'offer', label: 'Offer', swatch: 'offer' },
]

const diaryAreas = computed(() => {
  const flat = diary.value?.lessons ?? []
  const nested = (diary.value?.days ?? []).flatMap(d => d.lessons ?? [])
  const pickups = (flat.length ? flat : nested).map(l => l.pickup_address)
  return collectDiaryAreas(pickups)
})

const filterTriggerDots = computed(() => {
  if (colourMode.value === 'area') {
    if (areaFilter.value === 'all') {
      return diaryAreas.value.slice(0, 4).map(a => ({ kind: 'area' as const, tone: String(a.slot) }))
    }
    const hit = diaryAreas.value.find(a => a.key === areaFilter.value)
    return hit
      ? [{ kind: 'area' as const, tone: String(hit.slot) }]
      : [{ kind: 'area' as const, tone: '0' }]
  }
  if (typeFilter.value === 'all') {
    return ['paid', 'unpaid', 'package', 'break'].map(tone => ({ kind: 'payment' as const, tone }))
  }
  const tone = typeFilter.value === 'package' ? 'package' : typeFilter.value
  return [{ kind: 'payment' as const, tone }]
})

const filterTriggerLabel = computed(() => {
  if (colourMode.value === 'area') {
    if (areaFilter.value === 'all') return 'All areas'
    return diaryAreas.value.find(a => a.key === areaFilter.value)?.label ?? 'Area'
  }
  if (typeFilter.value === 'all') return 'All types'
  return filterOptions.find(o => o.value === typeFilter.value)?.label ?? 'Filter'
})

function setColourMode(next: DiaryColourMode) {
  colourMode.value = next
  writeDiaryColourMode(next)
  typeFilter.value = 'all'
  areaFilter.value = 'all'
}

function selectTypeFilter(next: DiaryFocusType) {
  typeFilter.value = next
  typeFilterOpen.value = false
}

function selectAreaFilter(next: string) {
  areaFilter.value = next
  typeFilterOpen.value = false
}

function onTypeFilterClickOutside(e: MouseEvent) {
  if (!typeFilterOpen.value) return
  const root = typeFilterRoot.value
  if (root && !root.contains(e.target as Node)) {
    typeFilterOpen.value = false
  }
}

function syncPanelForViewport() {
  if (!isDesktop.value) {
    panelOpen.value = false
    if (panelMode.value === 'welcome') {
      selectedLesson.value = null
      selectedRequest.value = null
    }
    return
  }
  mobileOverviewOpen.value = false
  mobileLessonOpen.value = false
  // Desktop: open by default until the instructor closes it this session.
  if (!panelDismissed.value) {
    panelOpen.value = true
    if (panelMode.value === 'welcome') {
      selectedLesson.value = null
      selectedRequest.value = null
    }
  }
}

function toggleOverviewPanel() {
  if (!isDesktop.value) {
    mobileOverviewOpen.value = !mobileOverviewOpen.value
    mobileLessonOpen.value = false
    return
  }
  if (panelOpen.value && panelMode.value === 'welcome') {
    closePanel()
    return
  }
  panelDismissed.value = false
  selectedLesson.value = null
  selectedRequest.value = null
  selectedBlock.value = null
  panelMode.value = 'welcome'
  panelOpen.value = true
  slotPick.value = null
}

function openAppointmentPanel() {
  panelDismissed.value = false
  panelOpen.value = true
  if (!isDesktop.value) {
    mobileLessonOpen.value = true
    mobileOverviewOpen.value = false
  }
}

function onSelectLesson(lesson: DiaryLesson) {
  selectedLesson.value = lesson
  selectedRequest.value = null
  selectedBlock.value = null
  panelMode.value = 'lesson'
  slotPick.value = null
  openAppointmentPanel()
}

function onSelectRequest(request: InstructorBookingRequest) {
  selectedRequest.value = request
  selectedLesson.value = null
  selectedBlock.value = null
  panelMode.value = 'request'
  slotPick.value = null
  openAppointmentPanel()
}

function closePanel() {
  panelOpen.value = false
  panelDismissed.value = true
  selectedLesson.value = null
  selectedRequest.value = null
  selectedBlock.value = null
  panelMode.value = 'welcome'
  mobileLessonOpen.value = false
  slotPick.value = null
}

function onOverviewSelectDay(next: string) {
  mobileOverviewOpen.value = false
  void openDay(next)
}

function setFlash(message?: string) {
  if (flashTimer) clearTimeout(flashTimer)
  panelFlash.value = message || null
  if (message) {
    flashTimer = setTimeout(() => { panelFlash.value = null }, 2800)
  }
}

async function onAppointmentChanged(message?: string) {
  setFlash(message)
  slotPick.value = null
  await load()
  // Refresh selected lesson from reloaded diary when possible
  if (selectedLesson.value) {
    const id = selectedLesson.value.id
    const next = diary.value?.lessons?.find(l => l.id === id)
      || diary.value?.days.flatMap(d => d.lessons || []).find(l => l.id === id)
    if (next) selectedLesson.value = next
    else {
      selectedLesson.value = null
      panelMode.value = 'welcome'
      mobileLessonOpen.value = false
    }
  }
  if (selectedRequest.value) {
    const id = selectedRequest.value.id
    const next = bookingRequests.value.find(r => r.id === id)
    if (next) selectedRequest.value = next
    else {
      selectedRequest.value = null
      panelMode.value = 'welcome'
      mobileLessonOpen.value = false
    }
  }
}

function onStartPickSlot(kind: 'move' | 'suggest') {
  slotPick.value = { kind, pendingStartsAtLocal: null, pendingLabel: null }
  if (!isDesktop.value) {
    mobileLessonOpen.value = false
  }
}

function onCancelPickSlot() {
  slotPick.value = null
  if (!isDesktop.value && (selectedLesson.value || selectedRequest.value)) {
    mobileLessonOpen.value = true
  }
}

async function onConfirmPickSlot() {
  const pending = slotPick.value?.pendingStartsAtLocal
  if (!pending || !slotPick.value) return
  const kind = slotPick.value.kind
  if (isDesktop.value) {
    if (kind === 'move') await sidePanelRef.value?.applyMove?.(pending)
    else await sidePanelRef.value?.applySuggest?.(pending)
  } else {
    if (kind === 'move') await mobileApptRef.value?.applyMove?.(pending)
    else await mobileApptRef.value?.applySuggest?.(pending)
  }
}

const viewOptions: DiaryView[] = ['day', 'week', 'month']

const hasAnyPupils = computed(() => {
  if ((onboarding.value?.pupil_count ?? 0) > 0) return true
  // Fall back to diary payload so a stale onboarding count can't blank the grid
  return (diary.value?.lessons?.length ?? 0) > 0
    || (diary.value?.days ?? []).some(d => d.lesson_count > 0)
})

const view = computed<DiaryView>(() => {
  const q = route.query.view
  if (q === 'week' || q === 'month' || q === 'day') return q
  return 'day'
})

const showSidePanel = computed(() =>
  isDesktop.value && panelOpen.value,
)

const overviewActive = computed(() =>
  (showSidePanel.value && panelMode.value === 'welcome')
  || (!isDesktop.value && mobileOverviewOpen.value),
)

const date = computed(() => {
  const q = route.query.date
  if (typeof q === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(q)) return q
  return localToday()
})

const isViewingToday = computed(() => {
  const today = localToday()
  if (view.value === 'day') return date.value === today
  // Week / month: “today” means the loaded range includes today
  if (diary.value?.days?.length) {
    return diary.value.days.some(d => d.is_today || d.date === today)
  }
  return date.value === today
})

const workStart = computed(() =>
  diary.value?.work_start_time
  || me.value?.organisation?.work_start_time
  || '08:00',
)
const workEnd = computed(() =>
  diary.value?.work_end_time
  || me.value?.organisation?.work_end_time
  || '18:00',
)

const workDays = computed(() =>
  diary.value?.work_days
  || me.value?.organisation?.work_days
  || [],
)

const weekStartsOn = computed(() =>
  diary.value?.week_starts_on
  || me.value?.organisation?.week_starts_on
  || 1,
)

const weekDayLabels = computed(() => {
  const labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
  const start = ((weekStartsOn.value - 1) % 7 + 7) % 7
  return [...labels.slice(start), ...labels.slice(0, start)]
})

const gridDays = computed(() => {
  if (!diary.value) return []
  // Desktop week = seven day columns in one row.
  if (view.value === 'week' && isDesktop.value) return diary.value.days
  const match = diary.value.days.find(d => d.date === date.value)
  return match ? [match] : diary.value.days.slice(0, 1)
})

const bounds = computed<GridBounds>(() => {
  // Full midnight–midnight day. Work hours only shade the outside bands.
  return fullDayGridBounds(view.value === 'day' ? 1.35 : 0.95)
})

const visibleBreaks = computed(() => {
  const dates = gridDays.value.map(d => d.date)
  const fromDays = gridDays.value.flatMap(d => d.blocks || [])
  const fromTop = diary.value?.blocks || []
  const merged = fromDays.length ? fromDays : fromTop
  return breaksForDates(merged, dates)
})

const nowMinutes = computed(() => {
  if (!diary.value?.now_time) return null
  return parseHm(diary.value.now_time)
})

const weekStrip = computed(() => {
  const anchor = parseYmd(date.value)
  if (!anchor) return []
  const isoDow = ((anchor.getDay() + 6) % 7) + 1 // 1=Mon … 7=Sun
  const offset = (isoDow - weekStartsOn.value + 7) % 7
  const weekStart = new Date(anchor)
  weekStart.setDate(anchor.getDate() - offset)
  const today = localToday()
  return Array.from({ length: 7 }, (_, i) => {
    const d = new Date(weekStart)
    d.setDate(weekStart.getDate() + i)
    const ymd = formatYmd(d)
    return {
      date: ymd,
      dow: d.toLocaleDateString('en-GB', { weekday: 'short' }),
      dom: String(d.getDate()),
      isToday: ymd === today,
    }
  })
})

function hoursLabel(minutes: number): string {
  const h = minutes / 60
  return Number.isInteger(h) ? `${h}h` : `${h.toFixed(1)}h`
}

function onGapOpen(gap: DiaryGap) {
  selectedGap.value = gap
  gapSheetOpen.value = true
}

function onSlotPick(payload: { date: string; starts_at_local: string; duration_minutes?: number }) {
  if (slotPick.value) {
    const start = payload.starts_at_local
    const time = start.slice(11, 16)
    const d = parseYmd(payload.date)
    const dayLabel = d
      ? d.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })
      : payload.date
    const dur = slotPick.value.kind === 'move'
      ? (selectedLesson.value?.duration_minutes || payload.duration_minutes || 60)
      : (selectedRequest.value?.duration_minutes || payload.duration_minutes || 60)
    const endMins = parseHm(time) + dur
    const endH = String(Math.floor(endMins / 60)).padStart(2, '0')
    const endM = String(endMins % 60).padStart(2, '0')
    slotPick.value = {
      kind: slotPick.value.kind,
      pendingStartsAtLocal: start.length === 16 ? `${start}:00` : start,
      pendingLabel: `${dayLabel} · ${time}–${endH}:${endM}`,
    }
    if (!isDesktop.value) mobileLessonOpen.value = true
    return
  }
  slotChoice.value = {
    date: payload.date,
    starts_at_local: payload.starts_at_local,
    duration_minutes: payload.duration_minutes || 60,
  }
  breakLabel.value = 'Private'
}

function openBookPopup(preset: DiaryBookPreset = {}) {
  gapSheetOpen.value = false
  slotChoice.value = null
  bookPreset.value = preset
  bookPopupOpen.value = true
}

function closeBookPopup() {
  bookPopupOpen.value = false
  bookPreset.value = null
}

async function onBooked(message?: string) {
  setFlash(message)
  closeBookPopup()
  closePanel()
  await load()
}

function confirmBookLesson() {
  if (!slotChoice.value) return
  const slot = slotChoice.value
  slotChoice.value = null
  openBookPopup({
    date: slot.date,
    startsAtLocal: slot.starts_at_local,
    durationMinutes: slot.duration_minutes,
  })
}

function onBookNextFromLesson(payload?: BookNextPayload) {
  const lesson = selectedLesson.value
  const request = selectedRequest.value
  const learnerId = lesson?.learner_id || request?.learner_id
  if (!learnerId) return
  const suggestion = payload?.suggestion || null
  openBookPopup({
    learnerId,
    durationMinutes: suggestion?.duration_minutes
      || lesson?.duration_minutes
      || request?.duration_minutes
      || undefined,
    pickup: suggestion?.pickup_address
      || lesson?.pickup_address
      || request?.pickup_address
      || undefined,
    startsAtLocal: suggestion?.suggested_starts_at_local || undefined,
    lockPupil: true,
    suggestion: suggestion || undefined,
  })
}

async function confirmAddBreak() {
  if (!slotChoice.value || blockPending.value) return
  blockPending.value = true
  try {
    await createBlock({
      starts_at_local: slotChoice.value.starts_at_local,
      duration_minutes: slotChoice.value.duration_minutes,
      label: breakLabel.value,
    })
    slotChoice.value = null
    setFlash('Private time blocked')
    await load()
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not block that time.'
  } finally {
    blockPending.value = false
  }
}

function onSelectBlock(item: import('~/composables/useDiaryBreaks').DiaryBreak) {
  selectedLesson.value = null
  selectedRequest.value = null
  selectedBlock.value = item
  panelMode.value = 'block'
  panelOpen.value = true
  panelDismissed.value = false
  if (!isDesktop.value) mobileLessonOpen.value = true
}

async function onBlockChanged(message?: string) {
  setFlash(message)
  await load()
  // Refresh selected block from latest diary payload
  if (selectedBlock.value && diary.value?.blocks) {
    const match = diary.value.blocks.find(b => String(b.id) === selectedBlock.value!.id)
    if (match) {
      selectedBlock.value = breaksForDates([match], [match.date])[0] || selectedBlock.value
    }
  }
}

async function onBlockRemoved() {
  selectedBlock.value = null
  setFlash('Block removed')
  closePanel()
  await load()
}

function localToday(): string {
  const n = new Date()
  return formatYmd(n)
}

function formatYmd(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function parseYmd(ymd: string): Date | null {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(ymd)
  if (!m) return null
  return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]))
}

function addDays(ymd: string, days: number): string {
  const d = parseYmd(ymd)
  if (!d) return ymd
  d.setDate(d.getDate() + days)
  return formatYmd(d)
}

function addMonths(ymd: string, months: number): string {
  const d = parseYmd(ymd)
  if (!d) return ymd
  d.setMonth(d.getMonth() + months)
  return formatYmd(d)
}

async function setView(next: DiaryView) {
  if (import.meta.client) localStorage.setItem(VIEW_KEY, next)
  // Mobile: week view becomes day-first navigation, not a 7-column grid.
  const effective = !isDesktop.value && next === 'week' ? 'day' : next
  await router.replace({ query: { ...route.query, view: effective, date: date.value } })
}

async function setDate(next: string) {
  await router.replace({ query: { ...route.query, date: next, view: view.value } })
}

/** Month cells open day view. Week day headers only move the focused date. */
async function onOpenDay(next: string) {
  if (view.value === 'week') {
    await setDate(next)
    return
  }
  await router.replace({ query: { view: 'day', date: next } })
}

async function openDay(next: string) {
  await router.replace({ query: { view: 'day', date: next } })
}

async function goToday() {
  await router.replace({ query: { ...route.query, date: localToday(), view: view.value } })
}

async function shift(dir: number) {
  let next = date.value
  if (view.value === 'day') next = addDays(date.value, dir)
  else if (view.value === 'week') next = addDays(date.value, dir * 7)
  else next = addMonths(date.value, dir)
  await setDate(next)
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [result, requests] = await Promise.all([
      fetchDiary(view.value, date.value),
      listPending().catch(() => ({ items: [] as InstructorBookingRequest[] })),
      refresh(),
    ])
    diary.value = result
    bookingRequests.value = ((requests.items || []) as InstructorBookingRequest[])
      .filter(r => r.status === 'pending' || r.status === 'counter_proposed')
  } catch (e) {
    error.value = extractApiError(e, 'Could not load diary.')
  } finally {
    loading.value = false
  }
}

function syncDesktop() {
  const desktop = window.matchMedia('(min-width: 900px)').matches
  isDesktop.value = desktop
  if (!desktop && view.value === 'week') {
    void router.replace({ query: { ...route.query, view: 'day', date: date.value } })
  }
  syncPanelForViewport()
}

onMounted(() => {
  syncDesktop()
  window.addEventListener('resize', syncDesktop)
  document.addEventListener('pointerdown', onTypeFilterClickOutside)

  // Restore preferred view if URL has no view (and not forced by mobile).
  if (!route.query.view && import.meta.client) {
    const saved = localStorage.getItem(VIEW_KEY) as DiaryView | null
    const preferred = saved === 'week' || saved === 'month' || saved === 'day' ? saved : null
    if (preferred) {
      // Phones default to day even if week/month was saved.
      const next = window.matchMedia('(max-width: 899px)').matches ? 'day' : preferred
      void router.replace({ query: { ...route.query, view: next, date: date.value } })
    }
  }

  // Always load. Do not return early after router.replace — if the restored view
  // matches the default ("day"), [view, date] will not change and the watcher
  // would never fetch, leaving the diary stuck on the skeleton.
  void load()
  syncPanelForViewport()
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', syncDesktop)
  document.removeEventListener('pointerdown', onTypeFilterClickOutside)
})

watch([view, date], () => {
  void load()
})

watch(view, () => {
  if (panelMode.value === 'lesson' || panelMode.value === 'request') {
    selectedLesson.value = null
    selectedRequest.value = null
    panelMode.value = 'welcome'
    mobileLessonOpen.value = false
    slotPick.value = null
  }
  syncPanelForViewport()
})
</script>

<style scoped>
.diary {
  /* Fill the viewport below app chrome; calendar grows with it. */
  min-height: max(calc(520px + 5rem), calc(100dvh - 5.5rem));
}

.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-width: 0;
  flex-shrink: 0;
}

.toolbar__left,
.toolbar__right {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.toolbar__left {
  flex: 1 1 auto;
  flex-wrap: wrap;
}

.toolbar__right {
  flex: 0 1 auto;
  justify-content: flex-end;
  flex-wrap: wrap;
}

.toolbar__nav {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.toolbar__label {
  margin: 0;
  min-width: 0;
  max-width: 14rem;
  text-align: center;
  font-size: var(--text-body-sm);
  font-weight: 600;
  color: var(--color-ink-black);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.toolbar__today:disabled {
  opacity: 0.45;
}

.toolbar__book {
  flex-shrink: 0;
}

.week-summary {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.type-filter {
  position: relative;
  flex-shrink: 0;
}

.type-filter__trigger {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 36px;
  padding: 8px 12px 8px 8px;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  background: var(--color-paper-white);
  color: var(--color-ink-black);
  font: inherit;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}

.type-filter__trigger:hover {
  border-color: var(--color-border-strong);
}

.type-filter__dots {
  display: inline-flex;
  align-items: center;
  padding-left: 2px;
}

.type-filter__dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  border: 1.5px solid var(--color-paper-white);
  margin-left: -4px;
  box-shadow: 0 0 0 1px color-mix(in srgb, var(--color-ink-black) 8%, transparent);
}

.type-filter__dot:first-child {
  margin-left: 0;
}

.type-filter__dot[data-tone='paid'],
.type-filter__swatch[data-tone='paid'] {
  background: var(--color-diary-paid);
}

.type-filter__dot[data-tone='unpaid'],
.type-filter__swatch[data-tone='unpaid'] {
  background: var(--color-diary-unpaid);
}

.type-filter__dot[data-tone='package'],
.type-filter__swatch[data-tone='package'] {
  background: var(--color-diary-block);
}

.type-filter__dot[data-tone='break'],
.type-filter__swatch[data-tone='break'] {
  background: var(--color-diary-break);
}

.type-filter__dot[data-tone='offer'],
.type-filter__swatch[data-tone='offer'] {
  background: var(--color-diary-offer);
  box-shadow: inset 0 0 0 1px dashed var(--color-diary-offer-ink);
}

.type-filter__label {
  white-space: nowrap;
}

.type-filter__menu {
  position: absolute;
  top: calc(100% + 6px);
  left: auto;
  right: 0;
  z-index: 20;
  min-width: 200px;
  max-width: min(280px, calc(100vw - 24px));
  padding: 6px;
  border: 1px solid var(--color-border);
  border-radius: 12px;
  background: var(--color-paper-white);
  box-shadow: 0 12px 28px rgba(32, 21, 21, 0.12);
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.type-filter__mode {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 6px 6px 10px;
  margin-bottom: 4px;
  border-bottom: 1px solid var(--color-border);
}

.type-filter__mode-label {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-muted);
}

.type-filter__mode-seg {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4px;
  padding: 3px;
  border-radius: 999px;
  background: var(--color-parchment);
}

.type-filter__mode-btn {
  min-height: 30px;
  padding: 4px 10px;
  border: none;
  border-radius: 999px;
  background: transparent;
  color: var(--color-bark);
  font: inherit;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}

.type-filter__mode-btn--on {
  background: var(--color-paper-white);
  color: var(--color-ink-black);
  box-shadow: 0 0 0 1px color-mix(in srgb, var(--color-ink-black) 8%, transparent);
}

.type-filter__empty {
  margin: 0;
  padding: 8px 10px 6px;
  font-size: 12px;
  line-height: 1.35;
  color: var(--color-muted);
}

.type-filter__option {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 8px 10px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: var(--color-ink-black);
  font: inherit;
  font-size: var(--text-body-sm);
  text-align: left;
  cursor: pointer;
}

.type-filter__option:hover,
.type-filter__option[aria-selected='true'] {
  background: var(--color-parchment);
}

.type-filter__swatch {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  flex-shrink: 0;
}

.type-filter__swatch[data-tone='all'] {
  background:
    conic-gradient(
      var(--color-diary-paid) 0 90deg,
      var(--color-diary-unpaid) 90deg 180deg,
      var(--color-diary-block) 180deg 270deg,
      var(--color-diary-break) 270deg 360deg
    );
}

.type-filter__swatch[data-tone='all-areas'] {
  background:
    conic-gradient(
      var(--color-diary-area-0) 0 90deg,
      var(--color-diary-area-2) 90deg 180deg,
      var(--color-diary-area-4) 180deg 270deg,
      var(--color-diary-area-6) 270deg 360deg
    );
}

.type-filter__swatch[data-tone='break'] {
  border-radius: 999px;
}

.type-filter__dot[data-area-slot='0'],
.type-filter__swatch[data-area-slot='0'] {
  background: var(--color-diary-area-0);
}
.type-filter__dot[data-area-slot='1'],
.type-filter__swatch[data-area-slot='1'] {
  background: var(--color-diary-area-1);
}
.type-filter__dot[data-area-slot='2'],
.type-filter__swatch[data-area-slot='2'] {
  background: var(--color-diary-area-2);
}
.type-filter__dot[data-area-slot='3'],
.type-filter__swatch[data-area-slot='3'] {
  background: var(--color-diary-area-3);
}
.type-filter__dot[data-area-slot='4'],
.type-filter__swatch[data-area-slot='4'] {
  background: var(--color-diary-area-4);
}
.type-filter__dot[data-area-slot='5'],
.type-filter__swatch[data-area-slot='5'] {
  background: var(--color-diary-area-5);
}
.type-filter__dot[data-area-slot='6'],
.type-filter__swatch[data-area-slot='6'] {
  background: var(--color-diary-area-6);
}
.type-filter__dot[data-area-slot='7'],
.type-filter__swatch[data-area-slot='7'] {
  background: var(--color-diary-area-7);
}

.slot-choice {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: grid;
  place-items: end center;
  padding: 16px;
}

.slot-choice__backdrop {
  position: absolute;
  inset: 0;
  border: none;
  background: rgba(32, 21, 21, 0.28);
  cursor: pointer;
}

.slot-choice__panel {
  position: relative;
  width: min(100%, 360px);
  margin-bottom: max(8px, env(safe-area-inset-bottom));
  padding: 18px 16px 16px;
  border-radius: 16px;
  background: var(--color-paper-white, #fffefb);
  border: 1px solid var(--color-border, #ececec);
  box-shadow: 0 12px 40px rgba(32, 21, 21, 0.14);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.slot-choice__title {
  margin: 0;
  font-size: var(--text-body);
  font-weight: 650;
}

.slot-choice__meta {
  margin: -6px 0 0;
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.slot-choice__actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.slot-choice__label {
  margin: 0;
}

@media (min-width: 720px) {
  .slot-choice {
    place-items: center;
  }

  .slot-choice__panel {
    margin-bottom: 0;
  }
}

.strip {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 0;
  padding: 4px 0 8px;
}

.strip__day {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 6px 2px;
  border: none;
  border-radius: 0;
  background: transparent;
  min-height: 48px;
}

.strip__day[data-on='yes'] .strip__dom:not(.strip__dom--today) {
  background: var(--surface-wash);
}

.strip__dow {
  font-size: 11px;
  color: var(--color-muted);
  text-transform: none;
  font-weight: 500;
}

.strip__dom {
  font-size: 17px;
  font-variant-numeric: tabular-nums;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--color-ink-black);
}

.strip__day[data-today='yes'] .strip__dom {
  background: var(--color-ownlane-green);
  color: white;
  font-weight: 500;
  font-size: 15px;
}

.grid-wrap {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 0;
  width: 100%;
  flex: 1 1 auto;
  min-height: 0;
}

.grid-wrap__main {
  display: flex;
  flex-direction: column;
  gap: 0;
  min-width: 0;
  width: 100%;
  flex: 1 1 auto;
  min-height: 520px;
}

@media (min-width: 900px) {
  .grid-wrap__main {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 0fr;
    gap: 0;
    align-items: stretch;
    transition:
      grid-template-columns var(--duration-panel) var(--ease-out),
      border-color var(--duration-panel) var(--ease-out),
      background-color var(--duration-panel) ease;
  }

  .grid-wrap__main--panel {
    grid-template-columns: minmax(0, 1fr) minmax(280px, 34%);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-cards);
    overflow: hidden;
    background: var(--color-paper-white);
    height: max(520px, calc(100dvh - 8rem));
    min-height: 520px;
  }

  .grid-wrap__main--panel .grid-wrap__calendar {
    height: 100%;
    min-height: 0;
    border: none;
    border-radius: 0;
    border-right: 1px solid var(--color-border);
  }

  .grid-wrap__main--panel .month {
    height: 100%;
    min-height: 0;
    overflow: auto;
    border: none;
    border-radius: 0;
    border-right: 1px solid var(--color-border);
  }

  .grid-wrap__side {
    min-width: 0;
    min-height: 0;
    height: 100%;
    overflow: hidden;
    background: var(--color-parchment);
  }

  .grid-wrap__side :deep(.panel) {
    height: 100%;
  }

  .diary-side-enter-active,
  .diary-side-leave-active {
    transition:
      opacity var(--duration-panel) var(--ease-out),
      transform var(--duration-panel) var(--ease-out);
  }

  .diary-side-enter-from,
  .diary-side-leave-to {
    opacity: 0;
    transform: translateX(10px);
  }

  .grid-wrap__calendar,
  .grid-wrap__main > .month {
    transition:
      border-radius var(--duration-panel) var(--ease-out),
      border-color var(--duration-panel) ease;
  }
}

.grid-wrap__calendar {
  min-width: 0;
  width: 100%;
  flex: 1 1 auto;
  height: max(520px, calc(100dvh - 11.5rem));
  min-height: 520px;
  background: var(--color-paper-white);
  border-radius: var(--radius-cards);
  overflow: hidden;
  border: 1px solid var(--color-border);
  display: flex;
  flex-direction: column;
}

.grid-wrap__calendar :deep(.tg) {
  flex: 1;
  min-height: 0;
}

.diary__hint {
  display: none;
}

.month {
  display: flex;
  flex-direction: column;
  gap: 8px;
  background: var(--surface-card);
  border: 1px solid var(--color-border);
  border-radius: 16px;
  padding: 12px;
}

.month__dows {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
  font-size: 11px;
  color: var(--color-muted);
  text-align: center;
  text-transform: none;
  letter-spacing: 0;
  font-weight: 500;
}

.month__grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}

.month__cell {
  min-height: 84px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  padding: 8px;
  border: none;
  border-radius: 12px;
  background: var(--surface-wash);
  text-align: left;
  transition: background-color var(--duration-fast) ease;
}

.month__cell:hover {
  background: var(--surface-inset);
}

.month__cell[data-out='yes'] {
  opacity: 0.4;
}

.month__cell[data-today='yes'] {
  background: var(--color-parchment);
  border: 1px solid var(--color-driftwood);
  padding: 7px;
}

.month__num {
  font-size: 13px;
  font-variant-numeric: tabular-nums;
}

.month__count {
  font-size: 11px;
  color: var(--color-muted);
}

.month__dots {
  display: flex;
  flex-wrap: wrap;
  gap: 3px;
  margin-top: auto;
}

.month__dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--color-ownlane-green);
}

.month__dot[data-status='cancelled'] {
  background: #b0b0b5;
}

.month__dot[data-status='private'] {
  background: var(--color-diary-break);
}

.month__dot[data-overlap='yes'] {
  background: var(--color-danger);
}

.month__tag {
  font-size: 10px;
  color: var(--color-ownlane-green);
}

.month__tag--warn {
  color: var(--color-danger);
}

.month__tag--muted {
  color: var(--color-muted);
}

.overview-sheet {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.overview-sheet-enter-active,
.overview-sheet-leave-active {
  transition: opacity var(--duration-panel) var(--ease-out);
}

.overview-sheet-enter-active .overview-sheet__panel,
.overview-sheet-leave-active .overview-sheet__panel {
  transition: transform var(--duration-panel) var(--ease-out);
}

.overview-sheet-enter-from,
.overview-sheet-leave-to {
  opacity: 0;
}

.overview-sheet-enter-from .overview-sheet__panel,
.overview-sheet-leave-to .overview-sheet__panel {
  transform: translateY(16px);
}

.overview-sheet__backdrop {
  position: absolute;
  inset: 0;
  border: none;
  background: color-mix(in srgb, var(--color-ink-black) 36%, transparent);
  cursor: pointer;
}

.overview-sheet__panel {
  position: relative;
  z-index: 1;
  width: min(100%, 520px);
  max-height: min(88vh, 720px);
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px 16px 56px;
  border-radius: 20px 20px 0 0;
  background: var(--color-parchment);
  box-shadow: 0 -8px 32px color-mix(in srgb, var(--color-ink-black) 12%, transparent);
}

.overview-sheet__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-shrink: 0;
}

.overview-sheet__head-spacer {
  flex: 1;
}

.overview-sheet__eyebrow {
  margin: 0;
  font-size: 10px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  font-weight: 600;
  color: var(--color-muted);
}

.overview-sheet__x {
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
  margin-left: auto;
}

.overview-sheet__body {
  overflow: auto;
  min-height: 0;
  padding-bottom: 8px;
}

.overview-sheet__panel--lesson {
  max-height: min(92vh, 820px);
  padding-bottom: 0;
  min-height: min(72vh, 640px);
}

.overview-sheet__body--lesson {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding-bottom: 0;
}

.overview-sheet__flash {
  margin: 0;
  padding: 8px 10px;
  border-radius: 8px;
  background: color-mix(in srgb, var(--color-ownlane-green) 12%, white);
  color: var(--color-ownlane-green);
  font-size: var(--text-body-sm);
  font-weight: 600;
}

.diary__pick-banner {
  margin: 0 0 10px;
  padding: 10px 12px;
  border-radius: 10px;
  background: color-mix(in srgb, var(--color-ownlane-green) 10%, var(--color-paper-white));
  border: 1px dashed color-mix(in srgb, var(--color-ownlane-green) 35%, var(--color-border));
  font-size: var(--text-body-sm);
  color: var(--color-bark);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.diary__pick-cancel {
  border: none;
  background: transparent;
  color: var(--color-ownlane-green);
  font-weight: 600;
  cursor: pointer;
  font-size: var(--text-body-sm);
}

@media (max-width: 899px) {
  .toolbar {
    flex-wrap: wrap;
  }

  .toolbar__left,
  .toolbar__right {
    flex-wrap: wrap;
  }

  .toolbar__right {
    width: 100%;
    justify-content: flex-start;
  }

  .toolbar__label {
    max-width: 10rem;
  }

  .toolbar__book {
    margin-left: auto;
  }

  .month__cell {
    min-height: 68px;
    padding: 6px;
  }
}

@media (min-width: 900px) {
  .strip {
    display: none;
  }
}
</style>
