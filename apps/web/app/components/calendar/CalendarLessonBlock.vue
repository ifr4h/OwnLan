<script setup lang="ts">
import type { DiaryLesson } from '~/composables/useLessons'
import {
  contentTierForHeight,
  formatDuration,
  lessonAriaLabel,
  shortPlace,
} from '~/utils/calendar/timeGrid'

const props = defineProps<{
  lesson: DiaryLesson
  compact?: boolean
  blockHeight?: number
  styleInline?: Record<string, string>
}>()

const place = computed(() => shortPlace(props.lesson.pickup_address))

const tier = computed(() => {
  const h = props.blockHeight ?? 40
  return contentTierForHeight(h)
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

const showTimeRange = computed(() => !props.compact && tier.value !== 'minimal')
const showDuration = computed(() =>
  tier.value === 'comfortable' || tier.value === 'spacious',
)
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
  if (props.lesson.overlaps) return 'block--overlap'
  if (props.lesson.status === 'cancelled') return 'block--cancelled'
  if (props.lesson.status === 'no_show') return 'block--noshow'
  if (props.lesson.status === 'completed') return 'block--done'
  if (props.lesson.is_current) return 'block--now'
  if (props.lesson.settlement === 'outstanding') return 'block--unpaid'
  return ''
})

const aria = computed(() => lessonAriaLabel(props.lesson))
</script>

<template>
  <NuxtLink
    :to="`/lessons/${lesson.id}`"
    class="block"
    :class="[statusClass, { 'block--compact': compact }]"
    :style="styleInline"
    :aria-label="aria"
    :title="aria"
  >
    <p class="block__name">{{ lesson.learner_name }}</p>
    <p class="block__when">
      <template v-if="showTimeRange">
        {{ lesson.starts_at_time }}–{{ lesson.ends_at_time }}
      </template>
      <template v-else>
        {{ lesson.starts_at_time }}
      </template>
    </p>
    <p v-if="showDuration" class="block__duration">
      {{ formatDuration(lesson.duration_minutes) }}
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
  gap: 1px;
  min-height: 22px;
  padding: 3px 6px;
  border-radius: 6px;
  border: 1px solid rgba(22, 139, 85, 0.32);
  border-left-width: 3px;
  background: #e8f6ee;
  color: var(--color-ink-black);
  overflow: hidden;
  box-shadow: 0 1px 0 rgba(17, 17, 24, 0.04);
  transition: filter var(--duration-fast) ease, border-color var(--duration-fast) ease;
  text-decoration: none;
}

.block:hover {
  filter: brightness(0.98);
  border-color: var(--color-ownlane-green);
  z-index: 4;
}

.block--now {
  background: var(--color-ownlane-green);
  border-color: var(--color-ownlane-green);
  color: var(--color-paper-white);
}

.block--overlap {
  background: var(--color-danger-wash);
  border-color: var(--color-danger);
  border-left-color: var(--color-danger);
}

.block--unpaid {
  border-left-color: var(--color-warning);
}

.block--done {
  background: var(--surface-wash);
  border-color: var(--color-border);
  border-left-color: #9aab9e;
  opacity: 0.88;
}

.block--cancelled {
  background: #f4f4f5;
  border-style: dashed;
  border-color: #c5c5c8;
  border-left-color: #c5c5c8;
  opacity: 0.72;
}

.block--noshow {
  background: #faf3e8;
  border-color: #d4a574;
  border-left-color: #c4864a;
  opacity: 0.9;
}

.block__name {
  font-size: 12px;
  line-height: 1.2;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.block--compact .block__name {
  font-size: 11px;
}

.block__when {
  font-size: 10px;
  line-height: 1.2;
  font-variant-numeric: tabular-nums;
  opacity: 0.8;
}

.block--now .block__when {
  opacity: 0.92;
}

.block__duration {
  font-size: 10px;
  line-height: 1.2;
  opacity: 0.75;
}

.block__place,
.block__payment,
.block__focus {
  font-size: 10px;
  line-height: 1.2;
  opacity: 0.72;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.block__payment {
  font-weight: 500;
}

.block--unpaid .block__payment {
  color: var(--color-warning);
}

.block__flag {
  margin-top: 1px;
  font-size: 9px;
  line-height: 1.2;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  opacity: 0.9;
}

.block__flag--warn {
  color: var(--color-warning);
}

.block--now .block__flag--warn {
  color: #ffe9a8;
}
</style>
