<template>
  <section class="ol-page" style="max-width: 560px">
    <NuxtLink to="/pupils" class="ol-back">← Pupils</NuxtLink>

    <header class="ol-page-header">
      <p class="ol-eyebrow">New pupil</p>
      <h1 class="ol-page-title">Add a pupil</h1>
      <p class="ol-muted" style="max-width: 40ch">
        How would you like to get their details?
      </p>
    </header>

    <div class="choices">
      <NuxtLink to="/pupils/new/manual" class="ol-card ol-card--action choice">
        <span class="choice__title">Add them myself</span>
        <span class="choice__copy ol-muted">
          Type in a name and mobile now. You can fill the rest later.
        </span>
        <OlIcon name="chevron-right" :size="18" class="choice__arrow" />
      </NuxtLink>

      <NuxtLink to="/pupils/intake/new" class="ol-card ol-card--action ol-card--soft choice">
        <span class="choice__title">Let pupil fill their details</span>
        <span class="choice__copy ol-muted">
          Send a link. They answer a few questions — you get a clear brief to review.
        </span>
        <OlIcon name="chevron-right" :size="18" class="choice__arrow" />
      </NuxtLink>
    </div>

    <p class="ol-meta">
      Moving from another system?
      <NuxtLink to="/pupils/import" class="choice__import">Import a CSV</NuxtLink>
    </p>
  </section>
</template>

<script setup lang="ts">
useHead({ title: 'Add pupil · OwnLane' })
</script>

<style scoped>
.choices {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.choice {
  display: grid;
  grid-template-columns: 1fr auto;
  grid-template-rows: auto auto;
  column-gap: var(--spacing-12);
  row-gap: var(--spacing-8);
  text-decoration: none;
  color: inherit;
}

.choice__title {
  grid-column: 1;
  grid-row: 1;
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-body);
  letter-spacing: -0.01em;
}

.choice__copy {
  grid-column: 1;
  grid-row: 2;
  font-size: var(--text-body-sm);
  line-height: var(--leading-body-sm);
  max-width: 36ch;
}

.choice__arrow {
  grid-column: 2;
  grid-row: 1 / span 2;
  align-self: center;
  color: var(--color-ownlane-green);
}

.choice__import {
  color: var(--color-ownlane-green);
  margin-left: 0.25em;
}
</style>
