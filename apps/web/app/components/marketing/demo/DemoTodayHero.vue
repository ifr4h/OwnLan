<template>
  <ProductFrame title="Today">
    <div class="today-demo">
      <header class="today-demo__head">
        <p class="today-demo__eyebrow">Good morning, Sarah</p>
        <h3 class="today-demo__date">Thursday 11 September</h3>
        <p class="today-demo__summary">4 lessons · 6h teaching · 09:00–18:30</p>
      </header>

      <article class="today-demo__hero">
        <p class="today-demo__label">Next lesson · 09:00</p>
        <h4 class="today-demo__pupil">{{ pupils.amina.fullName }}</h4>
        <dl class="today-demo__facts">
          <div><dt>Last time</dt><dd>{{ pupils.amina.lastLesson }}</dd></div>
          <div><dt>Next focus</dt><dd>{{ pupils.amina.nextFocus }}</dd></div>
          <div><dt>Credit</dt><dd>{{ pupils.amina.creditHours }} hours</dd></div>
          <div><dt>Pickup</dt><dd>{{ pupils.amina.pickup }}</dd></div>
        </dl>
        <p class="today-demo__action">Open in Maps · Call</p>
      </article>

      <ul class="today-demo__list" aria-label="Today's schedule">
        <li v-for="row in rows" :key="row.time" :class="{ 'today-demo__gap': row.gap }">
          <span class="today-demo__time">{{ row.time }}</span>
          <span class="today-demo__name">{{ row.label }}</span>
          <span v-if="row.meta" class="today-demo__meta">{{ row.meta }}</span>
        </li>
      </ul>

      <aside class="today-demo__alert">
        <p><strong>£186</strong> outstanding · <strong>4</strong> pupils need another lesson</p>
      </aside>
    </div>
  </ProductFrame>
</template>

<script setup lang="ts">
import { marketingDiary, marketingPupils } from '~/marketing/content/demo-data'

const pupils = marketingPupils

const rows = [
  { time: '09:00', label: pupils.amina.fullName, meta: '1h 30m' },
  { time: '11:00', label: pupils.jack.fullName, meta: '1h' },
  { time: '13:00', label: 'Free', meta: '3 could fit', gap: true },
  { time: '16:00', label: pupils.sophie.fullName, meta: '1h 30m' },
]
</script>

<style scoped>
.today-demo {
  display: grid;
  gap: var(--spacing-16);
}

.today-demo__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: 11px;
  text-transform: uppercase;
  color: var(--color-ownlane-green);
}

.today-demo__date {
  font-size: var(--text-heading-sm);
  letter-spacing: -0.02em;
}

.today-demo__summary {
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.today-demo__hero {
  padding: var(--spacing-16);
  background: linear-gradient(165deg, var(--color-frost-green), var(--color-chalk-green));
  border-radius: var(--radius-panel);
  border: 1px solid var(--color-border);
}

.today-demo__label {
  font-family: var(--font-martian-mono);
  font-size: 11px;
  text-transform: uppercase;
  color: var(--color-ownlane-green);
}

.today-demo__pupil {
  font-size: var(--text-subheading);
  margin-top: 4px;
}

.today-demo__facts {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--spacing-8);
  margin-top: var(--spacing-12);
}

.today-demo__facts dt {
  font-size: 11px;
  color: var(--color-muted);
}

.today-demo__facts dd {
  font-size: var(--text-body-sm);
  margin: 0;
}

.today-demo__action {
  margin-top: var(--spacing-12);
  font-size: var(--text-meta);
  color: var(--color-ownlane-green);
}

.today-demo__list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.today-demo__list li {
  display: grid;
  grid-template-columns: 56px 1fr auto;
  gap: var(--spacing-8);
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid var(--color-border);
  font-size: var(--text-body-sm);
}

.today-demo__gap {
  background: var(--color-warning-wash);
  margin-inline: -8px;
  padding-inline: 8px;
  border-radius: var(--radius-small);
}

.today-demo__time {
  font-family: var(--font-martian-mono);
  font-size: 13px;
}

.today-demo__meta {
  font-size: var(--text-meta);
  color: var(--color-ownlane-green);
}

.today-demo__alert {
  padding: var(--spacing-12);
  background: var(--color-info-wash);
  border-radius: var(--radius-small);
  font-size: var(--text-body-sm);
}
</style>
