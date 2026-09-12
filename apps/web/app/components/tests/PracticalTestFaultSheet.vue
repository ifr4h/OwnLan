<template>
  <div class="sheet">
    <section class="ol-panel ol-stack">
      <h2 class="ol-section-title">Result</h2>
      <div class="result-row">
        <button
          type="button"
          class="result-btn"
          :class="{ 'result-btn--on': model.result === 'pass' }"
          data-result="pass"
          @click="model.result = 'pass'"
        >
          Pass
        </button>
        <button
          type="button"
          class="result-btn"
          :class="{ 'result-btn--on': model.result === 'fail' }"
          data-result="fail"
          @click="model.result = 'fail'"
        >
          Fail
        </button>
      </div>

      <label class="ol-field">
        <span class="ol-label">Test date</span>
        <input v-model="model.test_date" class="ol-input" type="date" required>
      </label>

      <label class="ol-field">
        <span class="ol-label">Test centre</span>
        <input v-model="model.test_centre" class="ol-input" type="text" placeholder="e.g. Croydon">
      </label>

      <label class="check">
        <input v-model="model.accompanied" type="checkbox">
        <span>You sat in on the test</span>
      </label>
      <label class="check">
        <input v-model="model.examiner_action" type="checkbox">
        <span>Examiner took physical action</span>
      </label>
      <label v-if="model.result === 'pass' && showMarkPassed" class="check">
        <input v-model="model.mark_learner_passed" type="checkbox">
        <span>Mark {{ pupilFirstName || 'pupil' }} as passed</span>
      </label>
    </section>

    <section class="ol-panel ol-stack">
      <div class="sheet__head">
        <h2 class="ol-section-title">Faults</h2>
        <p class="totals" aria-live="polite">
          {{ drivingTotal }} driving
          <span v-if="seriousTotal"> · {{ seriousTotal }} serious</span>
          <span v-if="dangerousTotal"> · {{ dangerousTotal }} dangerous</span>
        </p>
      </div>
      <p class="ol-meta">
        Tick what was on the sheet. Driving faults can be more than one; serious and dangerous are one each.
      </p>

      <div v-for="area in catalogue?.areas ?? []" :key="area.area" class="area">
        <h3 class="area__title">{{ area.area }}</h3>
        <ul class="fault-list">
          <li v-for="item in area.items" :key="item.code" class="fault-row">
            <div class="fault-row__label">
              <p>{{ item.aspect }}</p>
            </div>
            <div class="fault-row__controls">
              <div class="stepper" aria-label="Driving faults">
                <button type="button" class="stepper__btn" @click="bump(item.code, 'driving', -1)">−</button>
                <span class="stepper__val">{{ ticks[item.code]?.driving ?? 0 }}</span>
                <button type="button" class="stepper__btn" @click="bump(item.code, 'driving', 1)">+</button>
              </div>
              <button
                type="button"
                class="sev"
                :class="{ 'sev--on': (ticks[item.code]?.serious ?? 0) > 0 }"
                data-sev="serious"
                @click="toggle(item.code, 'serious')"
              >
                S
              </button>
              <button
                type="button"
                class="sev"
                :class="{ 'sev--on': (ticks[item.code]?.dangerous ?? 0) > 0 }"
                data-sev="dangerous"
                @click="toggle(item.code, 'dangerous')"
              >
                D
              </button>
            </div>
          </li>
        </ul>
      </div>
    </section>

    <section class="ol-panel ol-stack">
      <label class="ol-field">
        <span class="ol-label">Notes (optional)</span>
        <textarea
          v-model="model.instructor_notes"
          class="ol-textarea"
          rows="3"
          placeholder="Anything useful from the debrief"
        />
      </label>
    </section>
  </div>
</template>

<script setup lang="ts">
import type {
  PracticalCatalogue,
  PracticalFaultType,
  PracticalTest,
  PracticalTestPayload,
  PracticalTestResult,
} from '~/composables/usePracticalTests'

type TickState = Record<string, { driving: number; serious: number; dangerous: number }>

const props = withDefaults(defineProps<{
  catalogue: PracticalCatalogue | null
  pupilFirstName?: string
  showMarkPassed?: boolean
  initial?: PracticalTest | null
  defaultCentre?: string | null
  defaultDate?: string | null
}>(), {
  pupilFirstName: '',
  showMarkPassed: true,
  initial: null,
  defaultCentre: null,
  defaultDate: null,
})

const model = reactive({
  test_date: props.initial?.test_date || props.defaultDate || todayIso(),
  result: (props.initial?.result || 'fail') as PracticalTestResult,
  test_centre: props.initial?.test_centre || props.defaultCentre || '',
  accompanied: props.initial?.accompanied ?? true,
  examiner_action: props.initial?.examiner_action ?? false,
  instructor_notes: props.initial?.instructor_notes || '',
  mark_learner_passed: props.initial?.result === 'pass',
})

const ticks = reactive<TickState>({})

if (props.initial?.faults?.length) {
  for (const f of props.initial.faults) {
    if (!ticks[f.fault_code]) {
      ticks[f.fault_code] = { driving: 0, serious: 0, dangerous: 0 }
    }
    ticks[f.fault_code][f.fault_type] = f.count
  }
}

