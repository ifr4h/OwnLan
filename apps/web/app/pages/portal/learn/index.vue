<script setup lang="ts">
definePageMeta({ layout: 'portal' })

const { t } = usePortalI18n()

type LearnHome = {
  next_focus: string | null
  for_you: Array<{
    reason: string
    reason_label: string
    content: { slug: string; title: string; summary: string | null; category_label: string }
  }>
  quick_review: {
    reason: string
    reason_label: string
    prompt?: string
    content: { slug: string; title: string; summary: string | null; category_label: string }
  } | null
  from_last_lessons: Array<{
    id: number
    title: string
    note: string | null
    lesson_id: number
  }>
  places_to_review: Array<{
    id: number
    label: string
    note: string | null
    lesson_id: number
    route_id: number
  }>
  explore: Array<{
    code: string
    label: string
    items: Array<{ slug: string; title: string; summary: string | null }>
  }>
}

const data = ref<LearnHome | null>(null)
const loading = ref(true)
const error = ref('')
const { isDesktop } = usePortalLayout()

const activeCategory = ref<string | null>(null)
const layoutReady = ref(false)

const visibleExplore = computed(() => {
  if (!data.value) return []
  if (!layoutReady.value || !isDesktop.value || !activeCategory.value) {
    return data.value.explore
  }
  return data.value.explore.filter(c => c.code === activeCategory.value)
})

useHead({ title: 'Learn · OwnLane' })

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await apiFetch<LearnHome>('/portal/learn')
    activeCategory.value = data.value.explore[0]?.code ?? null
  } catch (e) {
    error.value = extractApiError(e, t('common.errorGeneric'))
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  layoutReady.value = true
  void load()
})
</script>

<template>
  <PortalPage>
    <template #header>
      <div class="learn__head">
        <h1 class="portal-page__title">{{ t('learn.title') }}</h1>
        <p v-if="data?.next_focus" class="learn__focus">{{ data.next_focus }}</p>
      </div>
    </template>

    <p v-if="loading" class="learn__msg">{{ t('common.loading') }}</p>
    <p v-else-if="error" class="learn__err" role="alert">{{ error }}</p>

    <PortalMainGrid v-else-if="data" variant="learn">
      <nav v-if="data.explore.length" class="learn__nav portal-hide-mobile" aria-label="Categories">
        <button
          v-for="cat in data.explore"
          :key="cat.code"
          type="button"
          class="learn__nav-item"
          :class="{ 'learn__nav-item--active': activeCategory === cat.code }"
          @click="activeCategory = cat.code"
        >
          {{ cat.label }}
        </button>
      </nav>

      <div class="learn__content">
        <section v-if="data.quick_review" class="learn__quick">
          <p class="learn__quick-eyebrow">{{ t('learn.quickReview') }}</p>
          <p v-if="data.quick_review.prompt" class="learn__quick-prompt">
            {{ data.quick_review.prompt }}
          </p>
          <NuxtLink
            :to="`/portal/learn/${data.quick_review.content.slug}`"
            class="learn__quick-cta"
          >
            {{ data.quick_review.content.title }}
            <span aria-hidden="true">→</span>
          </NuxtLink>
        </section>

        <section v-if="data.for_you.length" class="learn__section">
          <h2 class="learn__h">{{ t('learn.forYou') }}</h2>
          <ul class="learn__list">
            <li v-for="item in data.for_you" :key="item.content.slug">
              <NuxtLink :to="`/portal/learn/${item.content.slug}`" class="learn__row">
                <span class="learn__row-title">{{ item.content.title }}</span>
                <span class="learn__row-meta">{{ item.reason_label }}</span>
              </NuxtLink>
            </li>
          </ul>
        </section>

        <section v-if="data.from_last_lessons.length" class="learn__section">
          <h2 class="learn__h">{{ t('learn.fromLessons') }}</h2>
          <ul class="learn__list">
            <li v-for="item in data.from_last_lessons" :key="item.id">
              <NuxtLink :to="`/portal/recap/${item.lesson_id}`" class="learn__row">
                <span class="learn__row-title">{{ item.title }}</span>
                <span v-if="item.note" class="learn__row-meta">{{ item.note }}</span>
              </NuxtLink>
            </li>
          </ul>
        </section>

        <section v-if="data.places_to_review.length" class="learn__section">
          <h2 class="learn__h">{{ t('learn.places') }}</h2>
          <ul class="learn__list">
            <li v-for="place in data.places_to_review" :key="place.id">
              <NuxtLink :to="`/portal/routes/${place.route_id}`" class="learn__row">
                <span class="learn__row-title">{{ place.label }}</span>
                <span v-if="place.note" class="learn__row-meta">{{ place.note }}</span>
              </NuxtLink>
            </li>
          </ul>
        </section>

        <section
          v-for="cat in visibleExplore"
          :key="cat.code"
          class="learn__section"
          :id="`learn-cat-${cat.code}`"
        >
          <h2 class="learn__h">{{ cat.label }}</h2>
          <ul class="learn__list">
            <li v-for="item in cat.items" :key="item.slug">
              <NuxtLink :to="`/portal/learn/${item.slug}`" class="learn__row">
                <span class="learn__row-title">{{ item.title }}</span>
                <span v-if="item.summary" class="learn__row-meta">{{ item.summary }}</span>
              </NuxtLink>
            </li>
          </ul>
        </section>

        <p
          v-if="!data.quick_review && !data.for_you.length && !data.from_last_lessons.length && !data.explore.length"
          class="learn__msg"
        >
          {{ t('learn.empty') }}
        </p>
      </div>

      <PortalContextRail class="portal-hide-mobile">
        <PortalContextCard
          v-if="data.next_focus"
          :title="t('journey.currentFocus')"
          action-to="/portal/progress"
          :action-label="t('home.openProgress')"
        >
          {{ data.next_focus }}
        </PortalContextCard>
        <PortalContextCard
          v-if="data.quick_review"
          :title="t('learn.quickReview')"
          :action-to="`/portal/learn/${data.quick_review.content.slug}`"
          :action-label="data.quick_review.content.title"
        >
          {{ data.quick_review.reason_label }}
        </PortalContextCard>
      </PortalContextRail>
    </PortalMainGrid>
  </PortalPage>
