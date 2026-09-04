<template>
  <div class="studio" :data-phase="phase">
    <header class="studio__header">
      <p class="studio__eyebrow">Mock test</p>
      <h1 class="studio__name">{{ mock?.learner_first_name || 'Pupil' }}</h1>
      <p class="studio__timer" aria-live="polite">{{ elapsedDisplay }}</p>
    </header>

    <section class="studio__totals" aria-label="Fault totals">
      <div class="total">
        <span class="total__label">Driving faults</span>
        <span class="total__value">{{ mock?.driving_faults_count ?? 0 }}</span>
      </div>
      <div class="total" data-severity="serious">
        <span class="total__label">Serious</span>
        <span class="total__value">{{ mock?.serious_faults_count ?? 0 }}</span>
      </div>
      <div class="total" data-severity="dangerous">
        <span class="total__label">Dangerous</span>
        <span class="total__value">{{ mock?.dangerous_faults_count ?? 0 }}</span>
      </div>
    </section>

  <p v-if="lastRecorded" class="studio__last" role="status">
      {{ lastRecorded.fault_type_label }} recorded
      <span class="studio__last-label">{{ lastRecorded.fault_label }}</span>
      <button type="button" class="studio__undo" @click="onUndoLast">Undo</button>
    </p>

    <div v-if="phase === 'capture'" class="studio__capture">
      <p class="studio__step-label">Fault type</p>
      <div class="severity-row">
        <button
          v-for="t in faultTypes"
          :key="t.code"
          type="button"
          class="severity-btn"
          :data-type="t.code"
          :class="{ 'severity-btn--active': selectedType === t.code }"
          @click="selectedType = t.code"
        >
          {{ t.label }}
        </button>
      </div>

      <template v-if="selectedType">
        <p class="studio__step-label">Category</p>
        <div v-if="recentForType.length" class="recent">
          <p class="recent__title">Recent</p>
          <button
            v-for="item in recentForType"
            :key="item.code + item.fault_type"
            type="button"
            class="recent__btn"
            @click="recordQuick(item.fault_code, item.fault_type)"
          >
            <span>{{ item.fault_label }}</span>
            <span class="recent__again">+ again</span>
          </button>
        </div>
        <div class="areas">
          <button
            v-for="area in catalogue?.areas ?? []"
            :key="area.area"
            type="button"
            class="area-btn"
            @click="openArea(area)"
          >
            {{ area.area }}
          </button>
        </div>
      </template>
    </div>

    <div v-else-if="phase === 'area'" class="studio__area">
      <button type="button" class="studio__back" @click="phase = 'capture'">← Back</button>
      <p class="studio__step-label">{{ activeArea?.area }}</p>
      <button
        v-for="item in activeArea?.items ?? []"
        :key="item.code"
        type="button"
        class="fault-btn"
        @click="recordFault(item.code)"
      >
        {{ item.aspect }}
      </button>
    </div>

    <footer class="studio__footer">
      <button type="button" class="studio__finish" @click="showFinishConfirm = true">
        Finish mock
      </button>
    </footer>

    <div v-if="showFinishConfirm" class="dialog" role="dialog" aria-labelledby="finish-title">
      <h2 id="finish-title" class="dialog__title">Finish mock test?</h2>
      <p class="dialog__meta">{{ elapsedDisplay }}</p>
      <ul class="dialog__summary">
        <li>{{ mock?.driving_faults_count ?? 0 }} driving faults</li>
        <li>{{ mock?.serious_faults_count ?? 0 }} serious</li>
        <li>{{ mock?.dangerous_faults_count ?? 0 }} dangerous</li>
      </ul>
      <div class="dialog__actions">
        <button type="button" class="dialog__secondary" @click="showFinishConfirm = false">
          Continue mock
        </button>
        <button type="button" class="dialog__primary" :disabled="finishing" @click="onFinish">
          {{ finishing ? 'Finishing…' : 'Finish' }}
        </button>
      </div>
    </div>

    <p v-if="error" class="studio__error" role="alert">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
import {
  newClientOpId,
  newClientSessionId,
  type MockFault,
  type MockFaultType,
  type MockTest,
} from '~/composables/useMockTest'

