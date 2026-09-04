<script setup lang="ts">
definePageMeta({ layout: 'portal' })

const route = useRoute()
const slug = computed(() => String(route.params.slug))
const { t } = usePortalI18n()

type ScenarioOption = {
  id: string
  label: string
  correct?: boolean
  feedback?: string
}

type ScenarioStep = {
  id: string
  prompt: string
  mode: 'choice' | 'explain' | 'select_lane'
  options?: ScenarioOption[]
  explanation?: string
}

type ContentBlock =
  | { type: 'heading'; text: string }
  | { type: 'text'; text: string }
  | { type: 'callout'; text: string; tone?: string }
  | { type: 'checklist'; items: string[] }
  | { type: 'steps'; items: string[] }
  | { type: 'question'; prompt: string; answer?: string; feedback?: string }
  | {
      type: 'scenario'
      template: string
      title?: string
      steps: ScenarioStep[]
    }

type LearnContent = {
  id: number
  slug: string
  title: string
  category_label: string
  summary: string | null
  blocks: ContentBlock[]
  source_note: string | null
}

const data = ref<LearnContent | null>(null)
const loading = ref(true)
const error = ref('')
const revealed = reactive<Record<number, boolean>>({})
const openedLogged = ref(false)

useHead(() => ({
  title: data.value ? `${data.value.title} · Learn · OwnLane` : 'Learn · OwnLane',
}))

async function postActivity(
  activity_type: 'opened' | 'completed' | 'scenario_completed' | 'quick_review',
  result?: unknown,
) {
  try {
    await apiFetch('/portal/learn/activity', {
      method: 'POST',
      body: {
        content_slug: slug.value,
        activity_type,
        ...(result !== undefined ? { result } : {}),
      },
    })
  } catch {
    // Activity tracking is best-effort — never block learning.
  }
}

async function load() {
  loading.value = true
  error.value = ''
  openedLogged.value = false
  try {
    data.value = await apiFetch<LearnContent>(`/portal/learn/${slug.value}`)
    if (!openedLogged.value) {
      openedLogged.value = true
      void postActivity('opened')
    }
  } catch (e) {
    data.value = null
    error.value = extractApiError(e, t('common.errorGeneric'))
  } finally {
    loading.value = false
  }
}

function onScenarioCompleted(
  results: Array<{ step_id: string; option_id?: string; correct?: boolean }>,
) {
  void postActivity('scenario_completed', { steps: results })
}

onMounted(() => {
  void load()
})

watch(slug, () => {
  void load()
})
</script>

<template>
  <div class="article">
    <NuxtLink to="/portal/learn" class="article__back">← {{ t('learn.title') }}</NuxtLink>

    <p v-if="loading" class="article__msg">{{ t('common.loading') }}</p>
    <p v-else-if="error" class="article__err" role="alert">{{ error }}</p>

    <template v-else-if="data">
      <header class="article__hero">
        <p class="article__cat">{{ data.category_label }}</p>
        <h1 class="article__title">{{ data.title }}</h1>
        <p v-if="data.summary" class="article__summary">{{ data.summary }}</p>
      </header>

      <div class="article__body">
        <template v-for="(block, i) in data.blocks" :key="i">
          <h2 v-if="block.type === 'heading'" class="article__h">{{ block.text }}</h2>
          <p v-else-if="block.type === 'text'" class="article__p">{{ block.text }}</p>
          <aside v-else-if="block.type === 'callout'" class="article__callout">
            {{ block.text }}
          </aside>
          <ul v-else-if="block.type === 'checklist'" class="article__check">
            <li v-for="(item, j) in block.items" :key="j">{{ item }}</li>
          </ul>
          <ol v-else-if="block.type === 'steps'" class="article__steps">
            <li v-for="(item, j) in block.items" :key="j">{{ item }}</li>
          </ol>
          <div v-else-if="block.type === 'question'" class="article__q">
            <p class="article__prompt">{{ block.prompt }}</p>
            <button
              type="button"
              class="article__reveal"
              @click="revealed[i] = !revealed[i]"
            >
              {{ revealed[i] ? 'Hide' : 'Show answer' }}
            </button>
            <div v-if="revealed[i]" class="article__feedback">
              <p v-if="block.answer"><strong>Answer:</strong> {{ block.answer }}</p>
              <p v-if="block.feedback">{{ block.feedback }}</p>
            </div>
          </div>
          <LearnScenarioPlayer
            v-else-if="block.type === 'scenario'"
            :template="block.template"
            :title="block.title"
            :steps="block.steps"
            @completed="onScenarioCompleted"
          />
        </template>
      </div>

      <p class="article__provenance">{{ t('learn.provenance') }}</p>
      <p v-if="data.source_note" class="article__source">{{ data.source_note }}</p>
    </template>
  </div>
</template>

<style scoped>
.article {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-20);
  padding-top: var(--spacing-8);
}

.article__back {
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  color: var(--color-muted);
  text-decoration: none;
  font-size: var(--text-body-sm);
}

.article__hero {
  margin-left: calc(-1 * var(--spacing-20));
  margin-right: calc(-1 * var(--spacing-20));
  padding: var(--spacing-24) var(--spacing-20);
  background: var(--color-frost-green);
}

.article__cat {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  text-transform: uppercase;
  color: var(--color-ownlane-green);
}

.article__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-md);
  letter-spacing: var(--tracking-heading-md);
  margin-top: var(--spacing-8);
}

.article__summary {
  margin-top: var(--spacing-12);
  font-size: var(--text-body-sm);
  max-width: 40ch;
}

.article__body {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
}

.article__h {
  font-size: var(--text-heading-sm);
  letter-spacing: var(--tracking-heading-sm);
}

.article__p {
  font-size: var(--text-body);
  line-height: var(--leading-body);
  max-width: 40ch;
}

.article__callout {
  padding: var(--spacing-16);
  background: var(--color-success-wash);
  border-radius: var(--radius-small);
  font-size: var(--text-body-sm);
  border-left: 4px solid var(--color-ownlane-green);
}

.article__check,
.article__steps {
  margin: 0;
  padding-left: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 8px;
  font-size: var(--text-body-sm);
}

.article__q {
  padding: var(--spacing-16);
  background: var(--color-frost-green);
  border-radius: var(--radius-panel);
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.article__prompt {
  font-size: var(--text-body-sm);
}

.article__reveal {
  min-height: 44px;
  width: fit-content;
  padding: 8px 16px;
  border: none;
  border-radius: 14px;
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  font: inherit;
  font-size: var(--text-meta);
  cursor: pointer;
}

.article__feedback {
  font-size: var(--text-body-sm);
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.article__provenance,
.article__source {
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.article__msg {
  color: var(--color-muted);
}

.article__err {
  color: var(--color-danger);
}
</style>
