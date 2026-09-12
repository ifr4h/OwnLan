<script setup lang="ts">
import type { PortalProgress, PortalSkill } from '~/composables/usePortal'

const props = defineProps<{
  learnerId: number
  firstName?: string
  nextFocus?: string | null
  countdownLabel?: string | null
}>()

type FilterId = 'all' | 'attention' | 'confident'
type SkillRating = 'introduced' | 'practising' | 'developing' | 'confident'

type ProgressNote = {
  body: string
  learner_visible: boolean
  updated_at?: string
}

type ProgressPayload = PortalProgress & {
  syllabus_percent?: number | null
  syllabus_line?: string | null
  categories: Array<PortalProgress['categories'][number] & {
    note?: ProgressNote | null
    skills: Array<PortalSkill & { note?: ProgressNote | null }>
  }>
}

type SkillRow = PortalSkill & {
  categoryCode: string
  categoryLabel: string
  note?: ProgressNote | null
}

type CategoryBlock = {
  code: string
  label: string
  started: number
  total: number
  note: ProgressNote | null
  skills: SkillRow[]
}

const RATING_OPTIONS: { value: SkillRating; label: string }[] = [
  { value: 'introduced', label: 'Introduced' },
  { value: 'practising', label: 'Needs practice' },
  { value: 'developing', label: 'Improving' },
  { value: 'confident', label: 'Confident' },
]

const FILTERS: { id: FilterId; label: string }[] = [
  { id: 'all', label: 'All' },
  { id: 'attention', label: 'Needs work' },
  { id: 'confident', label: 'Confident' },
]

const { fetchLearnerProgress, recordSkillRating, upsertProgressNote } = useMockTest()

const loading = ref(true)
const error = ref('')
const saveError = ref('')
const data = ref<ProgressPayload | null>(null)
const filter = ref<FilterId>('all')
const openSkillId = ref<number | null>(null)
const pendingRating = ref<SkillRating | null>(null)
const pendingNote = ref('')
const pendingVisible = ref(false)
const saving = ref(false)
const categoryDrafts = ref<Record<string, string>>({})
const categoryVisible = ref<Record<string, boolean>>({})
const editingCategory = ref<string | null>(null)
const savingCategory = ref<string | null>(null)

const allSkills = computed((): SkillRow[] => {
  if (!data.value) return []
  return data.value.categories.flatMap(cat =>
    cat.skills.map(skill => ({
      ...skill,
      categoryCode: cat.code,
      categoryLabel: cat.label,
      note: skill.note ?? null,
    })),
  )
})

const startedCount = computed(() => allSkills.value.filter(s => s.rating).length)
const attentionCount = computed(() => allSkills.value.filter(s => s.rating !== 'confident').length)
const confidentCount = computed(() => allSkills.value.filter(s => s.rating === 'confident').length)

const summaryLine = computed(() => {
  if (!data.value) return ''
  const parts: string[] = []
  parts.push(`${startedCount.value} of ${allSkills.value.length} skills started`)
  if (props.countdownLabel) parts.push(props.countdownLabel)
  else if (props.nextFocus) parts.push(props.nextFocus)
  return parts.join(' · ')
})

function matchesFilter(skill: PortalSkill, id: FilterId): boolean {
  if (id === 'all') return true
  if (id === 'confident') return skill.rating === 'confident'
  return skill.rating !== 'confident'
}

function ratingRank(rating: string | null): number {
  if (rating === 'introduced') return 1
  if (rating === 'practising') return 2
  if (rating === 'developing') return 3
  if (rating === 'confident') return 4
  return 0
}

function ratingLabel(skill: PortalSkill): string {
  if (!skill.rating) return 'Not started'
  return RATING_OPTIONS.find(o => o.value === skill.rating)?.label
    || skill.rating_label
    || 'Not started'
}