definePageMeta({ layout: false })
useHead({ title: 'Mock test · OwnLane' })

const route = useRoute()
const lessonId = computed(() => Number(route.params.id))

const {
  fetchCatalogue,
  startForLesson,
  getMock,
  recordFault: apiRecordFault,
  undoFault,
  finishMock,
} = useMockTest()

const catalogue = ref<Awaited<ReturnType<typeof fetchCatalogue>> | null>(null)
const mock = ref<MockTest | null>(null)
const sessionId = ref(newClientSessionId())
const error = ref('')
const phase = ref<'capture' | 'area'>('capture')
const selectedType = ref<MockFaultType | ''>('')
const activeArea = ref<{ area: string; items: Array<{ code: string; aspect: string }> } | null>(null)
const lastRecorded = ref<MockFault | null>(null)
const showFinishConfirm = ref(false)
const finishing = ref(false)
const startedAtMs = ref(0)
const nowMs = ref(Date.now())
let tick: ReturnType<typeof setInterval> | null = null

const faultTypes = computed(() => catalogue.value?.fault_types ?? [])

const elapsedDisplay = computed(() => {
  if (!startedAtMs.value) return '0:00'
  const secs = Math.max(0, Math.floor((nowMs.value - startedAtMs.value) / 1000))
  const m = Math.floor(secs / 60)
  const s = secs % 60
  return `${m}:${String(s).padStart(2, '0')}`
})

const recentForType = computed(() => {
  if (!mock.value?.recent_faults || !selectedType.value) return []
  const seen = new Set<string>()
  const out: Array<{ fault_code: string; fault_type: MockFaultType; fault_label: string; code: string }> = []
  for (const f of mock.value.recent_faults) {
    if (f.fault_type !== selectedType.value) continue
    const key = f.fault_code
    if (seen.has(key)) continue
    seen.add(key)
    out.push({
      fault_code: f.fault_code,
      fault_type: f.fault_type,
      fault_label: f.fault_label,
      code: key,
    })
    if (out.length >= 4) break
  }
  return out
})

function openArea(area: { area: string; items: Array<{ code: string; aspect: string }> }) {
  activeArea.value = area
  phase.value = 'area'
}

async function load() {
  error.value = ''
  try {
    catalogue.value = await fetchCatalogue()
    mock.value = await startForLesson(lessonId.value, sessionId.value)
    startedAtMs.value = Date.parse(mock.value.started_at + 'Z')
  } catch (e) {
    error.value = extractApiError(e, 'Could not start mock test.')
  }
}

async function recordFault(faultCode: string) {
  if (!mock.value || !selectedType.value) return
  error.value = ''
  try {
    const fault = await apiRecordFault(mock.value.id, {
      fault_type: selectedType.value,
      fault_code: faultCode,
      client_op_id: newClientOpId(),
    })
    lastRecorded.value = fault
    mock.value = await getMock(mock.value.id)
    phase.value = 'capture'
    activeArea.value = null
  } catch (e) {
    error.value = extractApiError(e, 'Could not record fault.')
  }
}

async function recordQuick(faultCode: string, faultType: MockFaultType) {
  selectedType.value = faultType
  await recordFault(faultCode)
}

async function onUndoLast() {
  if (!mock.value || !lastRecorded.value) return
  try {
    await undoFault(mock.value.id, lastRecorded.value.id)
    lastRecorded.value = null
    mock.value = await getMock(mock.value.id)
  } catch (e) {
    error.value = extractApiError(e, 'Could not undo.')
  }
}

async function onFinish() {
  if (!mock.value) return
  finishing.value = true
  try {
    const result = await finishMock(mock.value.id)
    await navigateTo(`/mock-tests/${result.id}/review`)
  } catch (e) {
    error.value = extractApiError(e, 'Could not finish mock.')
    finishing.value = false
  }
}

onMounted(() => {
  void load()
  tick = setInterval(() => { nowMs.value = Date.now() }, 1000)
})

onUnmounted(() => {
  if (tick) clearInterval(tick)
})
</script>

<style scoped>
.studio {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  background: var(--color-paper-white, #faf9f6);
  padding: env(safe-area-inset-top) var(--spacing-16) env(safe-area-inset-bottom);
  gap: var(--spacing-16);
}

.studio__header {
  padding-top: var(--spacing-16);
}

.studio__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  text-transform: uppercase;
  opacity: 0.6;
  margin: 0;
}

