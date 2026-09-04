<template>
  <div class="continuity">
    <div v-for="(step, i) in steps" :key="step.id" class="continuity__step">
      <div class="continuity__card">
        <p class="continuity__label">{{ step.label }}</p>
        <template v-for="(line, j) in step.lines" :key="j">
          <p v-if="line.strong" class="continuity__strong">{{ line.text }}</p>
          <p v-else class="continuity__text">{{ line.text }}</p>
        </template>
      </div>
      <div v-if="i < steps.length - 1" class="continuity__arrow" aria-hidden="true">↓</div>
    </div>
  </div>
</template>

<script setup lang="ts">
const steps = [
  {
    id: 'before',
    label: 'Before',
    lines: [
      { text: 'Last lesson', strong: false },
      { text: 'Larger roundabouts', strong: true },
      { text: 'Next focus', strong: false },
      { text: 'Lane choice', strong: true },
    ],
  },
  {
    id: 'lesson',
    label: 'Lesson',
    lines: [{ text: 'You teach', strong: true }],
  },
  {
    id: 'after',
    label: 'After',
    lines: [
      { text: 'Progress updated', strong: false },
      { text: 'Next focus saved', strong: false },
      { text: 'Next lesson booked', strong: false },
    ],
  },
  {
    id: 'next',
    label: 'Next time',
    lines: [{ text: 'OwnLane already remembers', strong: true }],
  },
]
</script>

<style scoped>
.continuity {
  display: grid;
  gap: var(--spacing-8);
}

@media (min-width: 1024px) {
  .continuity {
    grid-template-columns: repeat(4, 1fr);
    align-items: start;
  }

  .continuity__step {
    display: contents;
  }

  .continuity__arrow {
    display: none;
  }
}

.continuity__card {
  background: var(--color-paper-white);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-panel);
  padding: var(--spacing-20);
  min-height: 140px;
}

.continuity__label {
  font-family: var(--font-martian-mono);
  font-size: 11px;
  text-transform: uppercase;
  color: var(--color-ownlane-green);
  margin-bottom: var(--spacing-12);
}

.continuity__strong {
  font-size: var(--text-body-sm);
  margin-top: 4px;
}

.continuity__text {
  font-size: var(--text-meta);
  color: var(--color-muted);
  margin-top: 6px;
}

.continuity__arrow {
  text-align: center;
  color: var(--color-muted);
  padding: 4px 0;
}
</style>
