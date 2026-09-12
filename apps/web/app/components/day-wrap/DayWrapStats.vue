<template>
  <section v-if="stats.length" class="wrap-stats" :aria-label="ariaLabel">
    <article
      v-for="stat in stats"
      :key="stat.key"
      class="wrap-stats__card ol-card ol-card--flat"
      :data-tone="stat.tone"
    >
      <p class="wrap-stats__label">{{ stat.label }}</p>
      <p class="wrap-stats__value">{{ stat.value }}</p>
    </article>
  </section>
</template>

<script setup lang="ts">
import type { DayWrapStat } from '~/composables/useDayWrap'

withDefaults(defineProps<{
  stats: DayWrapStat[]
  ariaLabel?: string
}>(), {
  ariaLabel: 'Day stats',
})
</script>

<style scoped>
.wrap-stats {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.wrap-stats__card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px 14px;
  margin: 0;
  box-shadow: none;
}

.wrap-stats__label {
  margin: 0;
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.wrap-stats__value {
  margin: 0;
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
  color: var(--color-ink-black);
  font-variant-numeric: tabular-nums;
}

.wrap-stats__card[data-tone='good'] .wrap-stats__value {
  color: var(--color-ownlane-green);
}

.wrap-stats__card[data-tone='warn'] .wrap-stats__value {
  color: var(--color-bark);
}

@media (min-width: 640px) {
  .wrap-stats {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
</style>
