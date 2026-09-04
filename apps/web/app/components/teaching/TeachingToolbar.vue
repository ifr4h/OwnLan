<script setup lang="ts">
import type { TeachingTool } from '~/utils/teaching/scene'

defineProps<{
  tool: TeachingTool
  canUndo: boolean
  canRedo: boolean
  stepLabel?: string
  stepIndex: number
  stepCount: number
  playing?: boolean
}>()

const emit = defineEmits<{
  'update:tool': [tool: TeachingTool]
  undo: []
  redo: []
  clear: []
  'prev-step': []
  'next-step': []
  'add-step': []
  play: []
  'stop-play': []
}>()

const { t } = useTeachingI18n()

const tools: Array<{ id: TeachingTool; labelKey: string }> = [
  { id: 'select', labelKey: 'tools.select' },
  { id: 'pen', labelKey: 'tools.pen' },
  { id: 'highlighter', labelKey: 'tools.highlighter' },
  { id: 'arrow', labelKey: 'tools.arrow' },
  { id: 'line', labelKey: 'tools.line' },
  { id: 'eraser', labelKey: 'tools.eraser' },
  { id: 'text', labelKey: 'tools.text' },
]
</script>

<template>
  <div class="ttb" role="toolbar" aria-label="Teaching tools">
    <div class="ttb__tools">
      <button
        v-for="item in tools"
        :key="item.id"
        type="button"
        class="ttb__btn"
        :class="{ 'ttb__btn--on': tool === item.id }"
        :aria-pressed="tool === item.id"
        @click="emit('update:tool', item.id)"
      >
        {{ t(item.labelKey) }}
      </button>
    </div>

    <div class="ttb__history">
      <button
        type="button"
        class="ttb__btn ttb__btn--ghost"
        :disabled="!canUndo"
        @click="emit('undo')"
      >
        {{ t('tools.undo') }}
      </button>
      <button
        type="button"
        class="ttb__btn ttb__btn--ghost"
        :disabled="!canRedo"
        @click="emit('redo')"
      >
        {{ t('tools.redo') }}
      </button>
      <button type="button" class="ttb__btn ttb__btn--ghost" @click="emit('clear')">
        {{ t('board.clear') }}
      </button>
    </div>

    <div class="ttb__steps">
      <button
        type="button"
        class="ttb__btn ttb__btn--ghost"
        :disabled="stepIndex <= 0"
        :aria-label="t('tools.prevStep')"
        @click="emit('prev-step')"
      >
        ←
      </button>
      <span class="ttb__step-label">
        {{ stepLabel || `Step ${stepIndex + 1}` }}
        <span class="ttb__step-count">{{ stepIndex + 1 }}/{{ stepCount }}</span>
      </span>
      <button
        type="button"
        class="ttb__btn ttb__btn--ghost"
        :disabled="stepIndex >= stepCount - 1"
        :aria-label="t('tools.nextStep')"
        @click="emit('next-step')"
      >
        →
      </button>
      <button type="button" class="ttb__btn ttb__btn--ghost" @click="emit('add-step')">
        {{ t('tools.addStep') }}
      </button>
      <button
        v-if="!playing"
        type="button"
        class="ttb__btn ttb__btn--accent"
        @click="emit('play')"
      >
        {{ t('tools.play') }}
      </button>
      <button
        v-else
        type="button"
        class="ttb__btn ttb__btn--accent"
        @click="emit('stop-play')"
      >
        Stop
      </button>
    </div>
  </div>
</template>

<style scoped>
.ttb {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
  padding: var(--spacing-8);
  background: var(--color-frost-green);
  border-radius: var(--radius-panel);
}

.ttb__tools,
.ttb__history,
.ttb__steps {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
}

.ttb__btn {
  min-height: 44px;
  padding: 8px 14px;
  border: none;
  border-radius: 14px;
  background: var(--color-paper-white);
  color: var(--color-ink-black);
  font: inherit;
  font-size: var(--text-meta);
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}

.ttb__btn--on {
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
}

.ttb__btn--ghost {
  background: transparent;
  border: 1px solid rgba(17, 17, 24, 0.1);
}

.ttb__btn--accent {
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
}

.ttb__btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.ttb__step-label {
  font-size: var(--text-meta);
  padding: 0 6px;
  display: inline-flex;
  flex-direction: column;
  line-height: 1.2;
}

.ttb__step-count {
  opacity: 0.55;
  font-family: var(--font-martian-mono);
  font-size: 11px;
}
</style>
