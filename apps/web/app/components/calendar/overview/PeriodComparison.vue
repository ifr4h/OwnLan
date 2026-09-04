<script setup lang="ts">
import type { PeriodComparison } from '~/utils/diary/overviewModel'
import { formatOverviewHours } from '~/utils/diary/overviewModel'

const props = defineProps<{
  comparison: PeriodComparison
}>()

const max = computed(() =>
  Math.max(1, props.comparison.currentMinutes, props.comparison.priorMinutes),
)

const deltaLabel = computed(() => {
  const d = props.comparison.deltaMinutes
  if (d === 0) return 'Same teaching time'
  const abs = formatOverviewHours(Math.abs(d))
  return d > 0 ? `${abs} more` : `${abs} less`
})
</script>

<template>
  <div class="compare">
    <div class="compare__row">
      <div class="compare__meta">
        <span class="compare__name">{{ comparison.currentLabel }}</span>
        <span class="compare__hours">{{ formatOverviewHours(comparison.currentMinutes) }}</span>
      </div>
      <span class="compare__track">
        <span
          class="compare__fill compare__fill--current"
          :style="{ width: `${(comparison.currentMinutes / max) * 100}%` }"
        />
      </span>
    </div>
    <div class="compare__row">
      <div class="compare__meta">
        <span class="compare__name">{{ comparison.priorLabel }}</span>
        <span class="compare__hours">{{ formatOverviewHours(comparison.priorMinutes) }}</span>
      </div>
      <span class="compare__track">
        <span
          class="compare__fill"
          :style="{ width: `${(comparison.priorMinutes / max) * 100}%` }"
        />
      </span>
    </div>
    <p class="compare__delta">
      {{ deltaLabel }}
      <span v-if="comparison.likeForLikeNote" class="compare__note">
        · {{ comparison.likeForLikeNote }}
      </span>
    </p>
  </div>
</template>

<style scoped>
.compare {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 0;
}

.compare__row {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.compare__meta {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  font-size: var(--text-body-sm);
}

.compare__name {
  color: var(--color-bark);
}

.compare__hours {
  color: var(--color-ink-black);
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.compare__track {
  height: 8px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--color-border) 55%, transparent);
  overflow: hidden;
}

.compare__fill {
  display: block;
  height: 100%;
  border-radius: 999px;
  background: color-mix(in srgb, var(--color-bark) 18%, var(--color-parchment));
}

.compare__fill--current {
  background: color-mix(in srgb, var(--color-ownlane-green) 55%, var(--color-soft-sage));
}

.compare__delta {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.compare__note {
  color: var(--color-ash-mist);
}
</style>
