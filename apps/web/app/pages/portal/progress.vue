<template>
  <PortalPage>
    <template #header>
      <h1 class="portal-page__title">{{ t('progress.title') }}</h1>
    </template>

    <template v-if="loading && !data">
      <PortalSkeleton variant="block" />
      <PortalSkeleton variant="line" />
    </template>
    <p v-else-if="error" class="progress__error" role="alert">{{ error }}</p>

    <template v-else-if="data">
      <header class="progress__lede">
        <p class="progress__lede-line">{{ ledeLine }}</p>
        <p v-if="data.next_focus" class="progress__focus">
          Working on: {{ data.next_focus }}
        </p>
        <p v-if="data.went_well" class="progress__went">
          Last lesson: {{ data.went_well }}
          <NuxtLink
            v-if="data.went_well_lesson_id"
            :to="`/portal/recap/${data.went_well_lesson_id}`"
            class="progress__went-link"
          >
            Recap
          </NuxtLink>
        </p>
      </header>

      <PortalMetricStrip class="progress__summary">
        <PortalMetric :value="data.summary.confident" :label="t('home.confident')" compact />
        <PortalMetric :value="data.summary.developing" :label="t('progress.rating.developing')" compact />
        <PortalMetric :value="data.summary.practising" :label="t('progress.rating.practising')" compact />
        <PortalMetric :value="data.summary.introduced" :label="t('progress.rating.introduced')" compact />
      </PortalMetricStrip>

      <section v-if="data.insights.length" class="progress__insights">
        <PortalInsight v-for="(line, i) in data.insights.slice(0, 3)" :key="i" :text="line" />
      </section>

      <PortalMainGrid variant="master-detail">
        <div class="progress__skills">
          <section class="progress__cats" aria-labelledby="by-cat">
            <h2 id="by-cat" class="progress__section">{{ t('progress.byCategory') }}</h2>
            <p v-if="!data.categories.length" class="progress__empty">{{ t('progress.noRatings') }}</p>
            <div v-for="cat in data.categories" :key="cat.code" class="cat">
              <h3 class="cat__title">{{ cat.label }}</h3>
              <p v-if="catNote(cat)" class="cat__note">{{ catNote(cat) }}</p>
              <ul class="cat__list">
                <li v-for="skill in cat.skills" :key="skill.id">
                  <button
                    type="button"
                    class="cat__skill"
                    :class="{ 'cat__skill--active': selected?.id === skill.id }"
                    @click="selected = skill"
                  >
                    <span class="cat__name">{{ skill.label }}</span>
                    <span class="cat__rating" :data-rating="skill.rating || 'none'">
                      {{ displayRating(skill) }}
                    </span>
                  </button>
                  <p v-if="skillNote(skill)" class="cat__skill-note">{{ skillNote(skill) }}</p>
                </li>
              </ul>
            </div>
          </section>
        </div>

        <div class="progress__detail">
          <template v-if="selected">
            <h2 class="progress__detail-title">{{ selected.label }}</h2>
            <p class="progress__detail-rating">
              {{ displayRating(selected) }}
            </p>
            <p v-if="skillNote(selected)" class="progress__detail-note">
              {{ skillNote(selected) }}
            </p>

            <section class="progress__history" aria-labelledby="over-time">
              <h3 id="over-time" class="progress__section">{{ t('progress.overTime') }}</h3>
              <p v-if="!selected.history.length" class="progress__empty">{{ t('progress.selectSkill') }}</p>
              <ol v-else class="spark" aria-label="Rating history">
                <li v-for="(pt, i) in selected.history" :key="i" class="spark__item">
                  <span class="spark__rank" :aria-label="`Rank ${pt.rank}`">
                    <span
                      class="spark__dot"
                      :style="{ height: `${(pt.rank / 4) * 100}%` }"
                      :data-rank="pt.rank"
                    />
                  </span>
                  <span class="spark__label">{{ ratingWord(pt.rating) }}</span>
                </li>
              </ol>
            </section>

            <NuxtLink :to="`/portal/skills/${selected.code}`" class="progress__skill-link">
              {{ t('progress.viewSkillDetail') }}
              <span aria-hidden="true">→</span>
            </NuxtLink>
          </template>
          <p v-else class="progress__empty progress__pick portal-hide-mobile">
            {{ t('progress.selectSkillDesktop') }}
          </p>
        </div>
      </PortalMainGrid>
    </template>
  </PortalPage>
</template>

<script setup lang="ts">
import type { PortalProgress, PortalSkill, PortalSkillCategory } from '~/composables/usePortal'

definePageMeta({ layout: 'portal' })
useHead({ title: 'Your skills · OwnLane' })

type Note = { body: string; learner_visible?: boolean }

const { t } = usePortalI18n()
const { fetchProgress } = usePortal()

const data = ref<(PortalProgress & {
  categories: Array<PortalSkillCategory & {
    note?: Note | null
    skills: Array<PortalSkill & { note?: Note | null }>
  }>
}) | null>(null)
const loading = ref(true)
const error = ref('')
const selected = ref<(PortalSkill & { note?: Note | null }) | null>(null)

