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
      <PortalMetricStrip class="progress__summary">
        <PortalMetric :value="data.summary.developing" :label="t('home.developing')" compact />
        <PortalMetric :value="data.summary.confident" :label="t('home.confident')" compact />
        <PortalMetric :value="data.summary.practising" label="Practising" compact />
        <PortalMetric :value="data.summary.introduced" label="Introduced" compact />
      </PortalMetricStrip>

      <section v-if="data.insights.length" class="progress__insights">
        <PortalInsight v-for="(line, i) in data.insights.slice(0, 3)" :key="i" :text="line" />
      </section>

      <PortalMainGrid variant="master-detail">
        <div class="progress__skills">
          <section v-if="data.practised_counts.length" class="progress__practised portal-hide-desktop" aria-labelledby="practised">
            <h2 id="practised" class="progress__section">{{ t('progress.practised') }}</h2>
            <PortalSkillBars :items="data.practised_counts.slice(0, 8)" @select="onSelectPractised" />
          </section>

          <section class="progress__cats" aria-labelledby="by-cat">
            <h2 id="by-cat" class="progress__section">{{ t('progress.byCategory') }}</h2>
            <p v-if="!data.categories.length" class="progress__empty">{{ t('progress.noRatings') }}</p>
            <div v-for="cat in data.categories" :key="cat.code" class="cat">
              <h3 class="cat__title">{{ cat.label }}</h3>
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
                      {{ skill.rating_label || t('progress.rating.none') }}
                    </span>
                  </button>
                </li>
              </ul>
            </div>
          </section>
        </div>

        <div class="progress__detail">
          <template v-if="selected">
            <h2 class="progress__detail-title">{{ selected.label }}</h2>
            <p class="progress__detail-rating">
              {{ selected.rating_label || t('progress.rating.none') }}
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
import type { PortalPractisedCount, PortalProgress, PortalSkill } from '~/composables/usePortal'

definePageMeta({ layout: 'portal' })
useHead({ title: 'Your skills · OwnLane' })

const { t } = usePortalI18n()
const { fetchProgress } = usePortal()

const data = ref<PortalProgress | null>(null)
const loading = ref(true)
const error = ref('')
const selected = ref<PortalSkill | null>(null)

function ratingWord(rating: string): string {
  const key = `progress.rating.${rating}` as const
  const mapped = t(key)
  return mapped === key ? rating : mapped
}

function onSelectPractised(item: PortalPractisedCount) {
  if (!data.value) return
  for (const cat of data.value.categories) {
    const found = cat.skills.find(s => s.id === item.skill_id)
    if (found) {
      selected.value = found
      return
    }
  }
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await fetchProgress()
    const firstRated = data.value.categories
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
  margin-bottom: var(--spacing-8);
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
