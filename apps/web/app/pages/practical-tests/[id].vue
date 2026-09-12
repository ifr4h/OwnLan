<template>
  <section class="ol-page">
    <NuxtLink
      :to="test ? `/pupils/${test.learner_id}/tests` : '/accounts/test-faults'"
      class="ol-back"
    >
      ← Back
    </NuxtLink>

    <p v-if="loading" class="ol-muted">Loading…</p>
    <p v-else-if="error" class="ol-error" role="alert">{{ error }}</p>

    <template v-else-if="test && !editing">
      <header class="ol-page-header head">
        <div>
          <p class="ol-eyebrow">Practical test</p>
          <h1 class="ol-page-title">{{ test.learner_name }}</h1>
          <p class="ol-meta">{{ test.date_display }}{{ test.test_centre ? ` · ${test.test_centre}` : '' }}</p>
        </div>
        <span class="badge" :data-result="test.result">{{ test.result_label }}</span>
      </header>

      <section class="kpi-grid">
        <article class="ol-card ol-card--flat">
          <p class="ol-eyebrow">Driving faults</p>
          <p class="ol-metric">{{ test.driving_faults_count }}</p>
        </article>
        <article class="ol-card ol-card--flat">
          <p class="ol-eyebrow">Serious</p>
          <p class="ol-metric">{{ test.serious_faults_count }}</p>
        </article>
        <article class="ol-card ol-card--flat">
          <p class="ol-eyebrow">Dangerous</p>
          <p class="ol-metric">{{ test.dangerous_faults_count }}</p>
        </article>
      </section>

      <section class="ol-panel ol-stack">
        <h2 class="ol-section-title">Details</h2>
        <dl class="facts">
          <div class="facts__row">
            <dt>Accompanied</dt>
            <dd>{{ test.accompanied ? 'Yes' : 'No' }}</dd>
          </div>
          <div class="facts__row">
            <dt>Examiner action</dt>
            <dd>{{ test.examiner_action ? 'Yes' : 'No' }}</dd>
          </div>
        </dl>
        <p v-if="test.instructor_notes" class="notes">{{ test.instructor_notes }}</p>
      </section>

      <section v-if="test.faults?.length" class="ol-panel ol-stack">
        <h2 class="ol-section-title">Faults marked</h2>
        <ul class="faults">
          <li v-for="f in test.faults" :key="`${f.fault_code}-${f.fault_type}`" class="fault">
            <span>{{ f.fault_label }}</span>
            <strong>
              <template v-if="f.fault_type === 'driving'">{{ f.count }} DF</template>
              <template v-else-if="f.fault_type === 'serious'">Serious</template>
              <template v-else>Dangerous</template>
            </strong>
          </li>
        </ul>
      </section>

      <div class="actions">
        <button type="button" class="ol-btn" @click="startEdit">Edit</button>
        <NuxtLink to="/accounts/test-faults" class="ol-btn ol-btn--ghost">Fault trends</NuxtLink>
        <button type="button" class="ol-btn ol-btn--ghost danger" :disabled="deleting" @click="onDelete">
          {{ deleting ? 'Deleting…' : 'Delete' }}
        </button>
      </div>
    </template>

    <template v-else-if="test && editing && catalogue">
      <header class="ol-page-header">
        <h1 class="ol-page-title">Edit test result</h1>
      </header>
      <PracticalTestFaultSheet
        ref="sheet"
        :catalogue="catalogue"
        :initial="test"
        :pupil-first-name="test.learner_first_name || ''"
        :show-mark-passed="false"
      />
      <p v-if="saveError" class="ol-error" role="alert">{{ saveError }}</p>
      <div class="actions">
        <button type="button" class="ol-btn" :disabled="saving" @click="onSave">
          {{ saving ? 'Saving…' : 'Save changes' }}
        </button>
        <button type="button" class="ol-btn ol-btn--ghost" @click="editing = false">Cancel</button>
      </div>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { PracticalCatalogue, PracticalTest } from '~/composables/usePracticalTests'
import PracticalTestFaultSheet from '~/components/tests/PracticalTestFaultSheet.vue'

const route = useRoute()
const id = computed(() => Number(route.params.id))
const { view, update, remove, fetchCatalogue } = usePracticalTests()

useHead({ title: 'Practical test · OwnLane' })

const test = ref<PracticalTest | null>(null)
const catalogue = ref<PracticalCatalogue | null>(null)
const sheet = ref<InstanceType<typeof PracticalTestFaultSheet> | null>(null)
const loading = ref(true)
const error = ref('')
const saveError = ref('')
const editing = ref(false)
const saving = ref(false)
const deleting = ref(false)

async function load() {
  loading.value = true
  error.value = ''
  try {
    test.value = await view(id.value)
  } catch (e) {
    error.value = extractApiError(e, 'Could not load that test result.')
  } finally {
    loading.value = false
  }
}

async function startEdit() {
  saveError.value = ''
  if (!catalogue.value) {
    catalogue.value = await fetchCatalogue()
  }
  editing.value = true
}

async function onSave() {
  if (!sheet.value || !test.value) return
  saving.value = true
  saveError.value = ''
  try {
    const payload = sheet.value.toPayload(test.value.learner_id)
    test.value = await update(test.value.id, payload)
    editing.value = false
  } catch (e) {
    saveError.value = extractApiError(e, 'Could not save changes.')
  } finally {
    saving.value = false
  }
}

async function onDelete() {
  if (!test.value) return
  if (!confirm('Delete this test result? It will drop out of your fault trends.')) return
  deleting.value = true
  try {
    const learnerId = test.value.learner_id
    await remove(test.value.id)
    await navigateTo(`/pupils/${learnerId}/tests`)
  } catch (e) {
    error.value = extractApiError(e, 'Could not delete that result.')
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.head {
  display: flex;
  justify-content: space-between;
  gap: var(--spacing-12);
  align-items: flex-start;
}

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--spacing-8);
  margin-bottom: var(--spacing-16);
}

.badge {
  display: inline-flex;
  padding: 4px 12px;
  border-radius: 999px;
  font: 600 13px/1.4 var(--font-haas-grot-text);
}

.badge[data-result='pass'] {
  background: var(--color-success-wash);
  color: var(--color-success);
}

.badge[data-result='fail'] {
  background: var(--color-danger-wash);
  color: var(--color-danger);
}

.facts {
  margin: 0;
}

.facts__row {
  display: flex;
  justify-content: space-between;
  gap: var(--spacing-12);
  padding: var(--spacing-8) 0;
  border-bottom: 1px solid var(--color-border);
}

.facts__row dt {
  color: var(--color-muted);
}

.facts__row dd {
  margin: 0;
  font-weight: 600;
}

.notes {
  margin: var(--spacing-12) 0 0;
  white-space: pre-wrap;
  color: var(--color-bark);
}

.faults {
  list-style: none;
  margin: 0;
  padding: 0;
}

.fault {
  display: flex;
  justify-content: space-between;
  gap: var(--spacing-12);
  padding: var(--spacing-8) 0;
  border-bottom: 1px solid var(--color-border);
  font: 400 14px/1.3 var(--font-haas-grot-text);
}

.actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
  margin-top: var(--spacing-16);
}

.danger {
  color: var(--color-danger);
}

@media (max-width: 560px) {
  .kpi-grid {
    grid-template-columns: 1fr;
  }
}
</style>
