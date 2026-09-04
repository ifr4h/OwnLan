<template>
  <aside v-if="visible" class="guide" :aria-label="ariaLabel">
    <div class="guide__main">
      <p v-if="eyebrow" class="ol-eyebrow">{{ eyebrow }}</p>
      <h2 class="guide__title">{{ title }}</h2>
      <p class="guide__copy ol-meta">{{ copy }}</p>
      <div v-if="$slots.default || primaryLabel || secondaryLabel || dismissLabel" class="ol-actions guide__actions">
        <slot />
        <NuxtLink v-if="primaryTo && primaryLabel" :to="primaryTo" class="ol-btn ol-btn--sm">
          {{ primaryLabel }}
          <span aria-hidden="true">→</span>
        </NuxtLink>
        <NuxtLink
          v-if="secondaryTo && secondaryLabel"
          :to="secondaryTo"
          class="ol-btn ol-btn--ghost ol-btn--sm"
        >
          {{ secondaryLabel }}
        </NuxtLink>
        <button
          v-if="dismissLabel"
          class="ol-btn ol-btn--ghost ol-btn--sm"
          type="button"
          @click="$emit('dismiss')"
        >
          {{ dismissLabel }}
        </button>
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  visible?: boolean
  eyebrow?: string
  title: string
  copy: string
  primaryTo?: string
  primaryLabel?: string
  secondaryTo?: string
  secondaryLabel?: string
  dismissLabel?: string
  ariaLabel?: string
}>(), {
  visible: true,
  eyebrow: '',
  primaryTo: undefined,
  primaryLabel: undefined,
  secondaryTo: undefined,
  secondaryLabel: undefined,
  dismissLabel: undefined,
  ariaLabel: 'Getting started',
})

defineEmits<{ dismiss: [] }>()
</script>

<style scoped>
.guide {
  background: var(--surface-card);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-cards);
  box-shadow: var(--shadow-card);
  padding: var(--card-padding);
}

.guide__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-subheading);
  line-height: var(--leading-subheading);
  margin-top: 4px;
}

.guide__copy {
  margin-top: 6px;
  max-width: 42ch;
}

.guide__actions {
  margin-top: 12px;
}
</style>
