<template>
  <div>
    <section class="marketing-section">
      <div class="marketing-shell page-hero">
        <p class="marketing-eyebrow">About</p>
        <h1 class="marketing-display">{{ copy.hero.title }}</h1>
        <p class="marketing-lead">{{ copy.hero.lead }}</p>
      </div>
    </section>

    <section class="marketing-section marketing-section--wash">
      <div class="marketing-shell about-body">
        <p v-for="(para, i) in copy.body" :key="i" class="marketing-body">{{ para }}</p>
        <div class="marketing-actions">
          <NuxtLink :to="site.cta.primaryTo" class="marketing-btn marketing-btn--primary">
            {{ site.cta.primary }}
          </NuxtLink>
          <NuxtLink to="/instructors" class="marketing-btn marketing-btn--ghost">For instructors</NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { aboutCopy as copy } from '~/marketing/content/copy/pages'
import { marketingSite as site } from '~/marketing/content/site'

definePageMeta({ layout: 'marketing' })

useMarketingSeo({
  title: copy.meta.title,
  description: copy.meta.description,
  path: '/about',
})
</script>

<style scoped>
.page-hero {
  max-width: var(--marketing-hero-max);
}

.about-body {
  max-width: 640px;
  display: grid;
  gap: var(--spacing-20);
}
</style>
