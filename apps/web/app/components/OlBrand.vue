<template>
  <span class="ol-brand" :class="{ 'ol-brand--compact': compact }">
    <span class="ol-brand__mark" aria-hidden="true">
      <svg class="ol-brand__glyph" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <!-- Abstract lane mark: position above the path ahead -->
        <circle cx="12" cy="8" r="3" stroke="currentColor" stroke-width="2" />
        <path
          d="M12 13v6"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
        />
      </svg>
    </span>
    <span v-if="showWordmark" class="ol-brand__wordmark">OwnLane</span>
  </span>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  showWordmark?: boolean
  compact?: boolean
}>(), {
  showWordmark: true,
  compact: false,
})
</script>

<style scoped>
.ol-brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: inherit;
  text-decoration: none;
}

.ol-brand__mark {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  background: var(--color-ownlane-green, #c1f48f);
  color: var(--color-ink-black, #1e2c0f);
  flex-shrink: 0;
}

.ol-brand--compact .ol-brand__mark {
  width: 24px;
  height: 24px;
  border-radius: 5px;
}

.ol-brand__glyph {
  width: 16px;
  height: 16px;
  display: block;
}

.ol-brand--compact .ol-brand__glyph {
  width: 14px;
  height: 14px;
}

.ol-brand__wordmark {
  font-family: var(--font-haas-grot-disp);
  font-size: 20px;
  letter-spacing: -0.02em;
  line-height: 1;
  color: var(--color-ink-black, #1e2c0f);
}

.ol-brand--compact .ol-brand__wordmark {
  font-size: 17px;
}
</style>
