<script setup lang="ts">
import type { SceneObjectKind } from '~/utils/teaching/scene'

const emit = defineEmits<{
  add: [kind: SceneObjectKind]
}>()

const { t } = useTeachingI18n()

const items: SceneObjectKind[] = [
  'learner_car',
  'other_car',
  'bus',
  'bike',
  'pedestrian',
  'traffic_light',
  'give_way',
  'stop',
  'arrow',
  'hazard',
]
</script>

<template>
  <div class="pal" aria-label="Board objects">
    <p class="pal__title">{{ t('objects.title') }}</p>
    <div class="pal__grid">
      <button
        v-for="kind in items"
        :key="kind"
        type="button"
        class="pal__item"
        @click="emit('add', kind)"
      >
        <span class="pal__glyph" :data-kind="kind" aria-hidden="true" />
        <span>{{ t(`objects.${kind}`) }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.pal {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.pal__title {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
}

.pal__grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.pal__item {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 48px;
  padding: 8px 14px;
  border: none;
  border-radius: 16px;
  background: var(--color-frost-green);
  color: var(--color-ink-black);
  font: inherit;
  font-size: var(--text-meta);
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}

.pal__item:active {
  background: var(--color-soft-sage);
}

.pal__glyph {
  width: 22px;
  height: 22px;
  border-radius: 6px;
  background: var(--color-ownlane-green);
  flex-shrink: 0;
}

.pal__glyph[data-kind='other_car'] { background: var(--color-ink-black); }
.pal__glyph[data-kind='bus'] { background: var(--color-hi-yellow); }
.pal__glyph[data-kind='bike'] { background: var(--color-jelly-green); border-radius: 50%; }
.pal__glyph[data-kind='pedestrian'] { background: var(--color-ink-black); border-radius: 50%; width: 14px; }
.pal__glyph[data-kind='traffic_light'] { background: linear-gradient(#ff4141 33%, #ffda00 33% 66%, #16ab59 66%); }
.pal__glyph[data-kind='give_way'],
.pal__glyph[data-kind='stop'] { background: var(--color-marker-red); clip-path: polygon(50% 0%, 0% 100%, 100% 100%); }
.pal__glyph[data-kind='hazard'] { background: var(--color-hi-yellow); clip-path: polygon(50% 0%, 0% 100%, 100% 100%); }
.pal__glyph[data-kind='arrow'] { clip-path: polygon(50% 0%, 100% 70%, 65% 70%, 65% 100%, 35% 100%, 35% 70%, 0% 70%); }

@media (prefers-reduced-motion: reduce) {
  .pal__item:active {
    background: var(--color-frost-green);
  }
}
</style>
