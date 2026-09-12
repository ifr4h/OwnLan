<template>
  <section class="page">
    <NuxtLink to="/pupils" class="back">← Pupils</NuxtLink>

    <header class="hero">
      <p class="hero__eyebrow">Import</p>
      <h1 class="hero__title">Bring your pupils in</h1>
      <p class="hero__lede">
        Download the example spreadsheet, fill it in (or paste from another system), then upload.
        We’ll show you exactly what will be imported before anything is saved.
      </p>
    </header>

    <ol class="steps">
      <li :data-active="step === 1 ? 'yes' : 'no'">1. Example</li>
      <li :data-active="step === 2 ? 'yes' : 'no'">2. Upload</li>
      <li :data-active="step === 3 ? 'yes' : 'no'">3. Check</li>
    </ol>

    <!-- Step 1 -->
    <section v-if="step === 1" class="panel">
      <h2 class="panel__title">Start with the OwnLane format</h2>
      <p class="panel__copy">
        Only these columns: first name, last name, mobile, email, usual pickup, private notes.
        Name and mobile are required.
      </p>
      <a class="btn" :href="templateHref" download>
        Download example CSV
        <span aria-hidden="true">→</span>
      </a>
      <button class="btn btn--ghost" type="button" @click="step = 2">
        I’ve got my file
      </button>
    </section>

    <!-- Step 2 -->
    <section v-if="step === 2" class="panel">
      <h2 class="panel__title">Upload your CSV</h2>
      <p class="panel__copy">
        From Excel: File → Save As → CSV. Then choose that file here.
      </p>
      <label class="file">
        <span class="file__label">{{ fileName || 'Choose CSV file' }}</span>
        <input
          class="file__input"
          type="file"
          accept=".csv,text/csv"
          @change="onFileChange"
        >
      </label>
      <p v-if="previewError" class="error" role="alert">{{ previewError }}</p>
      <div class="actions">
        <button class="btn" type="button" :disabled="!file || previewing" @click="onPreview">
          {{ previewing ? 'Checking…' : 'Check file' }}
          <span aria-hidden="true">→</span>
        </button>
        <button class="ghost" type="button" :disabled="previewing" @click="step = 1">
          Back
        </button>
      </div>
    </section>

    <!-- Step 3 -->
    <template v-if="step === 3 && preview">
      <section class="summary" aria-label="Import summary">
        <div class="stat">
          <p class="stat__value">{{ preview.summary.ready }}</p>
          <p class="stat__label">Ready</p>
        </div>
        <div class="stat" :data-warn="preview.summary.duplicates > 0 ? 'yes' : 'no'">
          <p class="stat__value">{{ preview.summary.duplicates }}</p>
          <p class="stat__label">Duplicates</p>
        </div>
        <div class="stat" :data-bad="preview.summary.errors > 0 ? 'yes' : 'no'">
          <p class="stat__value">{{ preview.summary.errors }}</p>
          <p class="stat__label">Errors</p>
        </div>
      </section>

      <p class="help">{{ preview.help }}</p>

      <label v-if="preview.summary.duplicates > 0" class="check">
        <input v-model="includeDuplicates" type="checkbox">
        <span>Also import duplicate rows (creates a second pupil record)</span>
      </label>

      <section class="panel panel--table">
        <h2 class="panel__title">Preview</h2>
        <ul class="rows">
          <li
            v-for="row in preview.rows"
            :key="row.row_number"
            class="row"
            :data-status="row.status"
          >
            <div class="row__top">
              <span class="row__num">Row {{ row.row_number }}</span>
              <span class="row__badge">{{ statusLabel(row.status) }}</span>
            </div>
            <p class="row__name">{{ row.display_name || '—' }}</p>
            <p class="row__meta">{{ row.data.mobile || 'No mobile' }}</p>
            <p v-if="row.data.email" class="row__meta">{{ row.data.email }}</p>
            <ul v-if="row.errors.length" class="row__errors">
              <li v-for="err in row.errors" :key="err">{{ err }}</li>
            </ul>
            <p v-else-if="row.duplicate" class="row__dup">{{ row.duplicate.message }}</p>
          </li>
        </ul>
      </section>

      <p v-if="confirmError" class="error" role="alert">{{ confirmError }}</p>
      <p v-if="confirmResult" class="ok" role="status">{{ confirmResult.message }}</p>

      <div class="actions">
        <button
          class="btn"
          type="button"
          :disabled="!canConfirm || confirming"
          @click="onConfirm"
        >
          {{ confirmButtonLabel }}
          <span aria-hidden="true">→</span>
        </button>
        <button class="ghost" type="button" :disabled="confirming" @click="resetToUpload">
          Choose a different file
        </button>
      </div>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { ImportPreview } from '~/composables/usePupils'

useHead({ title: 'Import pupils · OwnLane' })

const { previewPupilImport, confirmPupilImport } = usePupils()

const step = ref(1)
const file = ref<File | null>(null)
const fileName = ref('')
const previewing = ref(false)
const previewError = ref('')
const preview = ref<ImportPreview | null>(null)
const includeDuplicates = ref(false)
const confirming = ref(false)
const confirmError = ref('')
const confirmResult = ref<{ message: string; imported_count: number } | null>(null)

const templateHref = '/api/learners/import/template'

const importCount = computed(() => {
  if (!preview.value) return 0
  const ready = preview.value.summary.ready
  const dups = includeDuplicates.value ? preview.value.summary.duplicates : 0
  return ready + dups
})

const canConfirm = computed(() => importCount.value > 0 && !confirmResult.value)

const confirmButtonLabel = computed(() => {
  if (confirming.value) return 'Importing…'
  if (confirmResult.value) return 'Done'
  const n = importCount.value
  if (n === 0) return 'Nothing to import'
  if (n === 1) return 'Import 1 pupil'
  return `Import ${n} pupils`
})

