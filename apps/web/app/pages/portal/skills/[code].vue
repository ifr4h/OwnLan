<script setup lang="ts">
definePageMeta({ layout: 'portal' })

const route = useRoute()
const code = computed(() => String(route.params.code))
const { t } = usePortalI18n()

type Evidence = {
  skill: {
    code: string
    label: string
    category_label: string
    rating: string | null
    rating_label: string | null
  }
  next_focus: string | null
  timeline: Array<{
    type: string
    rating?: string
    recorded_at: string
    lesson_id?: number
    route_id?: number
  }>
  lessons: Array<{
    id: number
    starts_at: string
    learner_summary: string | null
  }>
  routes: Array<{ id: number; lesson_id: number; started_at: string | null }>
  related_resources: Array<{ slug: string; title: string; summary: string | null }>
}

const data = ref<Evidence | null>(null)
const loading = ref(true)
const error = ref('')
const confidence = ref('')
const savingConfidence = ref(false)
const confidenceSaved = ref(false)

const confidenceOptions = [
  { value: 'need_more_help', label: 'I need more help' },
  { value: 'still_practising', label: 'I still need practice' },
  { value: 'getting_comfortable', label: 'I\'m getting more comfortable' },
  { value: 'feel_confident', label: 'I feel confident' },
]

async function saveConfidence() {
  if (!confidence.value) return
  savingConfidence.value = true
  confidenceSaved.value = false
  try {
    await apiFetch(`/portal/skills/${code.value}/self-assessment`, {
      method: 'POST',
      body: { confidence: confidence.value },
    })
    confidenceSaved.value = true
  } catch (e) {
    error.value = extractApiError(e, t('common.errorGeneric'))
  } finally {
    savingConfidence.value = false
  }
}

useHead(() => ({
  title: data.value
    ? `${data.value.skill.label} · Skills · OwnLane`
    : 'Skill · OwnLane',
}))

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await apiFetch<Evidence>(`/portal/skills/${code.value}/evidence`)
  } catch (e) {
    data.value = null
    error.value = extractApiError(e, t('common.errorGeneric'))
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
})

watch(code, () => {
  void load()
})
</script>

<template>
  <div class="ev">
    <NuxtLink to="/portal/progress" class="ev__back">← {{ t('progress.title') }}</NuxtLink>

    <p v-if="loading" class="ev__msg">{{ t('common.loading') }}</p>
    <p v-else-if="error" class="ev__err" role="alert">{{ error }}</p>

    <template v-else-if="data">
      <header class="ev__hero">
        <p class="ev__cat">{{ data.skill.category_label }}</p>
        <h1 class="ev__title">{{ data.skill.label }}</h1>
        <p v-if="data.skill.rating_label" class="ev__rating">{{ data.skill.rating_label }}</p>
      </header>

      <section class="ev__section">
        <h2 class="ev__h">How does this feel?</h2>
        <p class="ev__msg">Your view — separate from your instructor's assessment.</p>
        <div class="confidence-options">
          <label v-for="opt in confidenceOptions" :key="opt.value" class="confidence-opt">
            <input v-model="confidence" type="radio" name="confidence" :value="opt.value">
            <span>{{ opt.label }}</span>
          </label>
        </div>
        <button
          type="button"
          class="ev__save"
          :disabled="!confidence || savingConfidence"
          @click="saveConfidence"
        >
          {{ savingConfidence ? 'Saving…' : 'Save' }}
        </button>
        <p v-if="confidenceSaved" class="ev__saved" role="status">Saved</p>
      </section>

      <section v-if="data.timeline.length" class="ev__section">
        <h2 class="ev__h">{{ t('skillEvidence.timeline') }}</h2>
        <ul class="ev__list">
          <li v-for="(row, i) in data.timeline" :key="i" class="ev__row">
            <span class="ev__type">{{ row.type }}</span>
            <span v-if="row.rating">{{ row.rating }}</span>
            <NuxtLink
              v-if="row.lesson_id"
              :to="`/portal/recap/${row.lesson_id}`"
              class="ev__link"
            >
              Lesson
            </NuxtLink>
            <NuxtLink
              v-if="row.route_id"
              :to="`/portal/routes/${row.route_id}`"
              class="ev__link"
            >
              Route
            </NuxtLink>
          </li>
        </ul>
      </section>

      <section v-if="data.related_resources.length" class="ev__section">
        <h2 class="ev__h">{{ t('skillEvidence.related') }}</h2>
        <ul class="ev__list">
          <li v-for="r in data.related_resources" :key="r.slug">
            <NuxtLink :to="`/portal/learn/${r.slug}`" class="ev__row-link">
              {{ r.title }}
            </NuxtLink>
          </li>
        </ul>
      </section>

      <p
        v-if="!data.timeline.length && !data.related_resources.length"
        class="ev__msg"
      >
        {{ t('skillEvidence.empty') }}
      </p>
    </template>
  </div>
</template>

<style scoped>
.ev {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-24);
  padding-top: var(--spacing-8);
}

.ev__back {
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  color: var(--color-muted);
  text-decoration: none;
  font-size: var(--text-body-sm);
}

.ev__hero {
  padding: var(--spacing-20) 0;
}

.ev__cat {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
}

.ev__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-md);
  margin-top: 8px;
}

.ev__rating {
  margin-top: 8px;
  color: var(--color-ownlane-green);
  font-size: var(--text-body-sm);
}

.ev__h {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
  margin-bottom: 8px;
}

.ev__list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.ev__row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
  min-height: 48px;
  padding: 8px 0;
  border-bottom: 1px solid var(--color-border);
  font-size: var(--text-body-sm);
}

.ev__type {
  text-transform: capitalize;
  opacity: 0.7;
}

.ev__link,
.ev__row-link {
  color: var(--color-ownlane-green);
  text-decoration: none;
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  font-size: var(--text-body-sm);
}

.ev__msg {
  color: var(--color-muted);
  font-size: var(--text-body-sm);
}

.ev__err {
  color: var(--color-danger);
}

.confidence-options {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
  margin: var(--spacing-12) 0;
}

.confidence-opt {
  display: flex;
  align-items: center;
  gap: var(--spacing-12);
  min-height: 44px;
  padding: var(--spacing-8);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-small);
}

.ev__save {
  min-height: 44px;
  padding: var(--spacing-8) var(--spacing-16);
  border: none;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
}

.ev__saved {
  font-size: var(--text-body-sm);
  color: var(--color-ownlane-green);
}
</style>
