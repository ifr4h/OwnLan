<script setup lang="ts">
type ContentSummary = {
  id: number
  slug: string
  title: string
  category: string
  status: string
  version: number
  published_at: string | null
  updated_at: string
}

const { t } = useTeachingI18n()
const items = ref<ContentSummary[]>([])
const loading = ref(true)
const error = ref('')

useHead({ title: 'Learning contents · OwnLane' })

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ items: ContentSummary[] }>('/learning/contents')
    items.value = res.items ?? []
  } catch (e) {
    error.value = extractApiError(e, 'Could not load contents.')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <section class="cms ol-page">
    <header class="cms__head">
      <div>
        <p class="ol-eyebrow">Admin</p>
        <h1 class="ol-page-title">{{ t('cms.title') }}</h1>
      </div>
      <NuxtLink to="/learning/contents/new" class="ol-btn ol-btn--sm">
        {{ t('cms.new') }}
      </NuxtLink>
    </header>

    <p v-if="loading" class="ol-muted">Loading…</p>
    <p v-else-if="error" class="ol-error" role="alert">{{ error }}</p>

    <ul v-else class="cms__list">
      <li v-for="item in items" :key="item.id">
        <NuxtLink :to="`/learning/contents/${item.id}`" class="cms__row">
          <span class="cms__title">{{ item.title }}</span>
          <span class="cms__meta">
            {{ item.status === 'published' ? t('cms.published') : t('cms.draft') }}
            · {{ item.category }}
          </span>
        </NuxtLink>
      </li>
      <li v-if="!items.length" class="ol-muted">No learning contents yet.</li>
    </ul>
  </section>
</template>

<style scoped>
.cms {
  gap: var(--spacing-20);
  max-width: var(--content-max-width);
}

.cms__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.cms__list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.cms__row {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-height: 56px;
  padding: 12px 0;
  text-decoration: none;
  color: inherit;
  border-bottom: 1px solid var(--color-border);
}

.cms__title {
  font-size: var(--text-body-sm);
}

.cms__meta {
  font-size: var(--text-meta);
  color: var(--color-muted);
}
</style>