const blocks = computed((): CategoryBlock[] => {
  if (!data.value) return []
  return data.value.categories
    .map((cat) => {
      const skills = cat.skills
        .filter(s => matchesFilter(s, filter.value))
        .map(skill => ({
          ...skill,
          categoryCode: cat.code,
          categoryLabel: cat.label,
          note: skill.note ?? null,
        }))
        .sort((a, b) => ratingRank(a.rating) - ratingRank(b.rating))
      return {
        code: cat.code,
        label: cat.label,
        started: cat.skills.filter(s => s.rating).length,
        total: cat.skills.length,
        note: cat.note ?? null,
        skills,
      }
    })
    .filter(block => filter.value === 'all' || block.skills.length > 0)
})

const canSaveSkill = computed(() => {
  if (openSkillId.value == null) return false
  const skill = allSkills.value.find(s => s.id === openSkillId.value)
  if (!skill) return false
  const ratingChanged = Boolean(pendingRating.value) && pendingRating.value !== skill.rating
  const noteChanged = pendingNote.value.trim() !== (skill.note?.body || '').trim()
    || pendingVisible.value !== Boolean(skill.note?.learner_visible)
  return ratingChanged || noteChanged
})

function openSkill(skill: SkillRow) {
  saveError.value = ''
  if (openSkillId.value === skill.id) {
    openSkillId.value = null
    return
  }
  openSkillId.value = skill.id
  pendingRating.value = (skill.rating as SkillRating | null) || null
  pendingNote.value = skill.note?.body || ''
  pendingVisible.value = Boolean(skill.note?.learner_visible)
  editingCategory.value = null
}

function startCategoryNote(block: CategoryBlock) {
  editingCategory.value = block.code
  categoryDrafts.value = {
    ...categoryDrafts.value,
    [block.code]: block.note?.body || '',
  }
  categoryVisible.value = {
    ...categoryVisible.value,
    [block.code]: Boolean(block.note?.learner_visible),
  }
  openSkillId.value = null
}

async function saveSkill() {
  const skillId = openSkillId.value
  if (skillId == null) return
  const skill = allSkills.value.find(s => s.id === skillId)
  if (!skill) return

  saving.value = true
  saveError.value = ''
  try {
    if (pendingRating.value && pendingRating.value !== skill.rating) {
      data.value = await recordSkillRating(props.learnerId, skillId, pendingRating.value) as ProgressPayload
    }
    const noteBody = pendingNote.value.trim()
    const existing = (data.value?.categories.flatMap(c => c.skills).find(s => s.id === skillId)?.note?.body || '').trim()
    const existingVisible = Boolean(
      data.value?.categories.flatMap(c => c.skills).find(s => s.id === skillId)?.note?.learner_visible,
    )
    if (noteBody !== existing || pendingVisible.value !== existingVisible || (noteBody === '' && existing !== '')) {
      data.value = await upsertProgressNote(props.learnerId, {
        skill_id: skillId,
        body: noteBody,
        learner_visible: pendingVisible.value,
      }) as ProgressPayload
    }
    openSkillId.value = null
  } catch (e) {
    saveError.value = extractApiError(e, 'Could not save.')
  } finally {
    saving.value = false
  }
}

async function saveCategoryNote(code: string) {
  savingCategory.value = code
  saveError.value = ''
  try {
    data.value = await upsertProgressNote(props.learnerId, {
      category_code: code,
      body: (categoryDrafts.value[code] || '').trim(),
      learner_visible: Boolean(categoryVisible.value[code]),
    }) as ProgressPayload
    editingCategory.value = null
  } catch (e) {
    saveError.value = extractApiError(e, 'Could not save note.')
  } finally {
    savingCategory.value = null
  }
}

async function load() {
  loading.value = true
  error.value = ''
  saveError.value = ''
  openSkillId.value = null
  editingCategory.value = null
  try {
    data.value = await fetchLearnerProgress(props.learnerId) as ProgressPayload
    filter.value = 'all'
  } catch (e) {
    data.value = null
    error.value = extractApiError(e, 'Could not load progress.')
  } finally {
    loading.value = false
  }
}

