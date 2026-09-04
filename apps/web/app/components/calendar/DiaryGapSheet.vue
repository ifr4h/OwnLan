<script setup lang="ts">
import type { DiaryGap, GapMatch } from '~/composables/useLessons'

defineProps<{
  gap: DiaryGap | null
  open: boolean
}>()

const emit = defineEmits<{
  close: []
}>()

function bookMatchHref(match: GapMatch): string {
  const q = new URLSearchParams({
    learner_id: String(match.learner_id),
    starts_at_local: match.suggested_starts_at_local,
    duration_minutes: String(match.suggested_duration_minutes),
  })
  return `/lessons/new?${q.toString()}`
}
</script>

<template>
  <Teleport to="body">
    <div v-if="open && gap" class="sheet" role="dialog" aria-modal="true" :aria-label="`${gap.label} gap`">
      <button class="sheet__backdrop" type="button" aria-label="Close" @click="emit('close')" />
      <div class="sheet__panel">
        <header class="sheet__head">
          <div>
            <p class="sheet__title">{{ gap.label }} free</p>
            <p class="sheet__meta">{{ gap.starts_at_display }}–{{ gap.ends_at_display }}</p>
          </div>
          <button class="ol-btn ol-btn--icon" type="button" aria-label="Close" @click="emit('close')">
            <OlIcon name="close" :size="18" />
          </button>
        </header>

        <p v-if="!gap.matches.length" class="sheet__empty">
          No pupils match this gap yet. You can still book a lesson in this slot.
        </p>

        <ul v-else class="sheet__list">
          <li v-for="match in gap.matches" :key="match.learner_id" class="sheet__row">
            <div>
              <p class="sheet__name">{{ match.learner_name }}</p>
              <p class="sheet__reasons">{{ match.reasons.join(' · ') }}</p>
            </div>
            <NuxtLink class="ol-btn ol-btn--sm" :to="bookMatchHref(match)" @click="emit('close')">
              Book
            </NuxtLink>
          </li>
        </ul>

        <NuxtLink
          class="ol-btn ol-btn--ghost ol-btn--sm sheet__book"
          :to="`/lessons/new?date=${gap.date}&starts_at_local=${encodeURIComponent(gap.starts_at_local)}&duration_minutes=${gap.duration_minutes}`"
          @click="emit('close')"
        >
          Book without suggestion
        </NuxtLink>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.sheet {
  position: fixed;
  inset: 0;
  z-index: 200;
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.sheet__backdrop {
  position: absolute;
  inset: 0;
  border: none;
  background: rgba(17, 17, 24, 0.4);
}

.sheet__panel {
  position: relative;
  width: 100%;
  max-width: 520px;
  max-height: 80vh;
  overflow: auto;
  padding: var(--spacing-16);
  border-radius: var(--radius-panel) var(--radius-panel) 0 0;
  background: var(--surface-card);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.sheet__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: var(--spacing-12);
}

.sheet__title {
  margin: 0;
  font-size: var(--text-body);
  font-weight: 600;
}

.sheet__meta {
  margin: 4px 0 0;
  font-size: var(--text-body-sm);
  color: var(--color-muted);
  font-variant-numeric: tabular-nums;
}

.sheet__empty {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.sheet__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.sheet__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 0;
  border-top: 1px solid var(--color-border);
}

.sheet__name {
  margin: 0;
  font-weight: 500;
  font-size: var(--text-body-sm);
}

.sheet__reasons {
  margin: 2px 0 0;
  font-size: var(--text-caption);
  color: var(--color-muted);
}

.sheet__book {
  align-self: flex-start;
}

@media (min-width: 900px) {
  .sheet {
    align-items: center;
  }

  .sheet__panel {
    border-radius: var(--radius-panel);
    margin: var(--spacing-16);
  }
}
</style>
