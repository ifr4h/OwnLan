<template>
  <div class="test">
    <header class="test__head">
      <h1 class="test__title">{{ t('test.title') }}</h1>
    </header>

    <template v-if="loading && !home">
      <PortalSkeleton variant="block" />
    </template>
    <p v-else-if="error" class="test__error" role="alert">{{ error }}</p>

    <template v-else-if="home">
      <section class="test__block" aria-labelledby="practical">
        <h2 id="practical" class="test__section">{{ t('test.practical') }}</h2>
        <template v-if="home.practical_test">
          <p class="test__countdown">{{ home.practical_test.countdown_label }}</p>
          <dl class="test__facts">
            <div class="test__row">
              <dt>Date</dt>
              <dd>{{ home.practical_test.test_date_display }}</dd>
            </div>
            <div v-if="home.practical_test.test_centre" class="test__row">
              <dt>{{ t('test.centre') }}</dt>
              <dd>{{ home.practical_test.test_centre }}</dd>
            </div>
            <div v-if="home.practical_test.practical_test_time" class="test__row">
              <dt>Time</dt>
              <dd>{{ home.practical_test.practical_test_time }}</dd>
            </div>
            <div v-if="home.practical_test.booking_ref" class="test__row">
              <dt>Booking ref</dt>
              <dd>{{ home.practical_test.booking_ref }}</dd>
            </div>
            <div v-if="home.practical_test.cancel_by_label" class="test__row">
              <dt>Cancel by</dt>
              <dd>{{ home.practical_test.cancel_by_label }}</dd>
            </div>
            <div class="test__row">
              <dt>Lessons</dt>
              <dd>
                {{
                  t('test.bookedBefore', {
                    count: home.practical_test.lessons_booked_before_test,
                  })
                }}
                <template v-if="home.practical_test.hours_booked_label">
                  · {{ home.practical_test.hours_booked_label }}
                </template>
              </dd>
            </div>
            <div v-if="home.practical_test.syllabus_line" class="test__row">
              <dt>Syllabus</dt>
              <dd>{{ home.practical_test.syllabus_line }}</dd>
            </div>
            <div v-if="home.practical_test.latest_mock" class="test__row">
              <dt>Latest mock</dt>
              <dd>
                {{ home.practical_test.latest_mock.result_label }}
                <template v-if="home.practical_test.latest_mock.date_display">
                  · {{ home.practical_test.latest_mock.date_display }}
                </template>
              </dd>
            </div>
          </dl>
        </template>
        <p v-else class="test__empty">{{ t('test.noPractical') }}</p>
      </section>

      <section class="test__block" aria-labelledby="theory">
        <h2 id="theory" class="test__section">{{ t('test.theory') }}</h2>
        <template v-if="home.theory">
          <p class="test__theory-label">{{ home.theory.label }}</p>
          <p
            v-if="home.theory.expires_on_display"
            class="test__expires"
          >
            {{ t('test.expires', { date: home.theory.expires_on_display }) }}
          </p>
        </template>
        <p v-else class="test__empty">{{ t('test.noTheory') }}</p>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { PortalHome } from '~/composables/usePortal'

definePageMeta({ layout: 'portal' })
useHead({ title: 'Your tests · OwnLane' })

const { t } = usePortalI18n()
const { fetchHome } = usePortal()

const home = ref<PortalHome | null>(null)
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    home.value = await fetchHome({ allowStale: true })
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
.test {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-32);
  padding-top: var(--spacing-8);
}

.test__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-md);
  letter-spacing: var(--tracking-heading-md);
}

.test__error {
  color: var(--color-danger);
  font-size: var(--text-body-sm);
}

.test__section {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
  margin-bottom: var(--spacing-12);
}

.test__countdown {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
  letter-spacing: var(--tracking-heading-sm);
  margin-bottom: var(--spacing-16);
}

.test__facts {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.test__row {
  display: grid;
  grid-template-columns: 6rem 1fr;
  gap: var(--spacing-8);
  font-size: var(--text-body-sm);
}

.test__row dt {
  color: var(--color-muted);
}

.test__theory-label {
  font-size: var(--text-body);
}

.test__expires {
  margin-top: var(--spacing-8);
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.test__empty {
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.test__block {
  padding-bottom: var(--spacing-24);
  border-bottom: 1px solid var(--color-border);
}

.test__block:last-child {
  border-bottom: none;
}
</style>
