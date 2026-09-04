<template>
  <ProductFrame title="Amina Yusuf">
    <div class="pupil-demo">
      <h3 class="pupil-demo__name">{{ pupil.fullName }}</h3>
      <dl class="pupil-demo__grid">
        <div class="pupil-demo__cell">
          <dt>Next lesson</dt>
          <dd>{{ pupil.nextLesson }}</dd>
        </div>
        <div class="pupil-demo__cell">
          <dt>Last lesson</dt>
          <dd>{{ pupil.lastLesson }}</dd>
        </div>
        <div class="pupil-demo__cell pupil-demo__cell--wide">
          <dt>Next focus</dt>
          <dd>{{ pupil.nextFocus }}</dd>
        </div>
        <div class="pupil-demo__cell">
          <dt>Test</dt>
          <dd>{{ pupil.testDays }} days</dd>
        </div>
        <div class="pupil-demo__cell">
          <dt>Credit</dt>
          <dd>{{ pupil.creditHours }} hours</dd>
        </div>
      </dl>
      <section class="pupil-demo__history">
        <p class="pupil-demo__history-label">Recent progress</p>
        <ul>
          <li v-for="row in progress.history" :key="row.date">
            <span>{{ row.date }}</span>
            <span>{{ row.note }}</span>
          </li>
        </ul>
      </section>
    </div>
  </ProductFrame>
</template>

<script setup lang="ts">
import { marketingProgress as progress, marketingPupils } from '~/marketing/content/demo-data'

const pupil = marketingPupils.amina
</script>

<style scoped>
.pupil-demo__name {
  font-size: var(--text-subheading);
  letter-spacing: -0.02em;
  margin-bottom: var(--spacing-16);
}

.pupil-demo__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--spacing-12);
}

.pupil-demo__cell--wide {
  grid-column: 1 / -1;
}

.pupil-demo__cell dt {
  font-size: 11px;
  color: var(--color-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.pupil-demo__cell dd {
  font-size: var(--text-body-sm);
  margin: 4px 0 0;
}

.pupil-demo__history {
  margin-top: var(--spacing-20);
  padding-top: var(--spacing-16);
  border-top: 1px solid var(--color-border);
}

.pupil-demo__history-label {
  font-family: var(--font-martian-mono);
  font-size: 11px;
  text-transform: uppercase;
  color: var(--color-muted);
  margin-bottom: var(--spacing-8);
}

.pupil-demo__history ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: var(--spacing-8);
}

.pupil-demo__history li {
  display: grid;
  grid-template-columns: 64px 1fr;
  gap: var(--spacing-8);
  font-size: var(--text-meta);
}

.pupil-demo__history li span:first-child {
  font-family: var(--font-martian-mono);
  color: var(--color-muted);
}
</style>