const drivingTotal = computed(() =>
  Object.values(ticks).reduce((sum, t) => sum + t.driving, 0),
)
const seriousTotal = computed(() =>
  Object.values(ticks).reduce((sum, t) => sum + t.serious, 0),
)
const dangerousTotal = computed(() =>
  Object.values(ticks).reduce((sum, t) => sum + t.dangerous, 0),
)

function ensure(code: string) {
  if (!ticks[code]) ticks[code] = { driving: 0, serious: 0, dangerous: 0 }
}

function bump(code: string, type: 'driving', delta: number) {
  ensure(code)
  ticks[code].driving = Math.max(0, Math.min(99, ticks[code].driving + delta))
}

function toggle(code: string, type: 'serious' | 'dangerous') {
  ensure(code)
  ticks[code][type] = ticks[code][type] > 0 ? 0 : 1
}

function todayIso() {
  const d = new Date()
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function toPayload(learnerId: number): PracticalTestPayload {
  const faults: PracticalTestPayload['faults'] = []
  for (const [code, t] of Object.entries(ticks)) {
    if (t.driving > 0) {
      faults.push({ fault_code: code, fault_type: 'driving' as PracticalFaultType, count: t.driving })
    }
    if (t.serious > 0) {
      faults.push({ fault_code: code, fault_type: 'serious' as PracticalFaultType, count: 1 })
    }
    if (t.dangerous > 0) {
      faults.push({ fault_code: code, fault_type: 'dangerous' as PracticalFaultType, count: 1 })
    }
  }
  return {
    learner_id: learnerId,
    test_date: model.test_date,
    result: model.result,
    test_centre: model.test_centre || null,
    accompanied: model.accompanied,
    examiner_action: model.examiner_action,
    instructor_notes: model.instructor_notes || null,
    mark_learner_passed: model.result === 'pass' && model.mark_learner_passed,
    faults,
  }
}

defineExpose({ toPayload, model, drivingTotal, seriousTotal, dangerousTotal })
</script>

<style scoped>
.sheet {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
}

.result-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--spacing-8);
}

.result-btn {
  min-height: 48px;
  border-radius: 20px;
  border: 1px solid var(--color-driftwood);
  background: var(--color-paper-white);
  color: var(--color-ink-black);
  font: 600 15px/1.2 var(--font-haas-grot-text);
  cursor: pointer;
}

.result-btn--on[data-result='pass'] {
  background: var(--color-ownlane-green);
  border-color: var(--color-ownlane-green);
  color: var(--color-paper-white);
}

.result-btn--on[data-result='fail'] {
  background: var(--color-ink-black);
  border-color: var(--color-ink-black);
  color: var(--color-paper-white);
}

.check {
  display: flex;
  align-items: center;
  gap: var(--spacing-8);
  font: 400 14px/1.4 var(--font-haas-grot-text);
  color: var(--color-bark);
}

.sheet__head {
  display: flex;
  justify-content: space-between;
  gap: var(--spacing-12);
  align-items: baseline;
}

.totals {
  margin: 0;
  font: 600 13px/1.3 var(--font-haas-grot-text);
  color: var(--color-ownlane-green);
}

.area {
  margin-top: var(--spacing-12);
}

.area__title {
  margin: 0 0 var(--spacing-8);
  font: 600 13px/1.3 var(--font-haas-grot-text);
  color: var(--color-coffee-stone);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.fault-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.fault-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-12);
  padding: var(--spacing-10) 0;
  border-bottom: 1px solid var(--color-border);
}

.fault-row__label p {
  margin: 0;
  font: 500 14px/1.3 var(--font-haas-grot-text);
  color: var(--color-ink-black);
}

.fault-row__controls {
  display: flex;
  align-items: center;
  gap: var(--spacing-6);
  flex-shrink: 0;
}

.stepper {
  display: inline-flex;
  align-items: center;
  border: 1px solid var(--color-driftwood);
  border-radius: 20px;
  overflow: hidden;
  background: var(--color-paper-white);
}

.stepper__btn {
  width: 32px;
  height: 32px;
  border: 0;
  background: transparent;
  cursor: pointer;
  font: 600 16px/1 var(--font-haas-grot-text);
  color: var(--color-ink-black);
}

.stepper__val {
  min-width: 22px;
  text-align: center;
  font: 600 13px/1 var(--font-haas-grot-text);
}

.sev {
  width: 32px;
  height: 32px;
  border-radius: 999px;
  border: 1px solid var(--color-driftwood);
  background: var(--color-paper-white);
  font: 700 12px/1 var(--font-haas-grot-text);
  color: var(--color-muted);
  cursor: pointer;
}

.sev--on[data-sev='serious'] {
  background: var(--color-warning);
  border-color: var(--color-warning);
  color: var(--color-paper-white);
}

.sev--on[data-sev='dangerous'] {
  background: var(--color-danger);
  border-color: var(--color-danger);
  color: var(--color-paper-white);
}
</style>