</template>

<style scoped>
.learn__head {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.learn__focus {
  font-size: var(--text-body-sm);
  color: var(--color-muted);
  max-width: 48ch;
}

.learn__nav {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4);
}

.learn__nav-item {
  min-height: 40px;
  padding: 0 var(--spacing-12);
  border: none;
  border-radius: var(--radius-small);
  background: transparent;
  font: inherit;
  font-size: var(--text-body-sm);
  text-align: left;
  cursor: pointer;
  color: var(--color-muted);
}

.learn__nav-item--active,
.learn__nav-item:hover {
  background: var(--color-frost-green);
  color: var(--color-ink-black);
}

.learn__content {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-28);
  min-width: 0;
}

.learn__quick {
  padding: var(--spacing-20);
  background: linear-gradient(145deg, var(--color-frost-green), var(--color-success-wash));
  border-radius: var(--radius-panel);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.learn__quick-eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-ownlane-green);
  margin: 0;
}

.learn__quick-prompt {
  margin: 0;
  font-size: var(--text-body-sm);
  max-width: 36ch;
}

.learn__quick-cta {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-8);
  min-height: 48px;
  width: fit-content;
  margin-top: var(--spacing-4);
  padding: 10px 18px;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  text-decoration: none;
  font-size: var(--text-body-sm);
  box-shadow: var(--shadow-button);
}

.learn__h {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
  margin-bottom: var(--spacing-8);
}

.learn__list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.learn__row {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-height: 56px;
  padding: 12px 0;
  text-decoration: none;
  color: inherit;
  border-bottom: 1px solid var(--color-border);
}

.learn__row-title {
  font-size: var(--text-body-sm);
}

.learn__row-meta {
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.learn__msg {
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.learn__err {
  color: var(--color-danger);
  font-size: var(--text-body-sm);
}
</style>