watch(() => props.learnerId, () => {
  void load()
})

onMounted(() => {
  void load()
})
</script>

<template>
  <section class="nb" aria-labelledby="nb-title">
    <header class="nb__hero">
      <div class="nb__hero-copy">
        <p class="nb__eyebrow">Teaching notes</p>
        <h2 id="nb-title" class="nb__title">
          {{ loading ? 'Loading…' : (props.firstName ? `${props.firstName}'s skills` : 'Skills') }}
        </h2>
        <p v-if="!loading && !error" class="nb__summary">{{ summaryLine }}</p>
      </div>

      <dl v-if="data?.summary" class="nb__stats">
        <div class="nb__stat nb__stat--confident">
          <dd>{{ data.summary.confident }}</dd>
          <dt>Confident</dt>
        </div>
        <div class="nb__stat">
          <dd>{{ data.summary.developing }}</dd>
          <dt>Improving</dt>
        </div>
        <div class="nb__stat">
          <dd>{{ data.summary.practising }}</dd>
          <dt>Needs practice</dt>
        </div>
      </dl>
    </header>

    <p v-if="error" class="nb__error" role="alert">{{ error }}</p>
    <p v-else-if="saveError" class="nb__error" role="alert">{{ saveError }}</p>

    <template v-else-if="!loading">
      <div class="nb__filters" role="tablist" aria-label="Filter skills">
        <button
          v-for="item in FILTERS"
          :key="item.id"
          type="button"
          class="nb__filter"
          role="tab"
          :aria-selected="filter === item.id"
          :data-on="filter === item.id ? 'yes' : 'no'"
          @click="filter = item.id; openSkillId = null"
        >
          {{ item.label }}
          <span class="nb__filter-n">
            {{ item.id === 'all' ? allSkills.length : item.id === 'confident' ? confidentCount : attentionCount }}
          </span>
        </button>
      </div>

      <p v-if="!blocks.length" class="nb__empty">Nothing in this view.</p>

      <div v-else class="nb__sections">
        <section v-for="block in blocks" :key="block.code" class="nb__card">
          <header class="nb__card-head">
            <div class="nb__card-titles">
              <h3 class="nb__section-title">{{ block.label }}</h3>
              <div class="nb__meter" aria-hidden="true">
                <span
                  class="nb__meter-fill"
                  :style="{ width: `${block.total ? Math.round((block.started / block.total) * 100) : 0}%` }"
                />
              </div>
              <p class="nb__section-meta">{{ block.started }} of {{ block.total }} started</p>
            </div>
            <button
              type="button"
              class="nb__ghost-btn"
              @click="startCategoryNote(block)"
            >
              <OlIcon name="notes" :size="14" />
              {{ block.note || editingCategory === block.code ? 'Edit note' : 'Add note' }}
            </button>
          </header>

          <div v-if="editingCategory === block.code" class="nb__well">
            <label class="nb__note-label" :for="`cat-note-${block.code}`">Note for this area</label>
            <textarea
              :id="`cat-note-${block.code}`"
              v-model="categoryDrafts[block.code]"
              class="nb__textarea"
              rows="3"
              placeholder="e.g. Keep junctions quiet until observation settles"
            />
            <label class="nb__check">
              <input v-model="categoryVisible[block.code]" type="checkbox">
              Show on pupil app
            </label>
            <div class="nb__note-actions">
              <button
                type="button"
                class="nb__btn"
                :disabled="savingCategory === block.code"
                @click="saveCategoryNote(block.code)"
              >
                {{ savingCategory === block.code ? 'Saving…' : 'Save note' }}
              </button>
              <button type="button" class="nb__btn-ghost" @click="editingCategory = null">
                Cancel
              </button>
            </div>
          </div>
          <p v-else-if="block.note?.body" class="nb__quote">
            {{ block.note.body }}
            <span v-if="block.note.learner_visible" class="nb__visible">Visible to pupil</span>
          </p>

          <ul class="nb__skills">
            <li
              v-for="skill in block.skills"
              :key="skill.id"
              class="nb__skill"
              :data-open="openSkillId === skill.id ? 'yes' : 'no'"
            >
              <button type="button" class="nb__skill-row" @click="openSkill(skill)">
                <span class="nb__skill-name">{{ skill.label }}</span>
                <span class="nb__chip" :data-rating="skill.rating || 'none'">
                  {{ ratingLabel(skill) }}
                </span>
              </button>
              <p v-if="skill.note?.body && openSkillId !== skill.id" class="nb__quote nb__quote--nested">
                {{ skill.note.body }}
              </p>

              <div v-if="openSkillId === skill.id" class="nb__well">
                <p class="nb__editor-label">How’s it going?</p>
                <div class="nb__scale" role="radiogroup" aria-label="Skill rating">
                  <button
                    v-for="opt in RATING_OPTIONS"
                    :key="opt.value"
                    type="button"
                    class="nb__scale-btn"
                    role="radio"
                    :aria-checked="pendingRating === opt.value"
                    :data-on="pendingRating === opt.value ? 'yes' : 'no'"
                    :data-rating="opt.value"
                    @click="pendingRating = opt.value"
                  >
                    {{ opt.label }}
                  </button>
                </div>

                <label class="nb__note-label" :for="`skill-note-${skill.id}`">Comment</label>
                <textarea
                  :id="`skill-note-${skill.id}`"
                  v-model="pendingNote"
                  class="nb__textarea"
                  rows="3"
                  placeholder="Optional note for this skill"
                />
                <label class="nb__check">
                  <input v-model="pendingVisible" type="checkbox">
                  Show on pupil app
                </label>

                <div class="nb__note-actions">
                  <button
                    type="button"
                    class="nb__btn"
                    :disabled="!canSaveSkill || saving"
                    @click="saveSkill"
                  >
                    {{ saving ? 'Saving…' : 'Save' }}
                  </button>
                  <button type="button" class="nb__btn-ghost" :disabled="saving" @click="openSkillId = null">
                    Close
                  </button>
                </div>
              </div>
            </li>
          </ul>
        </section>
      </div>

      <p class="nb__foot">
        Your teaching notes — not a pass score.
      </p>
    </template>
  </section>
