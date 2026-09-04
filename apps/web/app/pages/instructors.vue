<template>
  <div>
    <section class="marketing-section">
      <div class="marketing-shell page-hero">
        <p class="marketing-eyebrow">For instructors</p>
        <h1 class="marketing-display">{{ copy.hero.title }}</h1>
        <p class="marketing-lead">{{ copy.hero.lead }}</p>
        <div class="marketing-actions">
          <NuxtLink :to="site.cta.primaryTo" class="marketing-btn marketing-btn--primary">
            {{ site.cta.primary }}
          </NuxtLink>
          <NuxtLink to="/features" class="marketing-btn marketing-btn--ghost">See features</NuxtLink>
        </div>
      </div>
    </section>

    <section class="marketing-section marketing-section--wash">
      <div class="marketing-shell">
        <DemoTodayHero />
      </div>
    </section>

    <section class="marketing-section">
      <div class="marketing-shell problem-grid">
        <article v-for="item in copy.problems" :key="item.title" class="problem-card">
          <h2 class="problem-card__title">{{ item.title }}</h2>
          <p class="problem-card__body">{{ item.body }}</p>
        </article>
      </div>
    </section>

    <section class="marketing-section marketing-section--paper">
      <div class="marketing-shell marketing-grid-2">
        <div>
          <h2 class="marketing-h2">{{ copy.switching.title }}</h2>
          <p class="marketing-body">{{ copy.switching.body }}</p>
        </div>
        <DemoPupilCard />
      </div>
    </section>

    <section class="marketing-section">
      <div class="marketing-shell page-hero">
        <h2 class="marketing-h2">Your pupils get their own app</h2>
        <p class="marketing-body">
          Progress, routes, lesson playback and resources — so they are not waiting on a reminder text the night before.
        </p>
        <NuxtLink to="/learners" class="marketing-link">See the learner experience</NuxtLink>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { instructorsCopy as copy } from '~/marketing/content/copy/pages'
import { marketingSite as site } from '~/marketing/content/site'

definePageMeta({ layout: 'marketing' })

useMarketingSeo({
  title: copy.meta.title,
  description: copy.meta.description,
  path: '/instructors',
})
</script>

<style scoped>
.page-hero {
  max-width: var(--marketing-hero-max);
}

.problem-grid {
  display: grid;
  gap: var(--spacing-20);
}

@media (min-width: 768px) {
  .problem-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1280px) {
  .problem-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.problem-card {
  background: var(--color-paper-white);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-panel);
  padding: var(--spacing-24);
}

.problem-card__title {
  font-size: var(--text-subheading);
  letter-spacing: -0.02em;
}

.problem-card__body {
  margin-top: var(--spacing-8);
  font-size: var(--text-body-sm);
  line-height: 1.5;
  color: rgba(17, 17, 24, 0.78);
}
</style>
