<template>
  <div class="hours">
    <div class="hours__chart" role="img" :aria-label="ariaLabel">
      <Line
        v-if="ready"
        :data="chartData"
        :options="chartOptions"
      />
    </div>
    <table class="hours__fallback sr-only">
      <caption>{{ ariaLabel }}</caption>
      <thead>
        <tr>
          <th scope="col">Month</th>
          <th scope="col">Hours</th>
          <th scope="col">Lessons</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in months" :key="row.month">
          <td>{{ row.label }}</td>
          <td>{{ row.hours }}</td>
          <td>{{ row.lesson_ids.length }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Filler,
  Tooltip,
  type ChartOptions,
  type ChartEvent,
  type ActiveElement,
} from 'chart.js'
import { Line } from 'vue-chartjs'
import type { PortalHoursMonth } from '~/composables/usePortal'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip)

const props = defineProps<{
  months: PortalHoursMonth[]
  ariaLabel?: string
}>()

const emit = defineEmits<{
  'select-month': [payload: { month: string; label: string; lesson_ids: number[] }]
}>()

const ready = ref(false)
onMounted(() => {
  ready.value = true
})

const ariaLabel = computed(() => props.ariaLabel || 'Hours driven by month')

const chartData = computed(() => ({
  labels: props.months.map(m => m.label),
  datasets: [
    {
      label: 'Hours',
      data: props.months.map(m => m.hours),
      borderColor: '#A8D5B5',
      backgroundColor: 'rgba(193, 244, 143, 0.28)',
      fill: true,
      tension: 0.35,
      pointRadius: 4,
      pointHoverRadius: 7,
      pointBackgroundColor: '#111118',
      pointBorderColor: '#ffffff',
      pointBorderWidth: 2,
      borderWidth: 2,
    },
  ],
}))

const chartOptions = computed<ChartOptions<'line'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
  interaction: { mode: 'index', intersect: false },
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: '#111118',
      titleFont: { family: 'Manrope', size: 12 },
      bodyFont: { family: 'Manrope', size: 13 },
      padding: 10,
      cornerRadius: 8,
      displayColors: false,
      callbacks: {
        label: (ctx) => {
          const h = ctx.parsed.y ?? 0
          return `${h} hour${h === 1 ? '' : 's'}`
        },
      },
    },
  },
  scales: {
    x: {
      grid: { display: false },
      border: { display: false },
      ticks: {
        color: 'rgba(17,17,24,0.55)',
        font: { size: 11, family: 'Manrope' },
        maxRotation: 0,
      },
    },
    y: {
      beginAtZero: true,
      grid: {
        color: 'rgba(226, 240, 231, 0.9)',
        drawTicks: false,
      },
      border: { display: false },
      ticks: {
        color: 'rgba(17,17,24,0.45)',
        font: { size: 11, family: 'Manrope' },
        precision: 0,
      },
    },
  },
  onClick: (_event: ChartEvent, elements: ActiveElement[]) => {
    if (!elements.length) return
    const idx = elements[0]?.index
    if (idx === undefined) return
    const row = props.months[idx]
    if (!row) return
    emit('select-month', {
      month: row.month,
      label: row.label,
      lesson_ids: row.lesson_ids,
    })
  },
}))
</script>

<style scoped>
.hours__chart {
  height: 200px;
  width: 100%;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
