<script setup lang="ts">
import { TEMPLATE_META } from '~/utils/teaching/templates'
import type { TeachingResourceSummary } from '~/composables/useTeaching'

const { t } = useTeachingI18n()
const { fetchResources } = useTeaching()

const resources = ref<TeachingResourceSummary[]>([])
const loading = ref(true)
const error = ref('')
const category = ref<string | null>(null)

const categories = [
  { code: 'junctions', label: t('home.categories.junctions') },
  { code: 'roundabouts', label: t('home.categories.roundabouts') },
  { code: 'roads', label: t('home.categories.roads') },
  { code: 'manoeuvres', label: t('home.categories.manoeuvres') },
  { code: 'basics', label: t('home.categories.basics') },
]

const favourites = computed(() => resources.value.filter(r => r.is_favourite))
const recent = computed(() => resources.value.slice(0, 8))

const quickTemplates = computed(() => {
  if (!category.value) return TEMPLATE_META.slice(0, 6)
  return TEMPLATE_META.filter(tm => tm.category === category.value)
})

useHead({ title: 'Teaching Studio · OwnLane' })

async function load() {
  loading.value = true
  error.value = ''
  try {
    resources.value = await fetchResources()
  } catch (e) {
    error.value = extractApiError(e, 'Could not load resources.')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <section class="teach ol-page">
    <header class="teach__hero">
      <p class="ol-eyebrow">{{ t('nav.studio') }}</p>
      <h1 class="ol-page-title">{{ t('home.prompt') }}</h1>
    </header>

    <nav class="teach__starts" aria-label="Start explaining">
      <NuxtLink to="/teaching/board?template=blank" class="teach__start">
        <span class="teach__start-title">{{ t('home.roadBoard') }}</span>
        <span class="teach__start-hint">{{ t('home.roadBoardHint') }}</span>
      </NuxtLink>
      <NuxtLink to="/teaching/real-road" class="teach__start">
        <span class="teach__start-title">{{ t('home.realJunction') }}</span>
        <span class="teach__start-hint">{{ t('home.realJunctionHint') }}</span>
      </NuxtLink>
      <NuxtLink to="/lessons" class="teach__start">
        <span class="teach__start-title">{{ t('home.previousDrive') }}</span>
        <span class="teach__start-hint">{{ t('home.previousDriveHint') }}</span>
      </NuxtLink>
      <a href="#resources" class="teach__start">
        <span class="teach__start-title">{{ t('home.myResources') }}</span>
        <span class="teach__start-hint">{{ t('home.myResourcesHint') }}</span>
      </a>
    </nav>

    <section class="teach__quick" aria-labelledby="quick-title">
      <h2 id="quick-title" class="teach__section">{{ t('home.quickStart') }}</h2>
      <div class="teach__chips">
        <button
          type="button"
          class="teach__chip"
          :class="{ 'teach__chip--on': !category }"
          @click="category = null"
        >
          All
        </button>
        <button
          v-for="cat in categories"
          :key="cat.code"
          type="button"
          class="teach__chip"
          :class="{ 'teach__chip--on': category === cat.code }"
          @click="category = cat.code"
        >
          {{ cat.label }}
        </button>
      </div>
      <ul class="teach__templates">
        <li v-for="tm in quickTemplates" :key="tm.code">
          <NuxtLink :to="`/teaching/board?template=${tm.code}`" class="teach__tpl">
            {{ tm.label }}
            <span aria-hidden="true">→</span>
          </NuxtLink>
        </li>
      </ul>
    </section>

    <p v-if="loading" class="ol-muted">Loading…</p>
    <p v-else-if="error" class="ol-error" role="alert">{{ error }}</p>

    <section id="resources" class="teach__lists">
      <div v-if="favourites.length" class="teach__list-block">
        <h2 class="teach__section">{{ t('home.favourites') }}</h2>
        <ul class="teach__list">
          <li v-for="item in favourites" :key="item.id">
            <NuxtLink :to="`/teaching/${item.id}`" class="teach__row">
              <span class="teach__row-title">★ {{ item.title }}</span>
              <span class="teach__row-meta">{{ item.template_code || item.kind }}</span>
            </NuxtLink>
          </li>
        </ul>
      </div>

      <div class="teach__list-block">
        <h2 class="teach__section">{{ t('home.recent') }}</h2>
        <ul v-if="recent.length" class="teach__list">
          <li v-for="item in recent" :key="item.id">
            <NuxtLink :to="`/teaching/${item.id}`" class="teach__row">
              <span class="teach__row-title">{{ item.title }}</span>
              <span class="teach__row-meta">{{ item.template_code || item.kind }}</span>
            </NuxtLink>
          </li>
        </ul>
        <p v-else class="teach__empty">{{ t('home.empty') }}</p>
      </div>
    </section>
  </section>
</template>

<style scoped>
.teach {
  gap: var(--spacing-28);
  max-width: var(--content-wide-max);
}

.teach__hero {
  margin-left: calc(-1 * var(--spacing-16));
  margin-right: calc(-1 * var(--spacing-16));
  padding: var(--spacing-28) var(--spacing-16);
  background: linear-gradient(
    160deg,
    var(--color-frost-green) 0%,
    var(--color-chalk-green) 55%,
    #fff 100%
  );
}

@media (min-width: 900px) {
  .teach__hero {
    margin-left: 0;
    margin-right: 0;
    border-radius: var(--radius-cards);
  }
}

.teach__starts {
  display: flex;
  flex-direction: column;
  gap: 2px;
  background: var(--color-frost-green);
  border-radius: var(--radius-panel);
  overflow: hidden;
}

.teach__start {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 16px 18px;
  min-height: 72px;
  justify-content: center;
  text-decoration: none;
  color: inherit;
  background: rgba(255, 255, 255, 0.55);
  border-bottom: 1px solid rgba(226, 240, 231, 0.9);
  transition: background-color var(--duration-fast) ease;
}

.teach__start:last-child {
  border-bottom: none;
}

.teach__start:hover,
.teach__start:focus-visible {
  background: var(--color-paper-white);
}

.teach__start-title {
  font-size: var(--text-body);
  letter-spacing: var(--tracking-body-sm);
}

.teach__start-hint {
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.teach__section {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
  margin-bottom: var(--spacing-12);
}

.teach__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: var(--spacing-12);
}

.teach__chip {
  min-height: 40px;
  padding: 6px 14px;
  border: none;
  border-radius: var(--radius-tags);
  background: var(--color-frost-green);
  font: inherit;
  font-size: var(--text-meta);
  cursor: pointer;
}

.teach__chip--on {
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
}

.teach__templates {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.teach__tpl {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 48px;
  padding: 10px 4px;
  text-decoration: none;
  color: inherit;
  font-size: var(--text-body-sm);
  border-bottom: 1px solid var(--color-border);
}

.teach__lists {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-28);
}

.teach__list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.teach__row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  min-height: 52px;
  padding: 10px 0;
  text-decoration: none;
  color: inherit;
  border-bottom: 1px solid var(--color-border);
}

.teach__row-title {
  font-size: var(--text-body-sm);
}

.teach__row-meta {
  font-size: var(--text-meta);
  color: var(--color-muted);
  flex-shrink: 0;
}

.teach__empty {
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

@media (prefers-reduced-motion: reduce) {
  .teach__start {
    transition: none;
  }
}
</style>
