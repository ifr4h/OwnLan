<script setup lang="ts">
/**
 * Searchable pupil picker — replaces browser <select> for growing lists.
 */
import type { PupilListItem } from '~/composables/usePupils'

const props = withDefaults(defineProps<{
  modelValue: number | null
  pupils: PupilListItem[]
  placeholder?: string
  disabled?: boolean
  recentIds?: number[]
}>(), {
  placeholder: 'Search pupils…',
  disabled: false,
  recentIds: () => [],
})

const emit = defineEmits<{
  'update:modelValue': [value: number | null]
}>()

const open = ref(false)
const query = ref('')
const root = ref<HTMLElement | null>(null)
const inputEl = ref<HTMLInputElement | null>(null)

const selected = computed(() =>
  props.pupils.find(p => p.id === props.modelValue) ?? null,
)

const recent = computed(() => {
  if (!props.recentIds.length) return []
  const map = new Map(props.pupils.map(p => [p.id, p]))
  return props.recentIds
    .map(id => map.get(id))
    .filter((p): p is PupilListItem => !!p)
    .slice(0, 5)
})

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return props.pupils.slice(0, 12)
  return props.pupils
    .filter((p) => {
      const hay = `${p.full_name} ${p.mobile} ${p.email ?? ''}`.toLowerCase()
      return hay.includes(q)
    })
    .slice(0, 20)
})

function openMenu() {
  if (props.disabled) return
  open.value = true
  query.value = ''
  nextTick(() => inputEl.value?.focus())
}

function closeMenu() {
  open.value = false
  query.value = ''
}

function pick(id: number) {
  emit('update:modelValue', id)
  closeMenu()
}

function clear() {
  emit('update:modelValue', null)
  closeMenu()
}

function onDocPointer(e: PointerEvent) {
  if (!open.value || !root.value) return
  if (!root.value.contains(e.target as Node)) closeMenu()
}

function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape') closeMenu()
}

onMounted(() => {
  document.addEventListener('pointerdown', onDocPointer)
  document.addEventListener('keydown', onKey)
})
onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onDocPointer)
  document.removeEventListener('keydown', onKey)
})
</script>

<template>
  <div ref="root" class="combo" :data-open="open ? 'yes' : 'no'">
    <button
      class="combo__trigger"
      type="button"
      :disabled="disabled"
      :aria-expanded="open"
      aria-haspopup="listbox"
      @click="open ? closeMenu() : openMenu()"
    >
      <OlIcon name="search" :size="18" class="combo__icon" />
      <span v-if="selected" class="combo__value">{{ selected.full_name }}</span>
      <span v-else class="combo__placeholder">{{ placeholder }}</span>
      <OlIcon name="chevron-down" :size="16" class="combo__caret" />
    </button>

    <div v-if="open" class="combo__panel" role="listbox">
      <label class="combo__search">
        <span class="sr-only">Search pupils</span>
        <input
          ref="inputEl"
          v-model="query"
          class="ol-input"
          type="search"
          autocomplete="off"
          :placeholder="placeholder"
        >
      </label>

      <button
        v-if="modelValue"
        class="combo__clear"
        type="button"
        @click="clear"
      >
        Clear selection
      </button>

      <template v-if="!query.trim() && recent.length">
        <p class="combo__group">Recent</p>
        <button
          v-for="p in recent"
          :key="`r-${p.id}`"
          class="combo__option"
          type="button"
          role="option"
          :aria-selected="p.id === modelValue"
          @click="pick(p.id)"
        >
          <span class="combo__option-name">{{ p.full_name }}</span>
          <span class="combo__option-meta">{{ p.mobile }}</span>
        </button>
      </template>

      <p v-if="filtered.length" class="combo__group">
        {{ query.trim() ? 'Results' : 'Pupils' }}
      </p>
      <button
        v-for="p in filtered"
        :key="p.id"
        class="combo__option"
        type="button"
        role="option"
        :aria-selected="p.id === modelValue"
        @click="pick(p.id)"
      >
        <span class="combo__option-name">{{ p.full_name }}</span>
        <span class="combo__option-meta">{{ p.mobile }}</span>
      </button>
      <p v-if="!filtered.length" class="combo__empty">No matches</p>
    </div>
  </div>
</template>

<style scoped>
.combo {
  position: relative;
}

.combo__trigger {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: var(--control-height);
  padding: 10px 16px;
  border: 1.5px solid var(--color-frost-green);
  border-radius: var(--radius-inputs);
  background: var(--surface-card);
  text-align: left;
  transition: border-color var(--duration-fast) ease, box-shadow var(--duration-fast) ease;
}

.combo__trigger:hover:not(:disabled) {
  border-color: #c5dccf;
}

.combo[data-open='yes'] .combo__trigger {
  border-color: var(--color-ownlane-green);
  box-shadow: 0 0 0 3px rgba(22, 139, 85, 0.18);
}

.combo__icon {
  color: var(--color-muted);
}

.combo__value {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.combo__placeholder {
  flex: 1;
  color: rgba(17, 17, 24, 0.4);
}

.combo__caret {
  color: var(--color-muted);
  margin-left: auto;
}

.combo__panel {
  position: absolute;
  z-index: var(--z-dropdown);
  top: calc(100% + 6px);
  left: 0;
  right: 0;
  max-height: min(360px, 60vh);
  overflow: auto;
  padding: 10px;
  background: var(--surface-card);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-panel);
  box-shadow: var(--shadow-soft);
  animation: combo-in var(--duration-med) var(--ease-out);
}

@keyframes combo-in {
  from {
    opacity: 0;
    transform: translateY(-4px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.combo__search {
  display: block;
  margin-bottom: 8px;
}

.combo__clear {
  width: 100%;
  text-align: left;
  padding: 8px 10px;
  border: none;
  background: transparent;
  color: var(--color-muted);
  font-size: var(--text-meta);
  border-radius: var(--radius-small);
}

.combo__clear:hover {
  background: var(--surface-wash);
}

.combo__group {
  margin: 8px 10px 4px;
  font-size: var(--text-label);
  color: var(--color-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.combo__option {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 10px 12px;
  border: none;
  border-radius: var(--radius-small);
  background: transparent;
  text-align: left;
}

.combo__option:hover,
.combo__option[aria-selected='true'] {
  background: var(--surface-wash);
}

.combo__option-name {
  font-size: var(--text-body-sm);
}

.combo__option-meta {
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.combo__empty {
  padding: 16px 12px;
  color: var(--color-muted);
  font-size: var(--text-meta);
}
</style>
