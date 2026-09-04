<template>
  <ul class="bars" role="list">
    <li v-for="item in items" :key="item.skill_id">
      <button
        type="button"
        class="bars__row"
        :aria-label="`${item.label}: ${item.lesson_count} lessons`"
        @click="emit('select', item)"
      >
        <span class="bars__meta">
          <span class="bars__label">{{ item.label }}</span>
          <span class="bars__count">{{ item.lesson_count }}</span>
        </span>
        <span class="bars__track" aria-hidden="true">
          <span
            class="bars__fill"
            :style="{ width: `${pct(item.lesson_count)}%` }"
          />
        </span>
        <span class="bars__cat">{{ item.category_label }}</span>
      </button>
    </li>
  </ul>
</template>

<script setup lang="ts">
import type { PortalPractisedCount } from '~/composables/usePortal'

const props = defineProps<{
  items: PortalPractisedCount[]
}>()

const emit = defineEmits<{
  select: [item: PortalPractisedCount]
}>()

const max = computed(() =>
  Math.max(1, ...props.items.map(i => i.lesson_count)),
)

function pct(count: number): number {
  return Math.round((count / max.value) * 100)
}
</script>

<style scoped>
.bars {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.bars__row {
  display: flex;
  flex-direction: column;
  gap: 4px;
  width: 100%;
  text-align: left;
  border: none;
  background: transparent;
  padding: var(--spacing-8) 0;
  min-height: 44px;
  cursor: pointer;
  font: inherit;
  color: inherit;
  border-radius: var(--radius-small);
}

.bars__row:focus-visible {
  outline: 2px solid var(--color-ownlane-green);
  outline-offset: 2px;
}

.bars__meta {
  display: flex;
  justify-content: space-between;
  gap: var(--spacing-12);
  font-size: var(--text-body-sm);
}

.bars__count {
  font-variant-numeric: tabular-nums;
  color: var(--color-muted);
}

.bars__track {
  display: block;
  height: 8px;
  border-radius: 999px;
  background: var(--color-frost-green);
  overflow: hidden;
}

.bars__fill {
  display: block;
  height: 100%;
  border-radius: 999px;
  background: var(--color-ownlane-green);
  min-width: 4px;
  transition: width var(--duration-med) var(--ease-out);
}

@media (prefers-reduced-motion: reduce) {
  .bars__fill {
    transition: none;
  }
}

.bars__cat {
  font-size: var(--text-meta);
  color: var(--color-muted);
}
</style>
