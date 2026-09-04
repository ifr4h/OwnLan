<template>
  <ProductFrame title="Roundabouts">
    <div class="progress-demo">
      <header class="progress-demo__head">
        <h3>{{ progress.skill }}</h3>
        <p class="progress-demo__current">{{ progress.current }}</p>
      </header>
      <p class="progress-demo__label">Lesson history</p>
      <ul class="progress-demo__timeline">
        <li v-for="row in progress.history" :key="row.date">
          <time>{{ row.date }}</time>
          <span>{{ row.note }}</span>
        </li>
      </ul>
      <footer class="progress-demo__next">
        <p class="progress-demo__label">Next</p>
        <p>{{ progress.next }}</p>
      </footer>
    </div>
  </ProductFrame>
</template>

<script setup lang="ts">
import { marketingProgress as progress } from '~/marketing/content/demo-data'
</script>

<style scoped>
.progress-demo__head h3 {
  font-size: var(--text-subheading);
  letter-spacing: -0.02em;
}

.progress-demo__current {
  font-size: var(--text-body-sm);
  color: var(--color-ownlane-green);
  margin-top: 4px;
}

.progress-demo__label {
  font-family: var(--font-martian-mono);
  font-size: 11px;
  text-transform: uppercase;
  color: var(--color-muted);
  margin-top: var(--spacing-20);
  margin-bottom: var(--spacing-8);
}

.progress-demo__timeline {
  list-style: none;
  margin: 0;
  padding: 0;
  border-left: 2px solid var(--color-frost-green);
  padding-left: var(--spacing-16);
  display: grid;
  gap: var(--spacing-12);
}

.progress-demo__timeline li {
  display: grid;
  gap: 2px;
  position: relative;
}

.progress-demo__timeline li::before {
  content: '';
  position: absolute;
  left: calc(-1 * var(--spacing-16) - 5px);
  top: 6px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--color-ownlane-green);
}

.progress-demo__timeline time {
  font-family: var(--font-martian-mono);
  font-size: 12px;
  color: var(--color-muted);
}

.progress-demo__timeline span {
  font-size: var(--text-body-sm);
}

.progress-demo__next {
  margin-top: var(--spacing-20);
  padding: var(--spacing-12);
  background: var(--color-frost-green);
  border-radius: var(--radius-small);
  font-size: var(--text-body-sm);
}
</style>
