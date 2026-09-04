<script setup lang="ts">
import type { MoneyPreset } from '~/composables/useBusinessFinance'

const props = defineProps<{
  presets: MoneyPreset[]
  from: string
  to: string
  rangeLabel: string
  loading?: boolean
}>()

const emit = defineEmits<{
  'update:from': [value: string]
  'update:to': [value: string]
  applyPreset: [preset: MoneyPreset]
  reload: []
}>()

function onFromChange(e: Event) {
  emit('update:from', (e.target as HTMLInputElement).value)
  emit('reload')
}

function onToChange(e: Event) {
  emit('update:to', (e.target as HTMLInputElement).value)
  emit('reload')
}
</script>

<template>
  <section class="period-filter" aria-label="Reporting period">
    <div class="ol-seg period-filter__presets">
      <button
        v-for="preset in presets"
        :key="preset.id"
        class="ol-chip"
        type="button"
        :class="{ 'ol-chip--on': from === preset.from && to === preset.to }"
        :disabled="loading"
        @click="emit('applyPreset', preset)"
      >
        {{ preset.label }}
      </button>
    </div>
    <div class="period-filter__custom">
      <label class="ol-field">
        <span class="ol-field__label">From</span>
        <input
          :value="from"
          class="ol-input ol-input--date"
          type="date"
          :disabled="loading"
          @change="onFromChange"
        >
      </label>
      <label class="ol-field">
        <span class="ol-field__label">To</span>
        <input
          :value="to"
          class="ol-input ol-input--date"
          type="date"
          :disabled="loading"
          @change="onToChange"
        >
      </label>
      <slot name="actions" />
    </div>
    <p class="ol-meta">{{ rangeLabel }}</p>
  </section>
</template>

<style scoped>
.period-filter {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.period-filter__custom {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
  align-items: flex-end;
}

.period-filter__custom .ol-field {
  min-width: 140px;
  flex: 1;
}
</style>