</template>

<style scoped>
.nb {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.nb__hero {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 18px 24px;
  padding: 20px;
  border-radius: 20px;
  background:
    radial-gradient(90% 120% at 100% 0%, color-mix(in srgb, var(--color-ownlane-green) 12%, transparent), transparent 55%),
    var(--color-parchment);
  border: 1px solid var(--color-border);
}

.nb__eyebrow {
  margin: 0 0 6px;
  font-size: 12px;
  font-weight: 650;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-ownlane-green);
}

.nb__title {
  margin: 0;
  font-family: var(--font-haas-grot-disp), var(--font-haas-grot-text), system-ui, sans-serif;
  font-size: clamp(1.4rem, 2.4vw, 1.85rem);
  font-weight: 700;
  letter-spacing: -0.025em;
  line-height: 1.15;
  color: var(--color-ink-black);
}

.nb__summary {
  margin: 10px 0 0;
  font-size: 14px;
  line-height: 1.45;
  color: var(--color-bark);
  max-width: 42ch;
}

.nb__stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(72px, 1fr));
  gap: 8px;
  margin: 0;
  min-width: min(100%, 280px);
}

.nb__stat {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px 10px;
  border-radius: 14px;
  background: var(--color-paper-white);
  border: 1px solid var(--color-border);
  text-align: center;
}

.nb__stat--confident {
  background: color-mix(in srgb, var(--color-ownlane-green) 10%, var(--color-paper-white));
  border-color: color-mix(in srgb, var(--color-ownlane-green) 28%, var(--color-border));
}

.nb__stat dd {
  margin: 0;
  font-size: 24px;
  font-weight: 750;
  line-height: 1;
  letter-spacing: -0.03em;
  font-variant-numeric: tabular-nums;
  color: var(--color-ink-black);
}

