<template>
  <div>
    <section class="marketing-section">
      <div class="marketing-shell page-hero">
        <p class="marketing-eyebrow">Product</p>
        <h1 class="marketing-display">What OwnLane does</h1>
        <p class="marketing-lead">
          Each area below maps to working software in beta. We show the interface, not a bullet list.
        </p>
      </div>
    </section>

    <section class="marketing-section marketing-section--wash">
      <div class="marketing-shell features-grid">
        <article
          v-for="feature in availableFeatures"
          :key="feature.slug"
          class="feature-card"
        >
          <div class="feature-card__head">
            <h2 class="feature-card__title">{{ feature.title }}</h2>
            <span v-if="feature.route" class="feature-card__badge">Available</span>
            <span v-else class="feature-card__badge feature-card__badge--muted">In product</span>
          </div>
          <p class="feature-card__body">{{ feature.shortDescription }}</p>
          <NuxtLink
            v-if="feature.route"
            :to="feature.route"
            class="feature-card__link"
          >
            View feature
          </NuxtLink>
        </article>
      </div>
    </section>

    <section v-if="comingSoon.length" class="marketing-section">
      <div class="marketing-shell">
        <h2 class="marketing-h2">Coming soon</h2>
        <ul class="coming-list">
          <li v-for="item in comingSoon" :key="item.slug">
            <strong>{{ item.title }}</strong> — {{ item.shortDescription }}
          </li>
        </ul>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { availableFeatures, marketingFeatures } from '~/marketing/content/features'

definePageMeta({ layout: 'marketing' })

const comingSoon = marketingFeatures.filter(f => f.status === 'coming-soon')

useMarketingSeo({
  title: 'Features',
  description: 'Diary, pupils, progress, teaching tools, money and learner portal for UK driving instructors.',
  path: '/features',
})
</script>

<style scoped>
.page-hero {
  max-width: var(--marketing-hero-max);
}

.features-grid {
  display: grid;
  gap: var(--spacing-16);
}

@media (min-width: 768px) {
  .features-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

.feature-card {
  background: var(--color-paper-white);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-panel);
  padding: var(--spacing-24);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.feature-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-8);
}

.feature-card__title {
  font-size: var(--text-subheading);
  letter-spacing: -0.02em;
}

.feature-card__badge {
  font-family: var(--font-martian-mono);
  font-size: 10px;
  text-transform: uppercase;
  padding: 4px 8px;
  border-radius: var(--radius-tags);
  background: var(--color-success-wash);
  color: var(--color-ownlane-green);
  white-space: nowrap;
}

.feature-card__badge--muted {
  background: var(--color-frost-green);
  color: var(--color-muted);
}

.feature-card__body {
  font-size: var(--text-body-sm);
  line-height: 1.5;
  color: rgba(17, 17, 24, 0.78);
  flex: 1;
}

.feature-card__link {
  font-size: var(--text-body-sm);
  color: var(--color-ownlane-green);
  text-decoration: underline;
}

.coming-list {
  margin-top: var(--spacing-16);
  padding-left: 1.2em;
  display: grid;
  gap: var(--spacing-12);
  font-size: var(--text-body-sm);
  max-width: 60ch;
}
</style>
