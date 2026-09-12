<script setup lang="ts">
import type { PupilListAttention, PupilStatusCounts } from '~/composables/usePupils'

const props = defineProps<{
  counts: PupilStatusCounts
  attention?: PupilListAttention | null
}>()

type TrackerTab = 'teaching' | 'waiting' | 'finished'

type TrackerCell = {
  key: string
  count: number
  label: string
  to: string
}

const tab = ref<TrackerTab>('teaching')

const tabs = computed(() => {
  const c = props.counts
  return [
    { id: 'teaching' as const, label: 'Teaching', count: c.active + c.paused },
    { id: 'waiting' as const, label: 'Waiting', count: c.waiting },
    { id: 'finished' as const, label: 'Finished', count: c.passed + c.inactive },
  ]
})

const cells = computed((): TrackerCell[] => {
  const c = props.counts
  const attention = props.attention
  if (tab.value === 'waiting') {
    return [
      { key: 'waiting', count: c.waiting, label: 'On waitlist', to: '/pupils?status=waiting' },
    ]
  }
  if (tab.value === 'finished') {
    return [
      { key: 'passed', count: c.passed, label: 'Passed', to: '/pupils?status=passed' },
      { key: 'inactive', count: c.inactive, label: 'Inactive', to: '/pupils?status=inactive' },
    ]
  }
  return [
    { key: 'active', count: c.active, label: 'Active', to: '/pupils?status=active' },
    { key: 'paused', count: c.paused, label: 'Paused', to: '/pupils?status=paused' },
    {
      key: 'booking',
      count: attention?.no_future_booking_count ?? 0,
      label: 'Need a lesson booked',
      to: '/pupils?status=active',
    },
    {
      key: 'tests',
      count: attention?.tests_soon_count ?? 0,
      label: 'Test soon',
      to: '/pupils?status=active',
    },
  ]
})
</script>

<template>
  <section class="tracker" aria-labelledby="tracker-title">
    <header class="tracker__head">
      <div class="tracker__title-row">
        <span class="tracker__mark" aria-hidden="true">
          <OlIcon name="pupils" :size="14" />
        </span>
        <h2 id="tracker-title" class="tracker__title">Pupils</h2>
      </div>
      <NuxtLink to="/pupils" class="ol-btn ol-btn--ghost ol-btn--sm">View all</NuxtLink>
    </header>

    <div class="tracker__tabs" role="tablist" aria-label="Pupil groups">
      <button
        v-for="item in tabs"
        :key="item.id"
        type="button"
        class="tracker__tab"
        role="tab"
        :aria-selected="tab === item.id"
        :class="{ 'tracker__tab--on': tab === item.id }"
        @click="tab = item.id"
      >
        {{ item.label }} ({{ item.count }})
      </button>
    </div>

    <div class="tracker__grid" role="tabpanel">
      <NuxtLink
        v-for="cell in cells"
        :key="cell.key"
        :to="cell.to"
        class="tracker__cell"
        :data-hot="cell.count > 0 ? 'yes' : 'no'"
      >
        <span class="tracker__count">{{ cell.count }}</span>
        <span class="tracker__label">{{ cell.label }}</span>
      </NuxtLink>
    </div>
  </section>
</template>

<style scoped>
.tracker {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px;
  border-radius: 20px;
  background: var(--color-paper-white);
  border: 1px solid var(--color-border);
}

.tracker__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.tracker__title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.tracker__mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: color-mix(in srgb, var(--color-ownlane-green) 12%, var(--color-parchment));
  color: var(--color-ownlane-green);
}

.tracker__title {
  margin: 0;
  font-size: 17px;
  font-weight: 650;
  color: var(--color-ink-black);
}

.tracker__tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 16px;
  border-bottom: 1px solid var(--color-border);
}

.tracker__tab {
  appearance: none;
  border: none;
  background: none;
  padding: 10px 2px 12px;
  margin: 0;
  font: inherit;
  font-size: 14px;
  font-weight: 500;
  color: var(--color-muted);
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
}

.tracker__tab--on {
  color: var(--color-ink-black);
  font-weight: 650;
  border-bottom-color: var(--color-ink-black);
}

.tracker__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

@media (min-width: 640px) {
  .tracker__grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

.tracker__cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 88px;
  padding: 16px 12px;
  border-radius: 14px;
  background: var(--color-parchment);
  text-decoration: none;
  text-align: center;
  color: inherit;
}

.tracker__count {
  font-size: 28px;
  font-weight: 650;
  line-height: 1;
  letter-spacing: -0.02em;
  color: var(--color-muted);
}

.tracker__cell[data-hot='yes'] .tracker__count {
  color: var(--color-ownlane-green);
}

.tracker__label {
  font-size: 13px;
  color: var(--color-muted);
  line-height: 1.25;
}

.tracker__cell[data-hot='yes'] .tracker__label {
  color: var(--color-ink-black);
}
</style>
