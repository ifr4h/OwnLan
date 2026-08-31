<template>
  <section class="empty">
    <p class="empty__eyebrow">Today</p>
    <h1 class="empty__title">Nothing scheduled yet</h1>
    <p class="empty__copy">
      Once lessons are on the diary, today’s teaching view will live here —
      current lesson, next pupil, complete in a few taps.
    </p>
  </section>
</template>

<script setup lang="ts">
useHead({ title: 'Today · OwnLane' })
</script>

<style scoped>
.empty {
  background: var(--surface-card);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-cards);
  box-shadow: var(--shadow-card);
  padding: var(--card-padding);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
  max-width: 560px;
}

.empty__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  line-height: var(--leading-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.6;
}

.empty__title {
  font-size: var(--text-heading-sm);
  line-height: var(--leading-heading-sm);
  letter-spacing: var(--tracking-heading-sm);
}

.empty__copy {
  font-size: var(--text-body-sm);
  line-height: var(--leading-body-sm);
  max-width: 42ch;
}
</style>
