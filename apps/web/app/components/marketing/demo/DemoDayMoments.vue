<template>
  <div class="moments">
    <article v-for="moment in moments" :key="moment.id" class="moments__card">
      <header class="moments__head">
        <p class="moments__label">{{ moment.label }}</p>
        <time class="moments__time">{{ moment.time }}</time>
      </header>
      <ul class="moments__list">
        <li v-for="(item, i) in moment.items" :key="i">
          <span class="moments__item-label">{{ item.label }}</span>
          <span class="moments__item-detail">{{ item.detail }}</span>
        </li>
      </ul>
      <p class="moments__note">{{ moment.note }}</p>
    </article>
  </div>
</template>

<script setup lang="ts">
import { dayMoments as moments } from '~/marketing/content/copy/home'
</script>

<style scoped>
.moments {
  display: grid;
  gap: var(--spacing-16);
}

@media (min-width: 768px) {
  .moments {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1280px) {
  .moments {
    grid-template-columns: repeat(4, 1fr);
  }
}

.moments__card {
  background: var(--color-paper-white);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-panel);
  padding: var(--spacing-20);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.moments__label {
  font-family: var(--font-martian-mono);
  font-size: 11px;
  text-transform: uppercase;
  color: var(--color-ownlane-green);
}

.moments__time {
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.moments__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: var(--spacing-8);
  flex: 1;
}

.moments__list li {
  display: grid;
  gap: 2px;
}

.moments__item-label {
  font-size: var(--text-body-sm);
  font-weight: 500;
}

.moments__item-detail {
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.moments__note {
  font-size: var(--text-meta);
  color: rgba(17, 17, 24, 0.65);
  border-top: 1px solid var(--color-border);
  padding-top: var(--spacing-12);
}
</style>
