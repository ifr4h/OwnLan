<template>
  <div class="metric" :class="{ 'metric--compact': compact }">
    <p class="metric__value">{{ value }}</p>
    <p class="metric__label">{{ label }}</p>
    <p v-if="hint" class="metric__hint">{{ hint }}</p>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  value: string | number
  label: string
  hint?: string
  compact?: boolean
}>()
</script>

<style scoped>
.metric {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.metric__value {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-metric);
  line-height: var(--leading-metric);
  letter-spacing: var(--tracking-metric);
  font-variant-numeric: tabular-nums;
  color: var(--color-ink-black);
}

.metric--compact .metric__value {
  font-size: var(--text-heading-sm);
  letter-spacing: var(--tracking-heading-sm);
}

.metric__label {
  font-size: var(--text-meta);
  line-height: var(--leading-meta);
  letter-spacing: var(--tracking-meta);
  color: var(--color-muted);
}

.metric__hint {
  font-size: var(--text-caption-mono);
  font-family: var(--font-martian-mono);
  letter-spacing: var(--tracking-caption-mono);
  color: var(--color-muted);
  margin-top: 2px;
}
</style>
