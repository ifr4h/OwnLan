<template>
  <div
    class="ring"
    role="img"
    :aria-label="ariaLabel"
  >
    <svg class="ring__svg" viewBox="0 0 120 120" aria-hidden="true">
      <circle
        class="ring__track"
        cx="60"
        cy="60"
        :r="radius"
        fill="none"
        :stroke-width="stroke"
      />
      <circle
        class="ring__progress"
        cx="60"
        cy="60"
        :r="radius"
        fill="none"
        :stroke-width="stroke"
        :stroke-dasharray="circumference"
        :stroke-dashoffset="offset"
        stroke-linecap="round"
      />
    </svg>
    <div class="ring__center">
      <p class="ring__value">{{ remainingLabel }}</p>
      <p class="ring__sub">{{ remainingHint }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    usedPercent: number
    remainingLabel: string
    remainingHint?: string
    ariaLabel?: string
  }>(),
  {
    remainingHint: 'remaining',
    ariaLabel: undefined,
  },
)

const stroke = 10
const radius = 48
const circumference = 2 * Math.PI * radius

const clamped = computed(() => Math.min(100, Math.max(0, props.usedPercent)))
const offset = computed(() => circumference * (1 - clamped.value / 100))

const ariaLabel = computed(
  () =>
    props.ariaLabel
    || `${props.remainingLabel} ${props.remainingHint}. ${clamped.value}% used.`,
)
</script>

<style scoped>
.ring {
  position: relative;
  width: 160px;
  height: 160px;
  margin: 0 auto;
}

.ring__svg {
  width: 100%;
  height: 100%;
  transform: rotate(-90deg);
}

.ring__track {
  stroke: var(--color-frost-green);
}

.ring__progress {
  stroke: var(--color-ownlane-green);
  transition: stroke-dashoffset 0.6s var(--ease-out);
}

@media (prefers-reduced-motion: reduce) {
  .ring__progress {
    transition: none;
  }
}

.ring__center {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: var(--spacing-16);
}

.ring__value {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
  line-height: 1.15;
  letter-spacing: -0.02em;
}

.ring__sub {
  font-size: var(--text-meta);
  color: var(--color-muted);
  margin-top: 2px;
}
</style>
