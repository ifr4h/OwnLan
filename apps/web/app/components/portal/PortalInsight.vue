<template>
  <p class="insight">
    <span class="insight__mark" aria-hidden="true" />
    <span class="insight__text">{{ text }}</span>
  </p>
</template>

<script setup lang="ts">
defineProps<{ text: string }>()
</script>

<style scoped>
.insight {
  display: flex;
  align-items: flex-start;
  gap: var(--spacing-12);
  margin: 0;
  font-size: var(--text-body-sm);
  line-height: var(--leading-body-sm);
  color: var(--color-ink-black);
}

.insight__mark {
  flex-shrink: 0;
  width: 8px;
  height: 8px;
  margin-top: 7px;
  border-radius: 999px;
  background: var(--color-ownlane-green);
}

.insight__text {
  flex: 1;
  min-width: 0;
}
</style>
