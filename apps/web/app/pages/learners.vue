<template>
  <div>
    <section class="marketing-section">
      <div class="marketing-shell page-hero">
        <p class="marketing-eyebrow">For learners</p>
        <h1 class="marketing-display">{{ copy.hero.title }}</h1>
        <p class="marketing-lead">{{ copy.hero.lead }}</p>
      </div>
    </section>

    <section class="marketing-section marketing-section--wash">
      <div class="marketing-shell marketing-grid-2">
        <DemoLearnerPhone />
        <DemoPlayback />
      </div>
    </section>

    <section class="marketing-section">
      <div class="marketing-shell includes-grid">
        <article v-for="item in copy.includes" :key="item.title" class="includes-card">
          <h2 class="includes-card__title">{{ item.title }}</h2>
          <p class="includes-card__body">{{ item.body }}</p>
        </article>
      </div>
    </section>

    <section class="marketing-section marketing-section--paper">
      <div class="marketing-shell">
        <p class="marketing-body">{{ copy.note }}</p>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { learnersCopy as copy } from '~/marketing/content/copy/pages'

definePageMeta({ layout: 'marketing' })

useMarketingSeo({
  title: copy.meta.title,
  description: copy.meta.description,
  path: '/learners',
})
</script>

<style scoped>
.page-hero {
  max-width: var(--marketing-hero-max);
}

.includes-grid {
  display: grid;
  gap: var(--spacing-16);
}

@media (min-width: 768px) {
  .includes-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1024px) {
  .includes-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

.includes-card {
  padding: var(--spacing-20);
  background: var(--color-paper-white);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-panel);
}

.includes-card__title {
  font-size: var(--text-body-sm);
  font-weight: 500;
}

.includes-card__body {
  margin-top: var(--spacing-8);
  font-size: var(--text-meta);
  color: var(--color-muted);
  line-height: 1.45;
}
</style>
