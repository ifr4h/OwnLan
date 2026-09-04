<script setup lang="ts">
import type { DiaryLesson } from '~/composables/useLessons'
import {
  contentTierForHeight,
  formatDuration,
  lessonAriaLabel,
  shortPlace,
} from '~/utils/calendar/timeGrid'

export type LessonTone = 'paid' | 'unpaid' | 'package' | 'scheduled' | 'other'

const props = defineProps<{
  lesson: DiaryLesson
  compact?: boolean
  blockHeight?: number
  dimmed?: boolean
  selectMode?: boolean
  styleInline?: Record<string, string>
}>()

const emit = defineEmits<{
  select: [lesson: DiaryLesson]
}>()

const place = computed(() => shortPlace(props.lesson.pickup_address))

const tier = computed(() => {
  const h = props.blockHeight ?? 40
  return contentTierForHeight(h)
})

const tone = computed<LessonTone>(() => {
  if (props.lesson.status === 'cancelled' || props.lesson.status === 'no_show') return 'other'
  const s = props.lesson.settlement
  if (s === 'outstanding') return 'unpaid'
  if (s === 'package') return 'package'
  if (s === 'paid') return 'paid'
  return 'scheduled'
})

const paymentLabel = computed(() => {
  if (props.lesson.status === 'no_show' && props.lesson.settlement === 'waived') return 'No charge'
  if (props.lesson.status !== 'completed' && props.lesson.status !== 'scheduled' && props.lesson.status !== 'no_show') return null
  const s = props.lesson.settlement
  if (!s) return null
  if (s === 'paid') return 'Paid'
  if (s === 'package') return 'Package'
  if (s === 'outstanding') {
    if (props.lesson.price_pence) {
      const pounds = (props.lesson.price_pence / 100).toFixed(0)
      return `£${pounds} due`
    }
    return 'Unpaid'
  }
  return null
})

const showMetaRow = computed(() => tier.value !== 'minimal')
const showPlace = computed(() =>
  !props.compact && place.value && (tier.value === 'comfortable' || tier.value === 'spacious'),
)
const showPayment = computed(() =>
  paymentLabel.value && tier.value !== 'minimal' && tier.value !== 'compact',
)
const showFocus = computed(() =>
  !props.compact
  && props.lesson.learner_next_focus
  && tier.value === 'spacious',
)

const statusClass = computed(() => {
  // Avoid Array.includes — Safari was throwing props.includes is not a function
  // and blanking the diary grid after a successful load.
  let toneClass = `block--${tone.value}`
  let overlapped = false

  if (props.lesson.overlaps) {
    toneClass = 'block--overlap'
    overlapped = true
  } else if (props.lesson.status === 'cancelled') {
    toneClass = 'block--cancelled'
  } else if (props.lesson.status === 'no_show') {
    toneClass = 'block--noshow'
  }

  const parts = [toneClass]
  if (props.lesson.is_current && !overlapped && toneClass !== 'block--cancelled') {
    parts.push('block--now')
  }
  if (props.lesson.status === 'completed' && !overlapped) {
    parts.push('block--done')
  }
  return parts.join(' ')
})

const aria = computed(() => lessonAriaLabel(props.lesson))

function onActivate(e: MouseEvent) {
  if (!props.selectMode) return
  e.preventDefault()
  e.stopPropagation()
  emit('select', props.lesson)
}
</script>

