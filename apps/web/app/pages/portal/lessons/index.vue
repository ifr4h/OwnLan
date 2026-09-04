<template>
  <PortalPage>
    <template #header>
      <h1 class="portal-page__title">{{ t('lessons.title') }}</h1>
    </template>

    <template v-if="loading && !home">
      <PortalSkeleton variant="line" />
      <PortalSkeleton variant="block" />
    </template>

    <p v-else-if="error" class="lessons__error" role="alert">{{ error }}</p>

    <PortalMainGrid v-else-if="home" variant="master-detail">
      <aside class="lessons__list">
        <section v-if="upcoming.length" aria-labelledby="upcoming">
          <h2 id="upcoming" class="lessons__section">{{ t('lessons.upcoming') }}</h2>
          <ul class="lessons__items">
            <li v-for="lesson in upcoming" :key="lesson.id">
              <button
                type="button"
                class="lessons__item"
                :class="{ 'lessons__item--active': selected?.id === lesson.id }"
                @click="selected = lesson"
              >
                <span class="lessons__day">{{ lesson.starts_at_day || lesson.starts_at_display }}</span>
                <span class="lessons__time">{{ lesson.starts_at_time }}–{{ lesson.ends_at_time }}</span>
                <span v-if="lesson.next_focus" class="lessons__focus">{{ lesson.next_focus }}</span>
              </button>
            </li>
          </ul>
        </section>

        <section v-if="previous.length" aria-labelledby="previous">
          <h2 id="previous" class="lessons__section">{{ t('lessons.previous') }}</h2>
          <ul class="lessons__items">
            <li v-for="lesson in previous" :key="lesson.id">
              <button
                type="button"
                class="lessons__item"
                :class="{ 'lessons__item--active': selected?.id === lesson.id }"
                @click="selected = lesson"
              >
                <span class="lessons__day">{{ lesson.starts_at_day || lesson.starts_at_display }}</span>
                <span class="lessons__time">{{ lesson.starts_at_time }}–{{ lesson.ends_at_time }}</span>
                <span v-if="lesson.skills?.length" class="lessons__focus">
                  {{ lesson.skills.slice(0, 2).join(' · ') }}
                </span>
              </button>
            </li>
          </ul>
        </section>

        <p v-if="!upcoming.length && !previous.length" class="lessons__empty">
          {{ t('lessons.empty') }}
        </p>
      </aside>

      <div v-if="selected" class="lessons__detail">
        <h2 class="lessons__detail-title">
          {{ selected.starts_at_day || selected.starts_at_display }}
        </h2>
        <p class="lessons__detail-meta">
          {{ selected.starts_at_time }}–{{ selected.ends_at_time }}
          <span v-if="selected.duration_label"> · {{ selected.duration_label }}</span>
        </p>
        <p v-if="selected.pickup_short || selected.pickup_address" class="lessons__detail-pickup">
          {{ selected.pickup_short || selected.pickup_address }}
        </p>
        <p v-if="selected.next_focus" class="lessons__detail-focus">{{ selected.next_focus }}</p>
        <p v-else-if="selected.learner_summary" class="lessons__detail-focus">
          {{ selected.learner_summary }}
        </p>

        <div class="lessons__actions">
          <NuxtLink
            v-if="isUpcoming(selected)"
            to="/portal/prepare"
            class="lessons__cta"
          >
            {{ t('home.prepareCta') }}
          </NuxtLink>
          <NuxtLink
            v-else
            :to="`/portal/recap/${selected.id}`"
            class="lessons__cta"
          >
            {{ t('home.viewRecap') }}
          </NuxtLink>
          <NuxtLink
            v-if="!isUpcoming(selected)"
            :to="`/portal/lessons/${selected.id}/playback`"
            class="lessons__link"
          >
            {{ t('lessons.playback') }}
          </NuxtLink>
        </div>
      </div>

      <p v-else-if="upcoming.length || previous.length" class="lessons__pick portal-hide-mobile">
        {{ t('lessons.selectHint') }}
      </p>
    </PortalMainGrid>
  </PortalPage>
</template>

<script setup lang="ts">
import type { PortalHome, PortalLesson } from '~/composables/usePortal'

definePageMeta({ layout: 'portal' })
useHead({ title: 'Lessons · OwnLane' })

const { t } = usePortalI18n()
const { fetchHome, readCachedHome } = usePortal()

const home = ref<PortalHome | null>(null)
const loading = ref(true)
const error = ref('')
const selected = ref<PortalLesson | null>(null)

const upcoming = computed(() => {
  const lessons = home.value?.upcoming_lessons ?? []
  const next = home.value?.next_lesson
  if (!next) return lessons
  if (lessons.some(l => l.id === next.id)) return lessons
  return [next, ...lessons]
})

const previous = computed(() => home.value?.previous_lessons ?? [])

function isUpcoming(lesson: PortalLesson): boolean {
  return upcoming.value.some(l => l.id === lesson.id)
}

async function load() {
  loading.value = true
  error.value = ''
  const cached = readCachedHome()
  if (cached) home.value = cached
  try {
    home.value = await fetchHome({ allowStale: true })
    selected.value =
      home.value.next_lesson ??
      home.value.upcoming_lessons[0] ??
      home.value.previous_lessons[0] ??
      null
  } catch (e) {
    error.value = extractApiError(e, t('common.errorGeneric'))
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.lessons__error {
  color: var(--color-danger);
  font-size: var(--text-body-sm);
}

.lessons__section {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
  margin-bottom: var(--spacing-12);
}

.lessons__items {
  list-style: none;
  margin: 0 0 var(--spacing-24);
  padding: 0;
}

.lessons__item {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  min-height: 56px;
  padding: var(--spacing-12) 0;
  border: none;
  border-bottom: 1px solid var(--color-border);
  background: transparent;
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.lessons__item--active {
  color: var(--color-ownlane-green);
}

.lessons__day {
  font-size: var(--text-body-sm);
  font-weight: 500;
}

.lessons__time,
.lessons__focus {
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.lessons__empty,
.lessons__pick {
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.lessons__detail {
  padding: var(--spacing-8) 0;
}

.lessons__detail-title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-md);
  letter-spacing: var(--tracking-heading-md);
}

.lessons__detail-meta,
.lessons__detail-pickup {
  font-size: var(--text-body-sm);
  color: var(--color-muted);
  margin-top: var(--spacing-4);
}

.lessons__detail-focus {
  margin-top: var(--spacing-16);
  font-size: var(--text-body);
  line-height: var(--leading-body);
}

.lessons__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-12);
  margin-top: var(--spacing-24);
}

.lessons__cta {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  padding: 10px 18px;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  text-decoration: none;
  font-size: var(--text-body-sm);
  box-shadow: var(--shadow-button);
}

.lessons__link {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  color: var(--color-ownlane-green);
  text-decoration: none;
  font-size: var(--text-body-sm);
}

@media (max-width: 1023px) {
  .lessons__detail {
    margin-top: var(--spacing-24);
    padding-top: var(--spacing-24);
    border-top: 1px solid var(--color-border);
  }
}
</style>