.studio__name {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-md);
  margin: var(--spacing-4) 0;
}

.studio__timer {
  font-family: var(--font-martian-mono);
  font-size: 2rem;
  margin: 0;
}

.studio__totals {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--spacing-8);
}

.total {
  background: var(--color-frost-green, #e8f0e8);
  border-radius: var(--radius-small);
  padding: var(--spacing-12);
  text-align: center;
}

.total[data-severity='serious'] {
  background: #fff4e0;
}

.total[data-severity='dangerous'] {
  background: #ffe8e8;
}

.total__label {
  display: block;
  font-size: var(--text-body-sm);
  opacity: 0.75;
}

.total__value {
  display: block;
  font-size: 1.75rem;
  font-weight: 600;
}

.studio__last {
  font-size: var(--text-body-sm);
  padding: var(--spacing-12);
  background: var(--surface-card, #fff);
  border-radius: var(--radius-small);
  border: 1px solid var(--color-frost-green);
}

.studio__last-label {
  display: block;
  font-weight: 600;
  margin-top: var(--spacing-4);
}

.studio__undo {
  margin-top: var(--spacing-8);
  padding: var(--spacing-8) var(--spacing-16);
  border: 1px solid var(--color-ownlane-green);
  border-radius: var(--radius-buttons);
  background: transparent;
  color: var(--color-ownlane-green);
  min-height: 44px;
}

.studio__capture,
.studio__area {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.studio__step-label {
  font-size: var(--text-body-sm);
  opacity: 0.7;
  margin: 0;
}

.severity-row {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.severity-btn {
  min-height: 52px;
  padding: var(--spacing-12) var(--spacing-16);
  border: 2px solid var(--color-frost-green);
  border-radius: var(--radius-buttons);
  background: var(--surface-card, #fff);
  font-size: var(--text-body);
  text-align: left;
}

.severity-btn--active {
  border-color: var(--color-ownlane-green);
  background: var(--color-frost-green);
}

.severity-btn[data-type='serious'].severity-btn--active {
  border-color: #c87800;
  background: #fff4e0;
}

.severity-btn[data-type='dangerous'].severity-btn--active {
  border-color: var(--color-marker-red);
  background: #ffe8e8;
}

.recent__title {
  font-size: var(--text-body-sm);
  opacity: 0.7;
}

.recent__btn {
  display: flex;
  justify-content: space-between;
  width: 100%;
  min-height: 48px;
  padding: var(--spacing-12);
  margin-bottom: var(--spacing-8);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-buttons);
  background: var(--surface-card);
  text-align: left;
}

.recent__again {
  color: var(--color-ownlane-green);
  font-size: var(--text-body-sm);
}

.areas {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--spacing-8);
}

.area-btn,
.fault-btn {
  min-height: 52px;
  padding: var(--spacing-12);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-buttons);
  background: var(--surface-card);
  font-size: var(--text-body-sm);
}

.studio__back {
  align-self: flex-start;
  background: none;
  border: none;
  color: var(--color-ownlane-green);
  padding: var(--spacing-8) 0;
  min-height: 44px;
}

.studio__footer {
  padding-bottom: var(--spacing-16);
}

.studio__finish {
  width: 100%;
  min-height: 52px;
  border: none;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  font-size: var(--text-body);
}

.studio__error {
  color: var(--color-marker-red);
}

.dialog {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding: var(--spacing-16);
  z-index: 100;
}

.dialog__title,
.dialog__meta,
.dialog__summary {
  margin: 0 0 var(--spacing-8);
}

.dialog > * {
  background: var(--surface-card);
  border-radius: var(--radius-cards);
  padding: var(--spacing-24);
  width: 100%;
  max-width: 400px;
}

.dialog__actions {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
  margin-top: var(--spacing-16);
}

.dialog__primary,
.dialog__secondary {
  min-height: 48px;
  border-radius: var(--radius-buttons);
  border: none;
}

.dialog__primary {
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
}

.dialog__secondary {
  background: transparent;
  border: 1px solid var(--color-frost-green);
}
</style>
