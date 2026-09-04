<template>
  <div
    class="skel"
    :class="[`skel--${variant}`]"
    :style="styleVars"
    aria-hidden="true"
  />
</template>

<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    variant?: 'line' | 'block' | 'circle' | 'hero'
    width?: string
    height?: string
  }>(),
  {
    variant: 'line',
    width: undefined,
    height: undefined,
  },
)

const styleVars = computed(() => ({
  ...(props.width ? { width: props.width } : {}),
  ...(props.height ? { height: props.height } : {}),
}))
</script>

<style scoped>
.skel {
  background: linear-gradient(
    90deg,
    var(--color-frost-green) 0%,
    #f0f7f2 50%,
    var(--color-frost-green) 100%
  );
  background-size: 200% 100%;
  animation: shimmer 1.4s ease-in-out infinite;
  border-radius: var(--radius-small);
}

.skel--line {
  height: 14px;
  width: 100%;
  max-width: 16rem;
}

.skel--block {
  height: 88px;
  width: 100%;
  border-radius: var(--radius-panel);
}

.skel--hero {
  height: 160px;
  width: 100%;
  border-radius: 0;
}

.skel--circle {
  width: 72px;
  height: 72px;
  border-radius: 999px;
}

@media (prefers-reduced-motion: reduce) {
  .skel {
    animation: none;
    background: var(--color-frost-green);
  }
}

@keyframes shimmer {
  0% {
    background-position: 100% 0;
  }
  100% {
    background-position: -100% 0;
  }
}
</style>
