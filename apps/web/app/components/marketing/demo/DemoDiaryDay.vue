<template>
  <ProductFrame :title="`${diary.day} · ${diary.date}`">
    <div class="diary-demo">
      <p class="diary-demo__day">{{ diary.day }}</p>
      <ul class="diary-demo__list">
        <li
          v-for="(block, i) in diary.lessons"
          :key="i"
          :class="{ 'diary-demo__gap': block.gap }"
        >
          <span class="diary-demo__time">{{ block.time }}</span>
          <div class="diary-demo__body">
            <template v-if="block.gap">
              <p class="diary-demo__gap-label">{{ block.gapLabel }}</p>
              <p class="diary-demo__match">{{ block.gapMatches }} pupils could fit</p>
            </template>
            <template v-else>
              <p class="diary-demo__pupil">{{ block.pupil }}</p>
              <p class="diary-demo__dur">{{ block.duration }}</p>
            </template>
          </div>
        </li>
      </ul>
    </div>
  </ProductFrame>
</template>

<script setup lang="ts">
import { marketingDiary as diary } from '~/marketing/content/demo-data'
</script>

<style scoped>
.diary-demo__day {
  font-family: var(--font-martian-mono);
  font-size: 11px;
  text-transform: uppercase;
  color: var(--color-muted);
  margin-bottom: var(--spacing-12);
}

.diary-demo__list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.diary-demo__list li {
  display: grid;
  grid-template-columns: 64px 1fr;
  gap: var(--spacing-12);
  padding: var(--spacing-12) 0;
  border-bottom: 1px solid var(--color-border);
}

.diary-demo__time {
  font-family: var(--font-martian-mono);
  font-size: 14px;
}

.diary-demo__pupil {
  font-size: var(--text-body-sm);
}

.diary-demo__dur {
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.diary-demo__gap {
  background: var(--color-warning-wash);
  margin-inline: -12px;
  padding-inline: 12px;
  border-radius: var(--radius-small);
}

.diary-demo__gap-label {
  font-size: var(--text-body-sm);
}

.diary-demo__match {
  font-size: var(--text-meta);
  color: var(--color-ownlane-green);
  margin-top: 4px;
}
</style>
