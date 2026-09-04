<template>
  <div class="phone-frame">
    <div class="phone-frame__screen learner-demo">
      <header class="learner-demo__head">
        <p class="learner-demo__greeting">{{ data.greeting }}</p>
        <p class="learner-demo__date">Thursday 11 September</p>
      </header>

      <section class="learner-demo__drive">
        <p class="learner-demo__eyebrow">{{ data.nextDrive }}</p>
        <h3 class="learner-demo__time">{{ data.nextDriveTime }}</h3>
        <p class="learner-demo__focus">Working on: {{ data.focus }}</p>
      </section>

      <div class="learner-demo__stats">
        <div><span class="learner-demo__stat-val">{{ data.hoursDriven }}</span><span class="learner-demo__stat-lbl">driven</span></div>
        <div><span class="learner-demo__stat-val">{{ data.lessonsDone }}</span><span class="learner-demo__stat-lbl">lessons</span></div>
        <div><span class="learner-demo__stat-val">{{ data.testCountdown }}</span><span class="learner-demo__stat-lbl">to test</span></div>
      </div>

      <section class="learner-demo__recap">
        <p class="learner-demo__eyebrow">Last lesson</p>
        <p>{{ data.lastLesson }}</p>
      </section>

      <nav class="learner-demo__nav" aria-hidden="true">
        <span class="learner-demo__nav-item learner-demo__nav-item--active">Home</span>
        <span class="learner-demo__nav-item">Lessons</span>
        <span class="learner-demo__nav-item">Progress</span>
        <span class="learner-demo__nav-item">Learn</span>
      </nav>
    </div>
  </div>
</template>

<script setup lang="ts">
import { marketingLearnerHome as data } from '~/marketing/content/demo-data'
</script>

<style scoped>
.learner-demo {
  padding: var(--spacing-20) var(--spacing-16) 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
  min-height: 520px;
}

.learner-demo__greeting {
  font-size: var(--text-body-sm);
}

.learner-demo__date {
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.learner-demo__drive {
  padding: var(--spacing-16);
  background: linear-gradient(165deg, var(--color-frost-green), var(--color-chalk-green));
  border-radius: var(--radius-panel);
  border: 1px solid var(--color-border);
}

.learner-demo__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: 10px;
  text-transform: uppercase;
  color: var(--color-ownlane-green);
}

.learner-demo__time {
  font-size: var(--text-heading-sm);
  letter-spacing: -0.02em;
  margin-top: 4px;
}

.learner-demo__focus {
  font-size: var(--text-meta);
  margin-top: var(--spacing-8);
}

.learner-demo__stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--spacing-8);
  text-align: center;
}

.learner-demo__stat-val {
  display: block;
  font-family: var(--font-martian-mono);
  font-size: 15px;
}

.learner-demo__stat-lbl {
  font-size: 11px;
  color: var(--color-muted);
}

.learner-demo__recap {
  padding: var(--spacing-12);
  background: var(--color-paper-white);
  border-radius: var(--radius-small);
  font-size: var(--text-body-sm);
}

.learner-demo__nav {
  margin-top: auto;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  border-top: 1px solid var(--color-border);
  padding: var(--spacing-12) 0;
  font-size: 10px;
  text-align: center;
}

.learner-demo__nav-item {
  color: var(--color-muted);
}

.learner-demo__nav-item--active {
  color: var(--color-ownlane-green);
}
</style>
