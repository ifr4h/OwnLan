<script setup lang="ts">
import type { WeatherSnapshot } from '~/utils/weather/openMeteo'
import WeatherIcon from '~/components/weather/WeatherIcon.vue'

defineProps<{
  weather: WeatherSnapshot
}>()

const open = ref(false)
const root = ref<HTMLElement | null>(null)
const canHover = ref(false)

function toggle(event: Event) {
  event.preventDefault()
  event.stopPropagation()
  open.value = !open.value
}

function onKey(event: KeyboardEvent) {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    event.stopPropagation()
    open.value = !open.value
  }
  if (event.key === 'Escape') open.value = false
}

function onEnter() {
  if (canHover.value) open.value = true
}

function onLeave() {
  if (canHover.value) open.value = false
}

function onDocPointer(event: PointerEvent) {
  if (!open.value || !root.value) return
  if (event.target instanceof Node && root.value.contains(event.target)) return
  open.value = false
}

onMounted(() => {
  canHover.value = window.matchMedia('(hover: hover) and (pointer: fine)').matches
  document.addEventListener('pointerdown', onDocPointer, true)
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onDocPointer, true)
})
</script>

<template>
  <span
    ref="root"
    class="wx-chip"
    :data-open="open ? 'yes' : 'no'"
    @pointerenter="onEnter"
    @pointerleave="onLeave"
  >
    <button
      class="wx-chip__btn"
      type="button"
      :aria-label="weather.hint"
      :aria-expanded="open"
      @click="toggle"
      @keydown="onKey"
    >
      <WeatherIcon :kind="weather.kind" :size="22" />
    </button>

    <span v-if="open" class="wx-chip__pop" role="tooltip">
      {{ weather.hint }}
    </span>
  </span>
</template>

<style scoped>
.wx-chip {
  position: relative;
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
}

.wx-chip__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  margin: 0;
  padding: 0;
  border: none;
  border-radius: var(--radius-small);
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.wx-chip__btn:hover,
.wx-chip__btn:focus-visible {
  background: var(--surface-wash);
  outline: none;
}

.wx-chip__pop {
  position: absolute;
  right: 0;
  top: calc(100% + 6px);
  z-index: 20;
  min-width: 180px;
  max-width: min(260px, 70vw);
  padding: 10px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-panel);
  background: var(--color-paper-white);
  color: var(--color-ink-black);
  font-size: var(--text-meta);
  line-height: 1.35;
  box-shadow: 0 8px 20px rgba(32, 21, 21, 0.08);
  pointer-events: none;
}

@media (max-width: 520px) {
  .wx-chip__pop {
    right: auto;
    left: 50%;
    transform: translateX(-50%);
  }
}
</style>
