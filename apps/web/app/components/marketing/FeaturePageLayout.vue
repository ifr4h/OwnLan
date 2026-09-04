<template>
  <article class="feature-page">
    <section class="marketing-section">
      <div class="marketing-shell feature-page__hero">
        <p class="marketing-eyebrow">Feature</p>
        <h1 class="marketing-display">{{ copy.hero.title }}</h1>
        <p class="marketing-lead">{{ copy.hero.lead }}</p>
      </div>
    </section>

    <section class="marketing-section marketing-section--wash">
      <div class="marketing-shell marketing-grid-2">
        <div>
          <h2 class="marketing-h2">The problem</h2>
          <p class="marketing-body">{{ copy.problem }}</p>
        </div>
        <div class="feature-page__demo">
          <slot name="demo">
            <ProductFrame :title="copy.meta.title">
              <p class="marketing-body">Product demonstration</p>
            </ProductFrame>
          </slot>
        </div>
      </div>
    </section>

    <section class="marketing-section">
      <div class="marketing-shell">
        <h2 class="marketing-h2">What you see</h2>
        <div class="feature-page__cols">
          <div>
            <h3 class="feature-page__sub">Instructor</h3>
            <ul class="feature-page__list">
              <li v-for="item in copy.instructorSees" :key="item">{{ item }}</li>
            </ul>
          </div>
          <div v-if="copy.pupilSees?.length">
            <h3 class="feature-page__sub">Learner</h3>
            <ul class="feature-page__list">
              <li v-for="item in copy.pupilSees" :key="item">{{ item }}</li>
            </ul>
          </div>
        </div>
        <p class="marketing-body feature-page__connect">{{ copy.connects }}</p>
        <div class="marketing-actions">
          <NuxtLink :to="site.cta.primaryTo" class="marketing-btn marketing-btn--primary">
            {{ site.cta.primary }}
          </NuxtLink>
          <NuxtLink to="/features" class="marketing-link">All features</NuxtLink>
        </div>
      </div>
    </section>
  </article>
</template>

<script setup lang="ts">
import type { FeaturePageCopy } from '~/marketing/content/copy/feature-pages'
import { marketingSite as site } from '~/marketing/content/site'

defineProps<{
  copy: FeaturePageCopy
}>()
</script>

<style scoped>
.feature-page__hero {
  max-width: var(--marketing-hero-max);
}

.feature-page__cols {
  display: grid;
  gap: var(--spacing-32);
  margin-top: var(--spacing-24);
}

@media (min-width: 768px) {
  .feature-page__cols {
    grid-template-columns: 1fr 1fr;
  }
}

.feature-page__sub {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  text-transform: uppercase;
  color: var(--color-ownlane-green);
  margin-bottom: var(--spacing-12);
}

.feature-page__list {
  margin: 0;
  padding-left: 1.2em;
  display: grid;
  gap: var(--spacing-8);
  font-size: var(--text-body-sm);
  line-height: 1.5;
}

.feature-page__connect {
  margin-top: var(--spacing-32);
}
</style>