.nb__stat--confident dd {
  color: var(--color-ownlane-green);
}

.nb__stat dt {
  font-size: 11px;
  font-weight: 600;
  color: var(--color-muted);
}

.nb__filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.nb__filter {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 38px;
  padding: 0 14px;
  border-radius: var(--radius-buttons);
  border: 1px solid var(--color-driftwood);
  background: var(--color-paper-white);
  color: var(--color-bark);
  font-size: 13px;
  font-weight: 650;
  cursor: pointer;
  transition: border-color 120ms ease, background 120ms ease, color 120ms ease;
}

.nb__filter:hover {
  border-color: var(--color-ink-black);
  color: var(--color-ink-black);
}

.nb__filter:focus-visible {
  outline: 2px solid var(--color-ownlane-green);
  outline-offset: 2px;
}

.nb__filter[data-on='yes'] {
  background: var(--color-ownlane-green);
  border-color: var(--color-ownlane-green);
  color: var(--color-paper-white);
}

.nb__filter-n {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 22px;
  height: 22px;
  padding: 0 6px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  background: color-mix(in srgb, currentColor 14%, transparent);
}

.nb__sections {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.nb__card {
  padding: 4px 4px 8px;
  border-radius: 18px;
  background: var(--color-paper-white);
  border: 1px solid var(--color-border);
  overflow: hidden;
}

.nb__card-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 14px 10px;
}

.nb__card-titles {
  min-width: 0;
  flex: 1;
}

.nb__section-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  letter-spacing: -0.01em;
  color: var(--color-ink-black);
}

.nb__meter {
  margin-top: 10px;
  height: 6px;
  max-width: 220px;
  border-radius: 999px;
  background: var(--color-parchment);
  overflow: hidden;
}

.nb__meter-fill {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--color-ownlane-green);
}

.nb__section-meta {
  margin: 6px 0 0;
  font-size: 12px;
  color: var(--color-muted);
  font-variant-numeric: tabular-nums;
}

.nb__ghost-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 34px;
  padding: 0 12px;
  border-radius: var(--radius-buttons);
  border: 1px solid var(--color-border);
  background: var(--color-parchment);
  color: var(--color-bark);
  font-size: 12px;
  font-weight: 650;
  cursor: pointer;
  white-space: nowrap;
}

.nb__ghost-btn:hover {
  border-color: var(--color-driftwood);
  color: var(--color-ink-black);
}

.nb__quote {
  margin: 0 14px 10px;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--color-parchment);
  font-size: 13px;
  line-height: 1.45;
  color: var(--color-bark);
}

.nb__quote--nested {
  margin: 0 12px 10px;
}

.nb__visible {
  display: inline-block;
  margin-left: 8px;
  padding: 2px 8px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--color-ownlane-green) 12%, var(--color-paper-white));
  color: var(--color-ownlane-green);
  font-size: 11px;
  font-weight: 650;
}

