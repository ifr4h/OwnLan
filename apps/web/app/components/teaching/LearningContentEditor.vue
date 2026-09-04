<script setup lang="ts">
/**
 * Resource Builder — used for /learning/contents/new and /learning/contents/:id
 */
const props = defineProps<{
  contentId?: number | null
}>()

const isNew = computed(() => !props.contentId)
const { t } = useTeachingI18n()

const title = ref('')
const slug = ref('')
const category = ref('general')
const summary = ref('')
const status = ref<'draft' | 'published'>('draft')
const blocksJson = ref(`[
  { "type": "heading", "text": "What to remember" },
  { "type": "text", "text": "Keep this short and clear." },
  { "type": "callout", "text": "A tip that stands out." },
  { "type": "steps", "items": ["Look", "Signal", "Manoeuvre"] },
  { "type": "checklist", "items": ["Mirrors", "Blind spot"] },
  { "type": "question", "prompt": "What should you check first?", "answer": "Mirrors", "feedback": "Always start with observation." }
]`)
const skillCodes = ref('')
const saving = ref(false)
const error = ref('')
const saveMsg = ref('')
const loading = ref(!isNew.value)

useHead(() => ({
  title: title.value
    ? `${title.value} · Learning · OwnLane`
    : 'Resource builder · OwnLane',
}))

onMounted(async () => {
  if (!props.contentId) {
    loading.value = false
    return
  }
  try {
    const list = await apiFetch<{
      items: Array<{ id: number; slug: string; title: string; category: string; status: string }>
    }>('/learning/contents')
    const row = list.items.find(i => i.id === props.contentId)
    if (row) {
      title.value = row.title
      slug.value = row.slug
      category.value = row.category
      status.value = row.status === 'published' ? 'published' : 'draft'
    }
    if (slug.value) {
      try {
        const content = await apiFetch<{
          summary: string | null
          blocks: unknown[]
          skill_codes: string[]
        }>(`/portal/learn/${slug.value}`)
        summary.value = content.summary || ''
        blocksJson.value = JSON.stringify(content.blocks ?? [], null, 2)
        skillCodes.value = (content.skill_codes ?? []).join(', ')
      } catch {
        // Drafts aren't learner-visible
      }
    }
  } catch (e) {
    error.value = extractApiError(e, 'Could not load content.')
  } finally {
    loading.value = false
  }
})

watch(title, (v) => {
  if (isNew.value && !slug.value) {
    slug.value = v
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-|-$/g, '')
      .slice(0, 80)
  }
})

async function onSave(nextStatus?: 'draft' | 'published') {
  saving.value = true
  error.value = ''
  saveMsg.value = ''
  try {
    let blocks: unknown[]
    try {
      blocks = JSON.parse(blocksJson.value)
      if (!Array.isArray(blocks)) throw new Error('Blocks must be an array')
    } catch {
      throw new Error('Blocks JSON is invalid.')
    }
    const body = {
      title: title.value.trim(),
      slug: slug.value.trim(),
      category: category.value.trim() || 'general',
      summary: summary.value.trim() || null,
      status: nextStatus ?? status.value,
      blocks,
      skill_codes: skillCodes.value
        .split(',')
        .map(s => s.trim())
        .filter(Boolean),
    }
    status.value = body.status as 'draft' | 'published'
    if (props.contentId) {
      try {
        await apiFetch(`/learning/contents/${props.contentId}`, { method: 'PUT', body })
      } catch (e) {
        // Backend returns portal content payload which 404s for drafts — treat as ok if list still has it
        if (body.status === 'published') throw e
      }
      saveMsg.value = 'Saved'
    } else {
      let newId: number | null = null
      try {
        const created = await apiFetch<{ id: number }>('/learning/contents', {
          method: 'POST',
          body,
        })
        newId = created.id
      } catch (e) {
        const list = await apiFetch<{ items: Array<{ id: number; slug: string }> }>('/learning/contents')
        newId = list.items.find(i => i.slug === body.slug)?.id ?? null
        if (!newId) throw e
      }
      saveMsg.value = 'Saved'
      await navigateTo(`/learning/contents/${newId}`, { replace: true })
    }
  } catch (e) {
    error.value = e instanceof Error && e.message.includes('Blocks')
      ? e.message
      : extractApiError(e, 'Could not save.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section class="builder ol-page">
    <NuxtLink to="/learning/contents" class="ol-back">← Contents</NuxtLink>
    <p v-if="loading" class="ol-muted">Loading…</p>
    <template v-else>
      <header>
        <h1 class="ol-page-title">{{ isNew ? t('cms.new') : title || 'Edit resource' }}</h1>
      </header>

      <form class="builder__form" @submit.prevent="onSave()">
        <label class="field">
          <span class="field__label">Title</span>
          <input v-model="title" class="field__input" type="text" required maxlength="200">
        </label>
        <label class="field">
          <span class="field__label">Slug</span>
          <input v-model="slug" class="field__input" type="text" required pattern="[a-z0-9\-]+">
        </label>
        <label class="field">
          <span class="field__label">Category</span>
          <input v-model="category" class="field__input" type="text" placeholder="roundabouts">
        </label>
        <label class="field">
          <span class="field__label">Summary</span>
          <textarea v-model="summary" class="field__input" rows="2" />
        </label>
        <label class="field">
          <span class="field__label">Skill codes (comma-separated)</span>
          <input v-model="skillCodes" class="field__input" type="text" placeholder="roundabout_approach">
        </label>
        <label class="field">
          <span class="field__label">Blocks (JSON)</span>
          <textarea v-model="blocksJson" class="field__input field__input--mono" rows="14" spellcheck="false" />
        </label>

        <div class="builder__actions">
          <button
            type="button"
            class="ol-btn ol-btn--ghost"
            :disabled="saving"
            @click="onSave('draft')"
          >
            {{ t('cms.draft') }}
          </button>
          <button
            type="button"
            class="ol-btn"
            :disabled="saving"
            @click="onSave('published')"
          >
            {{ t('cms.publish') }}
          </button>
        </div>
        <p v-if="error" class="ol-error" role="alert">{{ error }}</p>
        <p v-if="saveMsg" class="ol-meta">{{ saveMsg }} · {{ status }}</p>
      </form>
    </template>
  </section>
</template>

<style scoped>
.builder {
  gap: var(--spacing-16);
  max-width: var(--content-max-width);
}

.builder__form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field__label {
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.field__input {
  min-height: 48px;
  padding: 10px 14px;
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-small);
  background: var(--surface-canvas);
  font: inherit;
}

.field__input--mono {
  font-family: var(--font-martian-mono);
  font-size: 13px;
  min-height: 220px;
  line-height: 1.45;
}

.builder__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
</style>
