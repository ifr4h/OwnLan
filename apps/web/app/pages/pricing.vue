<template>
  <div>
    <section class="marketing-section">
      <div class="marketing-shell page-hero">
        <p class="marketing-eyebrow">Pricing</p>
        <h1 class="marketing-display">Straightforward while we are in beta</h1>
        <p class="marketing-lead">{{ pricing.disclaimer }}</p>
        <p v-if="pricing.status === 'draft'" class="pricing-draft">
          Draft pricing — requires founder approval before publication.
        </p>
      </div>
    </section>

    <section class="marketing-section marketing-section--wash">
      <div class="marketing-shell pricing-grid">
        <article
          v-for="tier in pricing.tiers"
          :key="tier.id"
          class="pricing-card"
          :data-featured="tier.featured ? 'yes' : 'no'"
        >
          <h2 class="pricing-card__name">{{ tier.name }}</h2>
          <p class="pricing-card__price">{{ tier.priceLabel }}</p>
          <p class="pricing-card__note">{{ tier.priceNote }}</p>
          <p class="pricing-card__desc">{{ tier.description }}</p>
          <ul class="pricing-card__list">
            <li v-for="item in tier.highlights" :key="item">{{ item }}</li>
          </ul>
          <NuxtLink
            :to="tier.ctaTo"
            class="marketing-btn"
            :class="tier.featured ? 'marketing-btn--primary' : 'marketing-btn--ghost'"
          >
            {{ tier.cta }}
          </NuxtLink>
        </article>
      </div>
    </section>

    <section class="marketing-section">
      <div class="marketing-shell">
        <p class="marketing-body">{{ pricing.foundingNote }}</p>
        <p class="pricing-fine">
          Card payments and automated billing are not live yet. OwnLane records manual payments and package credit today.
        </p>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { pricingDraft as pricing } from '~/marketing/content/pricing'

definePageMeta({ layout: 'marketing' })

useMarketingSeo({
  title: 'Pricing',
  description: 'OwnLane beta pricing for independent UK driving instructors. Core free during beta; Pro tier indicative.',
  path: '/pricing',
})
</script>

<style scoped>
.page-hero {
  max-width: var(--marketing-hero-max);
}

.pricing-draft {
  margin-top: var(--spacing-12);
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  color: var(--color-warning);
}

.pricing-grid {
  display: grid;
  gap: var(--spacing-20);
}

@media (min-width: 1024px) {
  .pricing-grid {
    grid-template-columns: repeat(3, 1fr);
    align-items: start;
  }
}

.pricing-card {
  background: var(--color-paper-white);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-panel);
  padding: var(--spacing-28);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.pricing-card[data-featured='yes'] {
  border-color: var(--color-ownlane-green);
  box-shadow: var(--shadow-soft);
}

.pricing-card__name {
  font-size: var(--text-subheading);
}

.pricing-card__price {
  font-family: var(--font-martian-mono);
  font-size: 28px;
  letter-spacing: -0.02em;
}

.pricing-card__note {
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.pricing-card__desc {
  font-size: var(--text-body-sm);
  line-height: 1.45;
}

.pricing-card__list {
  margin: 0;
  padding-left: 1.2em;
  font-size: var(--text-body-sm);
  display: grid;
  gap: var(--spacing-8);
  flex: 1;
}

.pricing-fine {
  margin-top: var(--spacing-16);
  font-size: var(--text-meta);
  color: var(--color-muted);
  max-width: 58ch;
}
</style>
