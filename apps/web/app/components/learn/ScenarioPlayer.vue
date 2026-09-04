<script setup lang="ts">
import { getTemplatePack, drawRoadShapes } from '~/utils/teaching/templates'
import { prefersReducedMotion } from '~/utils/portalFormat'

export type ScenarioOption = {
  id: string
  label: string
  correct?: boolean
  feedback?: string
}

export type ScenarioStep = {
  id: string
  prompt: string
  mode: 'choice' | 'explain' | 'select_lane'
  options?: ScenarioOption[]
  explanation?: string
}

const props = withDefaults(
  defineProps<{
    template: string
    title?: string
    steps: ScenarioStep[]
  }>(),
  {
    title: '',
  },
)

const emit = defineEmits<{
  completed: [results: Array<{ step_id: string; option_id?: string; correct?: boolean }>]
}>()

const LOGICAL = 1000
const canvasEl = ref<HTMLCanvasElement | null>(null)
const stepIndex = ref(0)
const selectedId = ref<string | null>(null)
const feedback = ref('')
const answered = ref(false)
const explainDone = ref(false)
const results = ref<Array<{ step_id: string; option_id?: string; correct?: boolean }>>([])
const carProgress = ref(0)
let raf = 0

const step = computed(() => props.steps[stepIndex.value] ?? null)
const isLast = computed(() => stepIndex.value >= props.steps.length - 1)
const canAdvance = computed(() => {
  if (!step.value) return false
  if (step.value.mode === 'explain') return explainDone.value
  return answered.value
})

function roundRect(
  ctx: CanvasRenderingContext2D,
  x: number,
  y: number,
  w: number,
  h: number,
  r: number,
) {
  ctx.beginPath()
  ctx.moveTo(x + r, y)
  ctx.arcTo(x + w, y, x + w, y + h, r)
  ctx.arcTo(x + w, y + h, x, y + h, r)
  ctx.arcTo(x, y + h, x, y, r)
  ctx.arcTo(x, y, x + w, y, r)
  ctx.closePath()
}

function drawCar(ctx: CanvasRenderingContext2D, t: number) {
  const x = 180 + t * 640
  const y = 500
  ctx.save()
  ctx.translate(x, y)
  ctx.fillStyle = '#111118'
  roundRect(ctx, -18, -28, 36, 56, 8)
  ctx.fill()
  ctx.fillStyle = '#E2F0E7'
  ctx.fillRect(-12, -20, 24, 14)
  ctx.restore()
}

function paint() {
  const canvas = canvasEl.value
  if (!canvas) return
  const ctx = canvas.getContext('2d')
  if (!ctx) return

  const dpr = typeof window !== 'undefined' ? window.devicePixelRatio || 1 : 1
  const cssW = canvas.clientWidth || 320
  const cssH = canvas.clientHeight || 240
  canvas.width = Math.round(cssW * dpr)
  canvas.height = Math.round(cssH * dpr)
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0)

  const scale = Math.min(cssW, cssH) / LOGICAL
  const ox = (cssW - LOGICAL * scale) / 2
  const oy = (cssH - LOGICAL * scale) / 2

  ctx.clearRect(0, 0, cssW, cssH)
  ctx.fillStyle = '#E2F0E7'
  ctx.fillRect(0, 0, cssW, cssH)

  ctx.save()
  ctx.translate(ox, oy)
  ctx.scale(scale, scale)

  const pack = getTemplatePack(props.template || 'blank')
  drawRoadShapes(ctx, pack.shapes, 1, 1)
  drawCar(ctx, carProgress.value)

  ctx.restore()
}

function animateCarTo(target: number) {
  cancelAnimationFrame(raf)
  if (prefersReducedMotion()) {
    carProgress.value = target
    paint()
    return
  }
  const from = carProgress.value
  const start = performance.now()
  const dur = 420
  const tick = (now: number) => {
    const p = Math.min(1, (now - start) / dur)
    const eased = 1 - (1 - p) ** 3
    carProgress.value = from + (target - from) * eased
    paint()
    if (p < 1) raf = requestAnimationFrame(tick)
  }
  raf = requestAnimationFrame(tick)
}

function onSelect(option: ScenarioOption) {
  if (answered.value || !step.value) return
  selectedId.value = option.id
  answered.value = true
  feedback.value = option.feedback || (option.correct ? 'That’s right.' : 'Not quite — try again.')
  results.value.push({
    step_id: step.value.id,
    option_id: option.id,
    correct: !!option.correct,
  })
  if (option.correct) {
    animateCarTo(Math.min(1, (stepIndex.value + 1) / Math.max(1, props.steps.length)))
  }
}

function onExplainContinue() {
  if (!step.value || explainDone.value) return
  explainDone.value = true
  results.value.push({ step_id: step.value.id })
  animateCarTo(Math.min(1, (stepIndex.value + 1) / Math.max(1, props.steps.length)))
}

function onRetry() {
  if (!step.value || step.value.mode === 'explain') return
  selectedId.value = null
  answered.value = false
  feedback.value = ''
  results.value = results.value.filter(r => r.step_id !== step.value!.id)
}

function onNext() {
  if (!canAdvance.value) return
  if (isLast.value) {
    emit('completed', [...results.value])
    return
  }
  stepIndex.value += 1
  selectedId.value = null
  answered.value = false
  feedback.value = ''
  explainDone.value = false
}

watch(
  () => [props.template, props.steps] as const,
  () => {
    stepIndex.value = 0
    selectedId.value = null
    answered.value = false
    feedback.value = ''
    explainDone.value = false
    results.value = []
    carProgress.value = 0
    nextTick(paint)
  },
  { deep: true },
)