function statusLabel(status: string): string {
  if (status === 'ready') return 'Ready'
  if (status === 'duplicate') return 'Duplicate'
  if (status === 'error') return 'Needs fix'
  return status
}

function onFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  const next = input.files?.[0] ?? null
  file.value = next
  fileName.value = next?.name || ''
  previewError.value = ''
  preview.value = null
  confirmResult.value = null
}

async function onPreview() {
  if (!file.value) return
  previewing.value = true
  previewError.value = ''
  confirmResult.value = null
  try {
    preview.value = await previewPupilImport(file.value)
    includeDuplicates.value = false
    step.value = 3
  } catch (e) {
    preview.value = null
    previewError.value = extractApiError(e, 'Could not read that CSV.')
  } finally {
    previewing.value = false
  }
}

async function onConfirm() {
  if (!preview.value || !canConfirm.value) return
  confirming.value = true
  confirmError.value = ''
  try {
    const rows = preview.value.rows
      .filter((row) => {
        if (row.status === 'ready') return true
        if (row.status === 'duplicate' && includeDuplicates.value) return true
        return false
      })
      .map(row => ({
        row_number: row.row_number,
        data: row.data,
        import_despite_duplicate: row.status === 'duplicate',
      }))

    const result = await confirmPupilImport({
      rows,
      include_duplicates: includeDuplicates.value,
    })
    confirmResult.value = result
    await useOnboarding().refresh()
    await navigateTo('/pupils')
  } catch (e) {
    confirmError.value = extractApiError(e, 'Could not import pupils.')
  } finally {
    confirming.value = false
  }
}

function resetToUpload() {
  step.value = 2
  preview.value = null
  confirmResult.value = null
  confirmError.value = ''
  includeDuplicates.value = false
}
</script>

<style scoped>
.page {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
}

.back {
  font-size: var(--text-body-sm);
  color: var(--color-ownlane-green);
  align-self: flex-start;
}

.hero {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.hero__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.6;
}

.hero__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
  letter-spacing: var(--tracking-heading-sm);
}

.hero__lede {
  font-size: var(--text-body-sm);
  line-height: var(--leading-body-sm);
  opacity: 0.85;
}

.steps {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
}

.steps li {
  opacity: 0.45;
}

.steps li[data-active='yes'] {
  opacity: 1;
  color: var(--color-ownlane-green);
}

.panel {
  background: var(--surface-card);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-cards);
  box-shadow: var(--shadow-card);
  padding: var(--spacing-20);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
}

.panel__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-body);
}

.panel__copy {
  font-size: var(--text-body-sm);
  opacity: 0.8;
}

.panel--table {
  padding: var(--spacing-16);
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-8);
  align-self: flex-start;
  min-height: 48px;
  padding: 12px 24px;
  border: none;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  box-shadow: var(--shadow-button);
  cursor: pointer;
  text-decoration: none;
  font: inherit;
}

.btn--ghost {
  background: transparent;
  color: var(--color-ink-black);
  box-shadow: none;
  border: 1px solid var(--color-frost-green);
}

.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.ghost {
  border: none;
  background: transparent;
  font: inherit;
  font-size: var(--text-body-sm);
  text-decoration: underline;
  cursor: pointer;
  min-height: 44px;
  align-self: flex-start;
}

.file {
  position: relative;
  display: flex;
  align-items: center;
  min-height: 56px;
  padding: 12px 16px;
  border: 1px dashed var(--color-ownlane-green);
  border-radius: var(--radius-inputs);
  background: var(--surface-canvas);
  cursor: pointer;
}

.file__label {
  font-size: var(--text-body-sm);
}

.file__input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}

.actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-12);
  align-items: center;
}

.error {
  color: var(--color-marker-red);
  font-size: var(--text-body-sm);
}

.ok {
  color: var(--color-ownlane-green);
  font-size: var(--text-body-sm);
}

.summary {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--spacing-8);
}

.stat {
  background: var(--color-frost-green);
  border-radius: var(--radius-cards);
  padding: var(--spacing-16);
  text-align: center;
}

.stat[data-warn='yes'] {
  background: #fff6d6;
}

.stat[data-bad='yes'] {
  background: #ffe4e4;
}

.stat__value {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
}

.stat__label {
  font-size: var(--text-body-sm);
  opacity: 0.7;
}

.help {
  font-size: var(--text-body-sm);
  opacity: 0.75;
}

.check {
  display: flex;
  gap: var(--spacing-8);
  align-items: flex-start;
  font-size: var(--text-body-sm);
}

.rows {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
  max-height: 420px;
  overflow: auto;
}

.row {
  padding: var(--spacing-12);
  border-radius: var(--radius-inputs);
  background: var(--surface-canvas);
  border-left: 4px solid var(--color-ownlane-green);
}

.row[data-status='error'] {
  border-left-color: var(--color-marker-red);
}

.row[data-status='duplicate'] {
  border-left-color: #d4a017;
}

.row__top {
  display: flex;
  justify-content: space-between;
  gap: var(--spacing-8);
  margin-bottom: 4px;
}

.row__num,
.row__badge {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.65;
}

.row__name {
  font-size: var(--text-body-sm);
}

.row__meta {
  font-size: 14px;
  opacity: 0.7;
}

.row__errors {
  margin: var(--spacing-8) 0 0;
  padding-left: 1.1rem;
  color: var(--color-marker-red);
  font-size: var(--text-body-sm);
}

.row__dup {
  margin-top: var(--spacing-8);
  font-size: var(--text-body-sm);
  opacity: 0.8;
}

@media (max-width: 480px) {
  .summary {
    grid-template-columns: 1fr;
  }
}
</style>