<template>
  <button
    v-if="selectMode"
    type="button"
    class="block"
    :class="[statusClass, { 'block--compact': compact, 'block--dimmed': dimmed }]"
    :style="styleInline"
    :aria-label="aria"
    :title="aria"
    :tabindex="dimmed ? -1 : undefined"
    @click="onActivate"
  >
    <div class="block__top">
      <p class="block__name">{{ lesson.learner_name }}</p>
      <p v-if="showMetaRow" class="block__duration">
        {{ formatDuration(lesson.duration_minutes) }}
      </p>
    </div>
    <p v-if="showMetaRow && !compact" class="block__when">
      {{ lesson.starts_at_time }}–{{ lesson.ends_at_time }}
    </p>
    <p v-else-if="showMetaRow && compact" class="block__when">
      {{ lesson.starts_at_time }}
    </p>
    <p v-if="showPlace" class="block__place">{{ place }}</p>
    <p v-if="showPayment" class="block__payment">{{ paymentLabel }}</p>
    <p v-if="showFocus" class="block__focus">{{ lesson.learner_next_focus }}</p>
    <span v-if="lesson.overlaps" class="block__flag">Overlap</span>
    <span
      v-else-if="lesson.travel_to_next?.is_warning && tier !== 'minimal'"
      class="block__flag block__flag--warn"
      aria-hidden="true"
    >
      Tight gap
    </span>
  </button>
  <NuxtLink
    v-else
    :to="`/lessons/${lesson.id}`"
    class="block"
    :class="[statusClass, { 'block--compact': compact, 'block--dimmed': dimmed }]"
    :style="styleInline"
    :aria-label="aria"
    :title="aria"
    :tabindex="dimmed ? -1 : undefined"
  >
    <div class="block__top">
      <p class="block__name">{{ lesson.learner_name }}</p>
      <p v-if="showMetaRow" class="block__duration">
        {{ formatDuration(lesson.duration_minutes) }}
      </p>
    </div>
    <p v-if="showMetaRow && !compact" class="block__when">
      {{ lesson.starts_at_time }}–{{ lesson.ends_at_time }}
    </p>
    <p v-else-if="showMetaRow && compact" class="block__when">
      {{ lesson.starts_at_time }}
    </p>
    <p v-if="showPlace" class="block__place">{{ place }}</p>
    <p v-if="showPayment" class="block__payment">{{ paymentLabel }}</p>
    <p v-if="showFocus" class="block__focus">{{ lesson.learner_next_focus }}</p>
    <span v-if="lesson.overlaps" class="block__flag">Overlap</span>
    <span
      v-else-if="lesson.travel_to_next?.is_warning && tier !== 'minimal'"
      class="block__flag block__flag--warn"
      aria-hidden="true"
    >
      Tight gap
    </span>
  </NuxtLink>
</template>

<style scoped>
.block {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-height: 22px;
  padding: 6px 8px;
  border-radius: 10px;
  border: none;
  overflow: hidden;
  text-decoration: none;
  box-shadow: none;
  cursor: pointer;
  text-align: left;
  font: inherit;
  color: inherit;
  width: auto;
  transition: filter var(--duration-fast) ease;
}

.block:hover {
  filter: brightness(0.97);
  z-index: 4;
}

.block--dimmed {
  opacity: 0.05;
  pointer-events: none;
}

/* Settlement / lesson type tones — pastel diary stack */
.block--scheduled,
.block--paid {
  background: var(--color-diary-paid);
  color: var(--color-diary-paid-ink);
}

.block--unpaid {
  background: var(--color-diary-unpaid);
  color: var(--color-diary-unpaid-ink);
}

.block--package {
  background: var(--color-diary-block);
  color: var(--color-diary-block-ink);
}

.block--now {
  box-shadow: inset 3px 0 0 var(--color-ownlane-green);
}

.block--overlap {
  background: #fde8e8;
  color: #9b2c2c;
}

.block--done {
  opacity: 0.9;
}

.block--cancelled {
  background: #f3f3f4;
  color: #8a8a90;
  opacity: 0.75;
}

.block--cancelled .block__name {
  text-decoration: line-through;
  text-decoration-thickness: 1px;
}

.block--noshow {
  background: #faf3e8;
  color: #8a5a2b;
}

.block__top {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 6px;
  min-width: 0;
}

.block__name {
  margin: 0;
  font-size: 13px;
  line-height: 1.2;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  min-width: 0;
}

.block--compact .block__name {
  font-size: 12px;
}

.block__duration {
  margin: 0;
  flex-shrink: 0;
  font-size: 11px;
  line-height: 1.2;
  font-weight: 500;
  font-variant-numeric: tabular-nums;
  opacity: 0.72;
}

.block__when,
.block__place,
.block__payment,
.block__focus {
  margin: 0;
  font-size: 11px;
  line-height: 1.25;
  font-variant-numeric: tabular-nums;
  opacity: 0.72;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.block__payment {
  font-weight: 500;
  opacity: 0.9;
}

.block__flag {
  margin-top: 1px;
  font-size: 10px;
  line-height: 1.2;
  font-weight: 500;
  opacity: 0.85;
}

.block__flag--warn {
  color: #8a6d00;
}
</style>