onMounted(() => {
  paint()
  if (typeof window !== 'undefined') {
    window.addEventListener('resize', paint)
  }
})

onBeforeUnmount(() => {
  cancelAnimationFrame(raf)
  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', paint)
  }
})
</script>

<template>
  <section class="scenario" :aria-label="title || 'Interactive scenario'">
    <header v-if="title" class="scenario__head">
      <h3 class="scenario__title">{{ title }}</h3>
      <p class="scenario__progress" aria-live="polite">
        Step {{ stepIndex + 1 }} of {{ steps.length }}
      </p>
    </header>

    <div class="scenario__board">
      <canvas ref="canvasEl" class="scenario__canvas" role="img" :aria-label="title || 'Road scene'" />
    </div>

    <div v-if="step" class="scenario__panel">
      <p class="scenario__prompt">{{ step.prompt }}</p>

      <template v-if="step.mode === 'explain'">
        <p v-if="step.explanation" class="scenario__explain">{{ step.explanation }}</p>
        <button
          v-if="!explainDone"
          type="button"
          class="scenario__btn scenario__btn--primary"
          @click="onExplainContinue"
        >
          Got it
        </button>
      </template>

      <template v-else>
        <div class="scenario__options" role="group" :aria-label="'Choices'">
          <button
            v-for="opt in step.options || []"
            :key="opt.id"
            type="button"
            class="scenario__option"
            :class="{
              'scenario__option--picked': selectedId === opt.id,
              'scenario__option--ok': answered && selectedId === opt.id && opt.correct,
              'scenario__option--bad': answered && selectedId === opt.id && !opt.correct,
            }"
            :disabled="answered"
            :aria-pressed="selectedId === opt.id"
            @click="onSelect(opt)"
          >
            <span class="scenario__option-label">{{ opt.label }}</span>
            <span
              v-if="answered && selectedId === opt.id"
              class="scenario__option-mark"
              aria-hidden="true"
            >
              {{ opt.correct ? '✓' : '✗' }}
            </span>
          </button>
        </div>
      </template>

      <p
        v-if="feedback"
        class="scenario__feedback"
        :class="{ 'scenario__feedback--ok': results.at(-1)?.correct }"
        role="status"
      >
        {{ feedback }}
      </p>

      <div class="scenario__actions">
        <button
          v-if="answered && step.mode !== 'explain' && results.at(-1) && !results.at(-1)!.correct"
          type="button"
          class="scenario__btn scenario__btn--ghost"
          @click="onRetry"
        >
          Try again
        </button>
        <button
          v-if="canAdvance"
          type="button"
          class="scenario__btn scenario__btn--primary"
          @click="onNext"
        >
          {{ isLast ? 'Finish' : 'Next' }}
          <span aria-hidden="true">→</span>
        </button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.scenario {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
  padding: var(--spacing-16);
  background: var(--color-frost-green);
  border-radius: var(--radius-panel);
}

.scenario__head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--spacing-8);
}

.scenario__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
  letter-spacing: var(--tracking-heading-sm);
  margin: 0;
}

.scenario__progress {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
  margin: 0;
}

.scenario__board {
  border-radius: var(--radius-small);
  overflow: hidden;
  background: var(--color-soft-sage);
}

.scenario__canvas {
  display: block;
  width: 100%;
  height: min(42vw, 280px);
  min-height: 200px;
}

.scenario__panel {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.scenario__prompt {
  font-size: var(--text-body);
  line-height: var(--leading-body);
  margin: 0;
  max-width: 42ch;
}

.scenario__explain {
  font-size: var(--text-body-sm);
  line-height: var(--leading-body-sm);
  margin: 0;
  padding: var(--spacing-12);
  background: var(--color-paper-white);
  border-radius: var(--radius-small);
  border-left: 4px solid var(--color-ownlane-green);
}

.scenario__options {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.scenario__option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-12);
  min-height: 52px;
  padding: 12px 16px;
  text-align: left;
  border: 2px solid transparent;
  border-radius: 18px;
  background: var(--color-paper-white);
  color: var(--color-ink-black);
  font: inherit;
  font-size: var(--text-body-sm);
  cursor: pointer;
}

.scenario__option:hover:not(:disabled),
.scenario__option:focus-visible {
  border-color: var(--color-ownlane-green);
  outline: none;
}

.scenario__option:disabled {
  cursor: default;
}

.scenario__option--ok {
  border-color: var(--color-ownlane-green);
  background: var(--color-success-wash);
}

.scenario__option--bad {
  border-color: var(--color-marker-red);
  background: color-mix(in srgb, var(--color-marker-red) 10%, white);
}

.scenario__option-mark {
  font-weight: 700;
  flex-shrink: 0;
}

.scenario__feedback {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-ink-black);
}

.scenario__feedback--ok {
  color: var(--color-ownlane-green);
}

.scenario__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
}

.scenario__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 48px;
  padding: 10px 18px;
  border: none;
  border-radius: var(--radius-buttons);
  font: inherit;
  font-size: var(--text-body-sm);
  cursor: pointer;
}

.scenario__btn--primary {
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  box-shadow: var(--shadow-button);
}

.scenario__btn--ghost {
  background: transparent;
  color: var(--color-ink-black);
  border: 1px solid var(--color-border);
}

@media (prefers-reduced-motion: reduce) {
  .scenario__option,
  .scenario__btn {
    transition: none;
  }
}
</style>