.nb__skills {
  list-style: none;
  margin: 0 8px 8px;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.nb__skill {
  border-radius: 12px;
  background: transparent;
  overflow: hidden;
}

.nb__skill[data-open='yes'] {
  background: var(--color-parchment);
}

.nb__skill-row {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-height: 48px;
  padding: 10px 12px;
  border: none;
  background: transparent;
  cursor: pointer;
  text-align: left;
  color: inherit;
  border-radius: 12px;
}

.nb__skill-row:hover {
  background: color-mix(in srgb, var(--color-parchment) 80%, transparent);
}

.nb__skill[data-open='yes'] .nb__skill-row:hover {
  background: transparent;
}

.nb__skill-row:focus-visible {
  outline: 2px solid var(--color-ownlane-green);
  outline-offset: 1px;
}

.nb__skill-name {
  font-size: 14px;
  font-weight: 550;
  color: var(--color-ink-black);
  min-width: 0;
}

.nb__chip {
  flex: 0 0 auto;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 650;
  border: 1px solid transparent;
  white-space: nowrap;
}

.nb__chip[data-rating='none'] {
  color: var(--color-muted);
  background: var(--color-paper-white);
  border-color: var(--color-border);
}

.nb__chip[data-rating='introduced'] {
  color: #5c4f7a;
  background: color-mix(in srgb, #8b7bb8 14%, var(--color-paper-white));
}

.nb__chip[data-rating='practising'] {
  color: #8a5a16;
  background: color-mix(in srgb, #c9852c 16%, var(--color-paper-white));
}

.nb__chip[data-rating='developing'] {
  color: #2f5f7d;
  background: color-mix(in srgb, #3d7ea6 14%, var(--color-paper-white));
}

.nb__chip[data-rating='confident'] {
  color: var(--color-ownlane-green);
  background: color-mix(in srgb, var(--color-ownlane-green) 12%, var(--color-paper-white));
}

.nb__well {
  margin: 0 8px 10px;
  padding: 14px;
  border-radius: 14px;
  background: var(--color-paper-white);
  border: 1px solid var(--color-border);
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.nb__editor-label,
.nb__note-label {
  margin: 0;
  font-size: 12px;
  font-weight: 700;
  color: var(--color-bark);
}

.nb__scale {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

@media (min-width: 720px) {
  .nb__scale {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

.nb__scale-btn {
  min-height: 42px;
  padding: 8px 10px;
  border-radius: 999px;
  border: 1.5px solid var(--color-border);
  background: var(--color-paper-white);
  font-size: 12px;
  font-weight: 650;
  color: var(--color-bark);
  cursor: pointer;
  transition: border-color 120ms ease, background 120ms ease, color 120ms ease;
}

.nb__scale-btn:hover {
  border-color: var(--color-driftwood);
  color: var(--color-ink-black);
}

.nb__scale-btn[data-on='yes'] {
  border-color: var(--color-ownlane-green);
  background: color-mix(in srgb, var(--color-ownlane-green) 10%, var(--color-paper-white));
  color: var(--color-ownlane-green);
}

.nb__scale-btn[data-rating='confident'][data-on='yes'] {
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
}

.nb__textarea {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid var(--color-driftwood);
  border-radius: 10px;
  background: var(--color-parchment);
  font: inherit;
  font-size: 14px;
  line-height: 1.4;
  color: var(--color-ink-black);
  resize: vertical;
  min-height: 84px;
}

.nb__textarea:focus {
  outline: none;
  border-color: var(--color-ink-black);
  background: var(--color-paper-white);
}

.nb__check {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--color-bark);
}

.nb__note-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding-top: 2px;
}

.nb__btn {
  min-height: 42px;
  padding: 0 18px;
  border: none;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  font-size: 13px;
  font-weight: 650;
  cursor: pointer;
}

.nb__btn:hover:not(:disabled) {
  background: var(--color-jelly-green);
}

.nb__btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.nb__btn-ghost {
  min-height: 42px;
  padding: 0 14px;
  border: 1px solid var(--color-driftwood);
  border-radius: var(--radius-buttons);
  background: transparent;
  color: var(--color-bark);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.nb__error {
  margin: 0;
  padding: 12px 14px;
  border-radius: 12px;
  background: color-mix(in srgb, #b42318 8%, var(--color-paper-white));
  color: var(--color-danger, #b42318);
  font-size: 14px;
}

.nb__empty {
  margin: 0;
  padding: 20px;
  border-radius: 16px;
  border: 1px dashed var(--color-driftwood);
  color: var(--color-muted);
  font-size: 14px;
  text-align: center;
}

.nb__foot {
  margin: 0;
  font-size: 12px;
  color: var(--color-muted);
}

@media (max-width: 560px) {
  .nb__stats {
    width: 100%;
  }

  .nb__card-head {
    flex-direction: column;
    align-items: stretch;
  }

  .nb__ghost-btn {
    align-self: flex-start;
  }
}
</style>
