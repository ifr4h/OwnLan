<template>
  <PortalPage>
    <template #header>
      <header v-if="home" class="home__header">
        <p class="portal-page__greeting">{{ home.greeting || t('home.greetingFallback') }}</p>
        <p class="portal-page__meta">
          {{ dateLabel }}
          <span v-if="home.instructor.display_name" class="home__instructor">
            · {{ t('home.withInstructor', { name: home.instructor.display_name }) }}
          </span>
        </p>
      </header>
    </template>

    <template v-if="loading && !home">
      <PortalSkeleton variant="line" width="12rem" />
      <PortalSkeleton variant="hero" />
      <PortalSkeleton variant="block" />
    </template>

    <p v-else-if="error && !home" class="home__error" role="alert">{{ error }}</p>

    <PortalMainGrid v-else-if="home" variant="split">
      <div class="home__main">
        <PortalNextDrive
          :priority="home.priority"
          :lesson="home.next_lesson"
          :focus="home.progress.next_focus"
          :test-label="home.practical_test?.countdown_label"
          :credit-label="home.package_and_balance.credit_label"
          :recap-lesson-id="home.priority.lesson_id ?? home.recent_recap?.lesson_id"
          :booking-cta="bookingCta"
        />

        <PortalSection :title="t('home.journeySnapshot')">
          <PortalMetricStrip>
            <PortalMetric
              :value="home.journey.total_hours_label"
              :label="t('home.hoursDriven')"
              compact
            />
            <PortalMetric
              :value="home.journey.lessons_completed"
              :label="t('home.lessonsDone')"
              compact
            />
            <PortalMetric
              :value="home.progress.summary.developing"
              :label="t('home.developing')"
              compact
            />
            <PortalMetric
              :value="home.progress.summary.confident"
              :label="t('home.confident')"
              compact
            />
          </PortalMetricStrip>
          <NuxtLink to="/portal/journey" class="home__link">
            {{ t('home.openJourney') }}
            <span aria-hidden="true">→</span>
          </NuxtLink>
        </PortalSection>

        <PortalSection v-if="home.recent_recap" :title="t('home.recentRecap')">
          <p class="home__recap-date">
            {{ home.recent_recap.is_today ? t('recap.today') : home.recent_recap.date_label }}
            · {{ home.recent_recap.duration_label }}
          </p>
          <p v-if="home.recent_recap.learner_summary" class="home__recap-body">
            {{ home.recent_recap.learner_summary }}
          </p>
          <p v-else-if="home.recent_recap.skills.length" class="home__recap-body">
            {{ home.recent_recap.skills.slice(0, 3).join(' · ') }}
          </p>
          <NuxtLink :to="`/portal/recap/${home.recent_recap.lesson_id}`" class="home__link">
            {{ t('home.viewRecap') }}
            <span aria-hidden="true">→</span>
          </NuxtLink>
        </PortalSection>

        <PortalSection v-if="displayInsights.length" :title="t('home.insights')">
          <PortalInsight v-for="(line, i) in displayInsights" :key="i" :text="line" />
        </PortalSection>

        <nav class="home__deeper portal-hide-desktop" aria-label="Explore">
          <NuxtLink to="/portal/progress" class="home__deep-link">{{ t('home.openProgress') }}</NuxtLink>
          <NuxtLink v-if="home.routes.count > 0" to="/portal/routes" class="home__deep-link">
            {{ t('home.openRoutes') }}
          </NuxtLink>
          <NuxtLink to="/portal/money" class="home__deep-link">{{ t('home.openMoney') }}</NuxtLink>
          <NuxtLink
            v-if="home.practical_test || home.theory"
            to="/portal/test"
            class="home__deep-link"
          >
            {{ t('home.openTest') }}
          </NuxtLink>
        </nav>
      </div>

      <PortalContextRail class="portal-hide-mobile">
        <PortalContextCard
          v-if="home.progress.next_focus"
          :title="t('journey.currentFocus')"
          action-to="/portal/progress"
          :action-label="t('home.openProgress')"
        >
          {{ home.progress.next_focus }}
        </PortalContextCard>

        <PortalContextCard
          v-if="home.practical_test"
          :title="t('test.practical')"
          action-to="/portal/test"
          :action-label="t('home.openTest')"
        >
          <p class="home__context-strong">{{ home.practical_test.countdown_label }}</p>
          <p v-if="home.practical_test.test_centre" class="home__context-muted">
            {{ home.practical_test.test_centre }}
          </p>
        </PortalContextCard>

        <PortalContextCard
          :title="t('money.title')"
          action-to="/portal/money"
          :action-label="t('home.openMoney')"
        >
          <p class="home__context-strong">{{ home.package_and_balance.credit_label }}</p>
          <p v-if="home.package_and_balance.has_amount_due" class="home__context-warn">
            {{ t('money.amountDue') }}: {{ home.package_and_balance.amount_due_label }}
          </p>
        </PortalContextCard>

        <PortalContextCard
          v-if="home.routes.count > 0"
          :title="t('nav.routes')"
          action-to="/portal/routes"
          :action-label="t('home.openRoutes')"
        >
          {{ t('routes.countHint', { count: home.routes.count }) }}
        </PortalContextCard>
      </PortalContextRail>
    </PortalMainGrid>
  </PortalPage>
