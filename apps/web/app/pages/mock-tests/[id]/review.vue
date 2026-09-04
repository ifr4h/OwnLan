<template>
  <section class="ol-page review">
    <NuxtLink :to="backLink" class="ol-back">← Back</NuxtLink>

    <p v-if="loading" class="ol-muted">Loading…</p>
    <p v-else-if="error" class="ol-error" role="alert">{{ error }}</p>

    <template v-else-if="mock">
      <header class="review__header">
        <p class="ol-eyebrow">Mock test review</p>
        <h1 class="ol-page-title">{{ mock.learner_name }}</h1>
        <p class="ol-meta">{{ mock.date_display }} · {{ mock.elapsed_display }}</p>
        <p class="review__result" :data-result="mock.result">{{ mock.result_label }}</p>
      </header>

      <section class="ol-panel">
        <dl class="facts">
          <div class="facts__row">
            <dt>Driving faults</dt>
            <dd>{{ mock.driving_faults_count }}</dd>
          </div>
          <div class="facts__row">
            <dt>Serious</dt>
            <dd>{{ mock.serious_faults_count }}</dd>
          </div>
          <div class="facts__row">
            <dt>Dangerous</dt>
            <dd>{{ mock.dangerous_faults_count }}</dd>
          </div>
        </dl>
      </section>

      <section v-for="group in mock.fault_summary" :key="group.area" class="ol-panel">
        <h2 class="panel__title">{{ group.area }}</h2>
        <ul class="fault-list">
          <li v-for="item in group.items" :key="item.label" class="fault-list__item">
            <p class="fault-list__label">{{ item.label }}</p>
            <p class="fault-list__counts">
              <span v-if="item.driving">{{ item.driving }} driving</span>
              <span v-if="item.serious">{{ item.serious }} serious</span>
              <span v-if="item.dangerous">{{ item.dangerous }} dangerous</span>
            </p>
            <NuxtLink
              v-if="item.skill_code"
              :to="`/teaching?skill=${item.skill_code}`"
              class="ol-link-action"
            >
              Explain this
            </NuxtLink>
          </li>
        </ul>
      </section>

      <section class="ol-panel">
        <h2 class="panel__title">Timeline</h2>
        <ol class="timeline">
          <li v-for="fault in mock.faults" :key="fault.id" class="timeline__item">
            <time class="timeline__time">{{ fault.time_display }}</time>
            <p class="timeline__label">{{ fault.fault_label }}</p>
            <p class="timeline__type">{{ fault.fault_type_label }}</p>
            <label v-if="!fault.note" class="note-add">
              <span class="sr-only">Add note for {{ fault.fault_label }}</span>
              <input
                v-model="noteDrafts[fault.id]"
                class="ol-input"
                type="text"
                placeholder="Add note"
                @keydown.enter.prevent="saveNote(fault.id)"
              >
            </label>
            <p v-else class="timeline__note">{{ fault.note }}</p>
          </li>
        </ol>
      </section>

      <section v-if="suggestions.length" class="ol-panel">
        <h2 class="panel__title">Possible next focus</h2>
        <ul class="suggestions">
          <li v-for="s in suggestions" :key="s.label" class="suggestions__item">
            <p class="suggestions__label">{{ s.label }}</p>
            <p class="ol-meta">{{ s.reason }}</p>
            <button
              type="button"
              class="ol-btn ol-btn--sm"
              :disabled="applying === s.label"
              @click="applyFocus(s.label)"
            >
              Add as next focus
            </button>
          </li>
        </ul>
      </section>

      <label class="ol-field">
        <span class="ol-field__label">Note for {{ mock.learner_first_name }} (visible on recap)</span>
        <textarea v-model="instructorNote" class="ol-input" rows="3" />
      </label>

      <button class="ol-btn" type="button" :disabled="saving" @click="saveNotes">
        {{ saving ? 'Saving…' : 'Save notes' }}
      </button>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { MockTest } from '~/composables/useMockTest'

const route = useRoute()
const id = computed(() => Number(route.params.id))
const { getMock, applyNextFocus } = useMockTest()

const mock = ref<MockTest | null>(null)
const loading = ref(true)
const error = ref('')
const instructorNote = ref('')
const noteDrafts = ref<Record<number, string>>({})
const saving = ref(false)
const applying = ref('')

const suggestions = computed(() => mock.value?.suggested_next_focus_options ?? [])

const backLink = computed(() =>
  mock.value?.lesson_id ? `/lessons/${mock.value.lesson_id}` : '/today',
)

async function load() {
  loading.value = true
  try {
    mock.value = await getMock(id.value)
    instructorNote.value = mock.value.instructor_note ?? ''
  } catch (e) {
    error.value = extractApiError(e, 'Could not load mock.')
  } finally {
    loading.value = false
  }
}

async function saveNote(faultId: number) {
  const note = noteDrafts.value[faultId]?.trim()
  if (!note) return
  await apiFetch(`/mock-tests/${id.value}/faults/${faultId}`, {
    method: 'PATCH',
    body: { note },
  })
  await load()
}

async function saveNotes() {
  saving.value = true
  try {
    await apiFetch(`/mock-tests/${id.value}`, {
      method: 'PATCH',
      body: { instructor_note: instructorNote.value },
    })
  } finally {
    saving.value = false
  }
}

async function applyFocus(label: string) {
  applying.value = label
  try {
    await applyNextFocus(id.value, label)
  } finally {
    applying.value = ''
  }
}

onMounted(() => { void load() })
</script>

<style scoped>
.review__result {
  font-weight: 600;
  font-size: var(--text-body-lg);
  margin-top: var(--spacing-8);
}

.review__result[data-result='pass_standard'] {
  color: var(--color-ownlane-green);
}

.facts__row {
  display: flex;
  justify-content: space-between;
  padding: var(--spacing-8) 0;
  border-bottom: 1px solid var(--color-frost-green);
}

.panel__title {
  font-size: var(--text-body-lg);
  margin-bottom: var(--spacing-12);
}

.fault-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.fault-list__item {
  padding: var(--spacing-12) 0;
  border-bottom: 1px solid var(--color-frost-green);
}

.fault-list__counts {
  font-size: var(--text-body-sm);
  opacity: 0.75;
}

.fault-list__counts span + span::before {
  content: ' · ';
}

.timeline {
  list-style: none;
  padding: 0;
  margin: 0;
}

.timeline__item {
  padding: var(--spacing-12) 0;
  border-bottom: 1px solid var(--color-frost-green);
}

.timeline__time {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  opacity: 0.7;
}

.timeline__type {
  font-size: var(--text-body-sm);
  opacity: 0.75;
}

.suggestions__item {
  padding: var(--spacing-12) 0;
  border-bottom: 1px solid var(--color-frost-green);
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
}
</style>
