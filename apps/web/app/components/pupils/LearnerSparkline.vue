<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    values: number[]
    labels: string[]
    tone?: 'green' | 'ink'
  }>(),
  { tone: 'green' },
)

const width = 320
const height = 120
const padX = 8
const padY = 12

const geometry = computed(() => {
  const values = props.values.length ? props.values : [0]
  const max = Math.max(...values, 1)
  const min = Math.min(...values, 0)
  const span = Math.max(max - min, 1)
  const n = values.length
  const step = n <= 1 ? 0 : (width - padX * 2) / (n - 1)
  const points = values.map((v, i) => {
    const x = padX + i * step
    const y = height - padY - ((v - min) / span) * (height - padY * 2)
    return { x, y }
  })
  const line = points
    .map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x.toFixed(1)} ${p.y.toFixed(1)}`)
    .join(' ')
  const last = points[points.length - 1]
  const first = points[0]
  const area = first && last
    ? `${line} L${last.x.toFixed(1)} ${height - padY} L${first.x.toFixed(1)} ${height - padY} Z`
    : ''
  return { line, area }
})

const stroke = computed(() =>
  props.tone === 'ink' ? 'var(--color-ink-black)' : 'var(--color-ownlane-green)',
)

const fill = computed(() =>
  props.tone === 'ink'
    ? 'color-mix(in srgb, var(--color-ink-black) 12%, transparent)'
    : 'color-mix(in srgb, var(--color-ownlane-green) 22%, transparent)',
)
</script>

<template>
  <div class="spark">
    <svg
      class="spark__svg"
      :viewBox="`0 0 ${width} ${height}`"
      role="img"
      aria-hidden="true"
    >
      <path :d="geometry.area" :fill="fill" stroke="none" />
      <path
        :d="geometry.line"
        fill="none"
        :stroke="stroke"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
    </svg>
    <div class="spark__labels" aria-hidden="true">
      <span v-for="(label, i) in labels" :key="`${label}-${i}`">{{ label }}</span>
    </div>
  </div>
</template>

<style scoped>
.spark {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.spark__svg {
  width: 100%;
  height: 120px;
  display: block;
}

.spark__labels {
  display: flex;
  justify-content: space-between;
  gap: 4px;
  font-size: 11px;
  color: var(--color-muted);
}
</style>