</template>

<script setup lang="ts">
import type { PortalHome } from '~/composables/usePortal'

definePageMeta({ layout: 'portal' })
useHead({ title: 'Home · OwnLane' })

const { t } = usePortalI18n()
const { fetchHome, readCachedHome } = usePortal()

const home = ref<PortalHome | null>(null)
const loading = ref(true)
const error = ref('')

const displayInsights = computed(() => (home.value?.insights ?? []).slice(0, 2))

const bookingCta = computed(() => {
  const booking = home.value?.booking
  if (!booking?.cta_label || !booking?.cta_path) return null
  return { label: booking.cta_label, path: booking.cta_path }
})

const dateLabel = computed(() => {
  if (!import.meta.client) return ''
  return new Intl.DateTimeFormat('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  }).format(new Date())
})

async function load() {
  loading.value = true
  error.value = ''
  const cached = readCachedHome()
  if (cached) home.value = cached
  try {
    home.value = await fetchHome({ allowStale: true })
  } catch (e) {
    if (!home.value) {
      error.value = extractApiError(e, t('common.errorGeneric'))
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.home__error {
  color: var(--color-danger);
  font-size: var(--text-body-sm);
}

.home__header {
  margin-bottom: var(--spacing-8);
}

.home__instructor {
  white-space: nowrap;
}

.home__main {
  display: flex;
  flex-direction: column;
  gap: var(--section-gap);
  min-width: 0;
}

.home__link {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-8);
  min-height: 44px;
  color: var(--color-ownlane-green);
  text-decoration: none;
  font-size: var(--text-body-sm);
}

.home__recap-date {
  font-size: var(--text-body-sm);
}

.home__recap-body {
  font-size: var(--text-body-sm);
  line-height: var(--leading-body-sm);
  color: rgba(17, 17, 24, 0.82);
  max-width: 52ch;
}

.home__context-strong {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
  letter-spacing: var(--tracking-heading-sm);
}

.home__context-muted {
  margin-top: var(--spacing-4);
  color: var(--color-muted);
  font-size: var(--text-meta);
}

.home__context-warn {
  margin-top: var(--spacing-8);
  color: var(--color-warning);
  font-size: var(--text-meta);
}

.home__deeper {
  display: flex;
  flex-direction: column;
  border-top: 1px solid var(--color-border);
  padding-top: var(--spacing-8);
}

.home__deep-link {
  display: flex;
  align-items: center;
  min-height: 48px;
  text-decoration: none;
  color: inherit;
  font-size: var(--text-body-sm);
  border-bottom: 1px solid var(--color-border);
}

.home__deep-link:last-child {
  border-bottom: none;
}
</style>
