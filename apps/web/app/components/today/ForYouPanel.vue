<script setup lang="ts">
import type { NeedsYouAction } from '~/composables/useToday'

const props = defineProps<{
  actions: NeedsYouAction[]
  showIntro?: boolean
  onboardingPercent?: number | null
  onboardingItems?: { id: string; label: string; done: boolean; path?: string }[]
}>()

const emit = defineEmits<{
  dismissIntro: []
}>()

type ForYouGroup = {
  kind: string
  label: string
  count: number
  ctaLabel: string
  ctaPath: string
  icon: 'warning' | 'diary' | 'test' | 'pupils' | 'check'
}

function kindMeta(kind: string): { label: string; icon: ForYouGroup['icon'] } {
  if (kind === 'empty_seat') return { label: 'Empty seats', icon: 'diary' }
  if (kind === 'rebook') return { label: 'Need rebooking', icon: 'pupils' }
  if (kind === 'test_gap') return { label: 'Test gaps', icon: 'test' }
  if (kind === 'intake_review') return { label: 'New pupil details', icon: 'check' }
  if (kind === 'booking_request') return { label: 'Lesson requests', icon: 'diary' }
  return { label: 'Needs a look', icon: 'warning' }
}

const groups = computed((): ForYouGroup[] => {
  const map = new Map<string, NeedsYouAction[]>()
  for (const action of props.actions) {
    const list = map.get(action.kind) || []
    list.push(action)
    map.set(action.kind, list)
  }
  return [...map.entries()].map(([kind, list]) => {
    const meta = kindMeta(kind)
    const first = list[0]!
    return {
      kind,
      label: meta.label,
      count: list.length,
      ctaLabel: list.length === 1 ? (first.cta_label || 'Review') : 'Review',
      ctaPath: first.cta_path,
      icon: meta.icon,
    }
  })
})

const showPanel = computed(() =>
  groups.value.length > 0
  || (props.onboardingItems?.some(i => !i.done) ?? false),
)

const incompleteOnboarding = computed(() =>
  (props.onboardingItems || []).filter(i => !i.done),
)

const onboardingLabel = computed(() => {
  const pct = props.onboardingPercent
  if (pct == null) return null
  return `${Math.round(pct)}% done`
})
</script>

<template>
  <section v-if="showPanel" class="for-you" aria-labelledby="for-you-title">
    <header class="for-you__head">
      <div class="for-you__title-row">
        <span class="for-you__mark" aria-hidden="true">
          <OlIcon name="check" :size="14" />
        </span>
        <h2 id="for-you-title" class="for-you__title">For you today</h2>
      </div>
      <p class="for-you__sub">Things that need a decision</p>
    </header>

    <p v-if="showIntro && groups.length" class="for-you__intro">
      Only when OwnLane already knows something needs you.
      <button class="for-you__gotit" type="button" @click="emit('dismissIntro')">Got it</button>
    </p>

    <ul v-if="groups.length" class="for-you__list">
      <li v-for="group in groups" :key="group.kind" class="for-you__row">
        <div class="for-you__icon-wrap" aria-hidden="true">
          <span class="for-you__icon">
            <OlIcon :name="group.icon" :size="16" />
          </span>
          <span class="for-you__badge">{{ group.count }}</span>
        </div>
        <p class="for-you__label">{{ group.label }}</p>
        <NuxtLink :to="group.ctaPath" class="ol-btn ol-btn--ghost ol-btn--sm for-you__cta">
          {{ group.ctaLabel }}
        </NuxtLink>
      </li>
    </ul>

    <aside
      v-if="incompleteOnboarding.length && onboardingPercent != null"
      class="for-you__setup"
      aria-label="Getting started"
    >
      <div class="for-you__setup-head">
        <p class="for-you__setup-title">Finish getting started</p>
        <p class="for-you__setup-pct">{{ onboardingLabel }}</p>
      </div>
      <div
        class="for-you__bar"
        role="progressbar"
        :aria-valuenow="onboardingPercent"
        aria-valuemin="0"
        aria-valuemax="100"
      >
        <span
          class="for-you__fill"
          :style="{ width: `${Math.min(100, Math.max(0, onboardingPercent))}%` }"
        />
      </div>
      <ul class="for-you__steps">
        <li v-for="item in incompleteOnboarding" :key="item.id" class="for-you__step">
          <span class="for-you__step-icon" aria-hidden="true">
            <OlIcon name="add" :size="14" />
          </span>
          <span class="for-you__step-label">{{ item.label }}</span>
          <NuxtLink
            v-if="item.path"
            :to="item.path"
            class="ol-btn ol-btn--ghost ol-btn--sm"
          >
            Continue
          </NuxtLink>
        </li>
      </ul>
    </aside>
  </section>
</template>

<style scoped>
.for-you {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px;
  border-radius: 20px;
  background: var(--color-paper-white);
  border: 1px solid var(--color-border);
}

.for-you__head {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.for-you__title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.for-you__mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: color-mix(in srgb, var(--color-ownlane-green) 12%, var(--color-parchment));
  color: var(--color-ownlane-green);
}

.for-you__title {
  margin: 0;
  font-size: 17px;
  font-weight: 650;
  color: var(--color-ink-black);
}

.for-you__sub,
.for-you__intro {
  margin: 0;
  font-size: 13px;
  color: var(--color-muted);
}

.for-you__gotit {
  margin-left: 6px;
  border: none;
  background: none;
  padding: 0;
  font: inherit;
  color: var(--color-ownlane-green);
  font-weight: 600;
  cursor: pointer;
}

.for-you__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
}

.for-you__row {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 12px;
  padding: 12px 0;
  border-top: 1px solid var(--color-border);
}

.for-you__row:first-child {
  border-top: none;
  padding-top: 4px;
}

.for-you__icon-wrap {
  position: relative;
  width: 40px;
  height: 40px;
}

.for-you__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 999px;
  background: var(--color-parchment);
  color: var(--color-ink-black);
}

.for-you__badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 999px;
  background: var(--color-ink-black);
  color: var(--color-on-accent);
  font-size: 11px;
  font-weight: 650;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

.for-you__label {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: var(--color-ink-black);
}

.for-you__cta {
  flex-shrink: 0;
}

.for-you__setup {
  margin-top: 4px;
  padding: 16px;
  border-radius: 16px;
  background: color-mix(in srgb, var(--color-ownlane-green) 10%, var(--color-parchment));
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.for-you__setup-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
}

.for-you__setup-title {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink-black);
}

.for-you__setup-pct {
  margin: 0;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-ownlane-green);
}

.for-you__bar {
  height: 8px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--color-ownlane-green) 18%, var(--color-paper-white));
  overflow: hidden;
}

.for-you__fill {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--color-ownlane-green);
}

.for-you__steps {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.for-you__step {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 10px;
}

.for-you__step-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 999px;
  background: var(--color-paper-white);
  color: var(--color-ownlane-green);
}

.for-you__step-label {
  font-size: 14px;
  font-weight: 500;
  color: var(--color-ink-black);
}
</style>