const ledeLine = computed(() => {
  if (!data.value) return ''
  const total = data.value.categories.reduce((n, c) => n + c.skills.length, 0)
  const started = data.value.summary.skills_with_rating
  const practical = data.value.practical?.countdown_label
  const base = total
    ? `${started} of ${total} skills started`
    : t('progress.noRatings')
  return practical ? `${base}. ${practical}.` : `${base}.`
})

function ratingWord(rating: string): string {
  const key = `progress.rating.${rating}` as const
  const mapped = t(key)
  return mapped === key ? rating : mapped
}

function displayRating(skill: PortalSkill): string {
  if (!skill.rating) return t('progress.rating.none')
  return ratingWord(skill.rating)
}

function catNote(cat: PortalSkillCategory & { note?: Note | null }): string | null {
  const body = cat.note?.body?.trim()
  return body || null
}

function skillNote(skill: PortalSkill & { note?: Note | null }): string | null {
  const body = skill.note?.body?.trim()
  return body || null
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await fetchProgress() as typeof data.value
    const firstRated = data.value?.categories
      .flatMap(c => c.skills)
      .find(s => s.history.length > 0)
    selected.value = firstRated ?? null
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
.progress__error {
  color: var(--color-danger);
  font-size: var(--text-body-sm);
}

.progress__lede {
  margin-bottom: var(--spacing-8);
  padding-bottom: var(--spacing-8);
  border-bottom: 1px solid var(--color-border);
}

.progress__lede-line {
  margin: 0;
  font-size: var(--text-body);
  color: var(--color-ink-black);
  font-weight: 600;
}

.progress__focus,
.progress__went {
  margin: 8px 0 0;
  font-size: var(--text-body-sm);
  color: var(--color-bark);
  line-height: 1.45;
}

.progress__went-link {
  margin-left: 6px;
  color: var(--color-ownlane-green);
  font-weight: 650;
  text-decoration: none;
}

.progress__summary {
  margin-bottom: var(--spacing-8);
}

.progress__insights {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
  margin-bottom: var(--spacing-8);
}

.progress__section {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
  margin-bottom: var(--spacing-12);
}

.progress__empty {
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.progress__detail-title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-md);
  letter-spacing: var(--tracking-heading-md);
}

.progress__detail-rating {
  margin-top: var(--spacing-4);
  font-size: var(--text-body-sm);
  color: var(--color-ownlane-green);
}

.progress__detail-note {
  margin: 12px 0 0;
  padding: 10px 12px;
  background: var(--color-parchment);
  border-left: 3px solid color-mix(in srgb, var(--color-ownlane-green) 50%, var(--color-border));
  font-size: var(--text-body-sm);
  color: var(--color-bark);
  line-height: 1.45;
}

.progress__skill-link {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-8);
  margin-top: var(--spacing-24);
  min-height: 44px;
  color: var(--color-ownlane-green);
  text-decoration: none;
  font-size: var(--text-body-sm);
}

.cat {
  margin-bottom: var(--spacing-24);
}

.cat__title {
  font-size: var(--text-body-sm);
  font-weight: 700;
  margin-bottom: var(--spacing-8);
}

.cat__note {
  margin: 0 0 10px;
  padding: 8px 10px;
  background: var(--color-parchment);
  border-left: 3px solid color-mix(in srgb, var(--color-ownlane-green) 50%, var(--color-border));
  font-size: 13px;
  color: var(--color-bark);
  line-height: 1.4;
}

.cat__list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.cat__skill {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-12);
  min-height: 48px;
  padding: 0;
  border: none;
  border-bottom: 1px solid var(--color-border);
  background: transparent;
  font: inherit;
  cursor: pointer;
  text-align: left;
}

.cat__skill--active {
  color: var(--color-ownlane-green);
}

.cat__name {
  font-size: var(--text-body-sm);
}

.cat__rating {
  font-size: var(--text-meta);
  color: var(--color-muted);
  white-space: nowrap;
}

.cat__rating[data-rating='confident'] {
  color: var(--color-ownlane-green);
}

.cat__skill-note {
  margin: 0 0 8px;
  padding: 0 0 8px;
  font-size: 12px;
  color: var(--color-muted);
  border-bottom: 1px solid var(--color-border);
}

.spark {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  align-items: flex-end;
  gap: var(--spacing-8);
  min-height: 120px;
  overflow-x: auto;
}

@media (min-width: 1024px) {
  .spark {
    min-height: 160px;
  }
}

.spark__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  min-width: 48px;
}

.spark__rank {
  display: flex;
  align-items: flex-end;
  height: 80px;
  width: 12px;
}

.spark__dot {
  display: block;
  width: 100%;
  min-height: 8px;
  border-radius: 999px 999px 4px 4px;
  background: var(--color-soft-sage);
}

.spark__dot[data-rank='3'],
.spark__dot[data-rank='4'] {
  background: var(--color-ownlane-green);
}

.spark__label {
  font-size: 10px;
  color: var(--color-muted);
  text-align: center;
  max-width: 4.5rem;
  line-height: 1.2;
}

@media (max-width: 1023px) {
  .progress__detail {
    margin-top: var(--spacing-24);
    padding-top: var(--spacing-24);
    border-top: 1px solid var(--color-border);
  }
}
</style>
