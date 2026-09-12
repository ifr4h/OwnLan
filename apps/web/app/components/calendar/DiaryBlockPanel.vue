<script setup lang="ts">
import type { DiaryBreak } from '~/composables/useDiaryBreaks'
import { formatDuration } from '~/utils/calendar/timeGrid'

const props = defineProps<{
  block: DiaryBreak | null
}>()

const emit = defineEmits<{
  close: []
  saved: [message?: string]
  removed: []
}>()

const { updateBlock, deleteBlock } = useDiaryBreaks()

const label = ref('Private')
const durationMinutes = ref(60)
const busy = ref(false)
const error = ref('')
const confirmRemove = ref(false)

const timeLabel = computed(() => {
  if (!props.block) return ''
  const start = props.block.starts_at_local?.slice(11, 16)
    || `${String(Math.floor(props.block.start_minutes / 60)).padStart(2, '0')}:${String(props.block.start_minutes % 60).padStart(2, '0')}`
  const endMins = props.block.end_minutes
  const end = `${String(Math.floor(endMins / 60)).padStart(2, '0')}:${String(endMins % 60).padStart(2, '0')}`
  return `${start}–${end} · ${formatDuration(props.block.end_minutes - props.block.start_minutes)}`
})

watch(
  () => props.block,
  (b) => {
    if (!b) return
    label.value = b.label || 'Private'
    durationMinutes.value = b.duration_minutes || (b.end_minutes - b.start_minutes) || 60
    confirmRemove.value = false
    error.value = ''
  },
  { immediate: true },
)

async function onSave() {
  if (!props.block) return
  busy.value = true
  error.value = ''
  try {
    await updateBlock(props.block.id, {
      label: label.value.trim() || 'Private',
      duration_minutes: Math.max(15, Number(durationMinutes.value) || 60),
    })
    emit('saved', 'Block updated')
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not update that block.'
  } finally {
    busy.value = false
  }
}

async function onRemove() {
  if (!props.block) return
  busy.value = true
  error.value = ''
  try {
    await deleteBlock(props.block.id)
    confirmRemove.value = false
    emit('removed')
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not remove that block.'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div v-if="block" class="blk">
    <header class="blk__chrome">
      <p class="blk__eyebrow">Private time</p>
      <button class="blk__close" type="button" aria-label="Close" @click="emit('close')">
        <OlIcon name="close" :size="16" />
      </button>
    </header>

    <p class="blk__time">{{ timeLabel }}</p>
    <p class="blk__hint">Only you see this on the diary. It never appears for pupils.</p>

    <label class="ol-field">
      <span class="ol-field__label">Label</span>
      <input
        v-model="label"
        class="ol-input"
        type="text"
        maxlength="120"
        placeholder="Break, Admin, Holiday…"
        :disabled="busy"
      >
    </label>

    <label class="ol-field">
      <span class="ol-field__label">Duration (minutes)</span>
      <input
        v-model.number="durationMinutes"
        class="ol-input"
        type="number"
        min="15"
        max="720"
        step="5"
        :disabled="busy"
      >
    </label>

    <p v-if="error" class="blk__error" role="alert">{{ error }}</p>

    <div v-if="confirmRemove" class="blk__confirm">
      <p>Remove this block from the diary?</p>
      <div class="blk__actions">
        <button class="ol-btn ol-btn--sm" type="button" :disabled="busy" @click="onRemove">
          Remove
        </button>
        <button class="ol-btn ol-btn--ghost ol-btn--sm" type="button" :disabled="busy" @click="confirmRemove = false">
          Keep it
        </button>
      </div>
    </div>

    <div v-else class="blk__actions">
      <button class="ol-btn ol-btn--sm" type="button" :disabled="busy" @click="onSave">
        {{ busy ? 'Saving…' : 'Save' }}
      </button>
      <button class="ol-btn ol-btn--ghost ol-btn--sm" type="button" :disabled="busy" @click="confirmRemove = true">
        Remove
      </button>
    </div>
  </div>
</template>

<style scoped>
.blk {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-height: 0;
  height: 100%;
}

.blk__chrome {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.blk__eyebrow {
  margin: 0;
  font-size: 10px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  font-weight: 600;
  color: var(--color-muted);
}

.blk__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: var(--color-muted);
  cursor: pointer;
}

.blk__time {
  margin: 0;
  font-size: var(--text-heading-sm);
  font-weight: 600;
  color: var(--color-ink-black);
}

.blk__hint {
  margin: 0;
  font-size: 12px;
  color: var(--color-muted);
}

.blk__error {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-danger);
}

.blk__confirm {
  padding: 12px;
  border-radius: var(--radius-small);
  border: 1px solid var(--color-border);
  background: var(--color-paper-white);
}

.blk__confirm p {
  margin: 0 0 10px;
  font-size: var(--text-body-sm);
}

.blk__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: auto;
  padding-top: 8px;
}
</style>
