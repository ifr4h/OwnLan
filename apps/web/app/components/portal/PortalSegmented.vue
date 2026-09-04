<template>
  <div
    ref="root"
    class="seg"
    role="tablist"
    :aria-label="ariaLabel"
  >
    <span
      class="seg__indicator"
      :style="indicatorStyle"
      aria-hidden="true"
    />
    <button
      v-for="opt in options"
      :key="opt.value"
      ref="btns"
      type="button"
      class="seg__btn"
      role="tab"
      :aria-selected="opt.value === modelValue"
      :tabindex="opt.value === modelValue ? 0 : -1"
      @click="select(opt.value)"
    >
      {{ opt.label }}
    </button>
  </div>
</template>

<script setup lang="ts">
export type PortalSegmentOption = { value: string; label: string }

const props = defineProps<{
  modelValue: string
  options: PortalSegmentOption[]
  ariaLabel?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const root = ref<HTMLElement | null>(null)
const btns = ref<HTMLButtonElement[]>([])
const indicatorStyle = ref<Record<string, string>>({
  width: '0',
  transform: 'translateX(0)',
})

function select(value: string) {
  emit('update:modelValue', value)
}

function updateIndicator() {
  const idx = props.options.findIndex(o => o.value === props.modelValue)
  const el = btns.value[idx]
  if (!el || !root.value) return
  indicatorStyle.value = {
    width: `${el.offsetWidth}px`,
    transform: `translateX(${el.offsetLeft}px)`,
  }
}

watch(() => props.modelValue, () => nextTick(updateIndicator))
watch(() => props.options, () => nextTick(updateIndicator), { deep: true })

onMounted(() => {
  nextTick(updateIndicator)
  window.addEventListener('resize', updateIndicator)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', updateIndicator)
})
</script>

<style scoped>
.seg {
  position: relative;
  display: flex;
  gap: 2px;
  padding: 4px;
  background: var(--color-frost-green);
  border-radius: var(--radius-buttons);
  width: fit-content;
  max-width: 100%;
}

.seg__indicator {
  position: absolute;
  top: 4px;
  left: 0;
  height: calc(100% - 8px);
  background: var(--color-paper-white);
  border-radius: var(--radius-buttons);
  box-shadow: 0 1px 0 0 var(--color-border-strong);
  transition: transform var(--duration-med) var(--ease-out),
    width var(--duration-med) var(--ease-out);
  z-index: 0;
  pointer-events: none;
}

@media (prefers-reduced-motion: reduce) {
  .seg__indicator {
    transition: none;
  }
}

.seg__btn {
  position: relative;
  z-index: 1;
  border: none;
  background: transparent;
  font: inherit;
  font-size: var(--text-body-sm);
  min-height: 44px;
  padding: 0 16px;
  border-radius: var(--radius-buttons);
  cursor: pointer;
  color: var(--color-ink-black);
  white-space: nowrap;
}

.seg__btn[aria-selected='true'] {
  font-weight: 500;
}
</style>
