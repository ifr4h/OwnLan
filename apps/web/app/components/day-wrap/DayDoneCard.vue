<template>
  <article class="day-done ol-card" :data-complete="complete ? 'yes' : 'no'" aria-label="Day status">
    <p class="ol-badge day-done__badge" :class="complete ? 'ol-badge--success' : 'ol-badge--warning'">
      <OlIcon v-if="complete" name="confetti" :size="14" />
      {{ complete ? 'Day complete' : 'Still going' }}
    </p>
    <h2 class="day-done__title">{{ title }}</h2>
    <p v-if="subtitle" class="day-done__subtitle ol-meta">{{ subtitle }}</p>

    <div v-if="actions.length" class="day-done__actions ol-actions">
      <NuxtLink
        v-for="action in actions"
        :key="action.path + action.label"
        :to="action.path"
        class="ol-btn"
        :class="action.primary ? '' : 'ol-btn--ghost'"
      >
        {{ action.label }}
        <span v-if="action.primary" aria-hidden="true">→</span>
      </NuxtLink>
    </div>
  </article>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  title: string
  subtitle?: string | null
  complete?: boolean
  actions?: Array<{ label: string; path: string; primary?: boolean }>
}>(), {
  subtitle: null,
  complete: false,
  actions: () => [],
})
</script>

<style scoped>
.day-done {
  display: flex;
  flex-direction: column;
  gap: 8px;
  background: var(--color-success-wash);
  border-color: transparent;
  box-shadow: none;
}

.day-done[data-complete='no'] {
  background: var(--color-warning-wash);
}

.day-done__title {
  margin: 0;
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
  color: var(--color-ink-black);
}

.day-done__badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  width: fit-content;
}

.day-done__subtitle {
  margin: 0;
}

.day-done__actions {
  margin-top: 4px;
}
</style>
